<?php
declare(strict_types=1);

/* POST config/auth/forgot-password.php   { "email": "..." } */

require_once __DIR__ . '/../db/db_connection.php';
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/reset-mail.php';

$input = authRequirePostJson();
$email = strtolower(trim((string)($input['email'] ?? '')));

$generic = ['message' => 'If that address has an account, a reset link is on its way.'];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    authJson(200, $generic); // still generic — don't confirm the format check either
}

try {
    resetMailConfig();
    $conn->beginTransaction();
    $user = $conn->prepare('SELECT id FROM users WHERE email = :email LIMIT 1 FOR UPDATE');
    $user->execute(['email' => $email]);

    $account = $user->fetch();
    if ($account) {
        // A fresh request invalidates any link(s) sent earlier.
        $conn->prepare('DELETE FROM password_resets WHERE email = :email')->execute(['email' => $email]);

        $token = bin2hex(random_bytes(32)); // 64 hex chars — matches the column exactly
        $conn->prepare('INSERT INTO password_resets (email, token, expires_at) VALUES (:email, :token, NOW() + INTERVAL 1 HOUR)')
             ->execute([
                'email' => $email,
                'token' => hash('sha256', $token),
             ]);

    }
    $conn->commit();
    if ($account) sendPasswordResetMail($email, $token);

    authJson(200, $generic);
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log($e->getMessage());
    authJson(200, $generic); // stay generic even on failure — see note above
}
