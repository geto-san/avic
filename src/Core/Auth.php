<?php
declare(strict_types=1);

namespace Avic\Core;

final class Auth
{
    private const MAX_FAILURES = 5;
    private const WINDOW_MINUTES = 15;
    private static ?array $user = null;
    private static bool $loaded = false;

    /** @return array<string,mixed>|null the signed-in, active user */
    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = $_SESSION['uid'] ?? null;
            if ($id) {
                $row = Db::one('SELECT id, full_name, email, phone, role, region, specialty, status FROM users WHERE id = ?', [$id]);
                if ($row && $row['status'] === 'active') {
                    self::$user = $row;
                } else {
                    unset($_SESSION['uid']);
                }
            }
        }
        return self::$user;
    }

    /** Require a signed-in user (optionally with a given role). */
    public static function require(?string $role = null): array
    {
        $u = self::user();
        if ($u === null) {
            View::redirect('/login');
        }
        if ($role !== null && $u['role'] !== $role) {
            throw new HttpException(403);
        }
        return $u;
    }

    /** Verify credentials; returns the user row or throws a RuleViolation. */
    public static function attempt(string $email, string $password, string $ip): array
    {
        $email = mb_strtolower(trim($email));
        $failures = (int)Db::val(
            'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND email = ? AND ok = 0 AND at > (NOW() - INTERVAL ' . self::WINDOW_MINUTES . ' MINUTE)',
            [$ip, $email]
        );
        if ($failures >= self::MAX_FAILURES) {
            throw new RuleViolation('Too many failed attempts. Try again in ' . self::WINDOW_MINUTES . ' minutes.');
        }

        $row = Db::one('SELECT * FROM users WHERE email = ?', [$email]);
        // Always run a hash check so response time does not reveal whether the email exists.
        $hash = $row['password_hash'] ?? '$2y$10$KbXpx6QkQ8Q5sFcW62T6Au1vx1tbM8EL9PwasfOEyUOI/KPDyFJ92';
        $ok = password_verify($password, $hash) && $row !== null && $row['status'] === 'active';

        Db::run('INSERT INTO login_attempts (ip, email, ok) VALUES (?, ?, ?)', [$ip, $email, $ok ? 1 : 0]);
        if (!$ok) {
            throw new RuleViolation('Incorrect email or password.');
        }
        Db::run('UPDATE users SET last_login = NOW() WHERE id = ?', [$row['id']]);
        return $row;
    }

    public static function login(array $row): void
    {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$row['id'];
        self::$loaded = false;
        self::$user = null;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        self::$loaded = true;
        self::$user = null;
    }

    public static function isAdjuster(?array $u): bool
    {
        return $u !== null && $u['role'] === 'adjuster';
    }
}
