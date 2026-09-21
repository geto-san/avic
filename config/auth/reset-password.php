<?php
declare(strict_types=1);

/**
 * POST config/auth/reset-password.php   { "token": "...", "password": "...", "password2": "..." }
 *
 * The token must exist and not be expired. On success the password is
 * updated and every outstanding reset row for that address is deleted —
 * this is what makes the link single-use, including any older links the
 * same person requested and never opened.
 */

require_once __DIR__ . '/../db/db_connection.php';
require_once __DIR__ . '/_helpers.php';

$input     = authRequirePostJson();
$token     = (string)($input['token'] ?? '');
$password  = (string)($input['password'] ?? '');
$password2 = (string)($input['password2'] ?? '');

if ($token === '') {
    authJson(400, ['message' => 'This link is invalid or has expired.']);
}
if (strlen($password) < 8) {
    authJson(422, ['message' => 'Password must be at least 8 characters.']);
}
if ($password !== $password2) {
    authJson(422, ['message' => 'Passwords do not match.']);
}

try {
    $row = $conn->prepare('SELECT email FROM password_resets WHERE token = :token AND expires_at > NOW() LIMIT 1');
    $row->execute(['token' => $token]);
    $reset = $row->fetch();

    if (!$reset) {
        authJson(400, ['message' => 'This link is invalid or has expired.']);
    }

    $conn->prepare('UPDATE users SET password_hash = :hash WHERE email = :email')
         ->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'email' => $reset['email']]);

    // Single-use: this token, and any sibling tokens requested for the
    // same address, are dead now regardless of which one was clicked.
    $conn->prepare('DELETE FROM password_resets WHERE email = :email')->execute(['email' => $reset['email']]);

    authJson(200, ['message' => 'Password changed. Sign in with your new password.']);
} catch (PDOException $e) {
    error_log($e->getMessage());
    authJson(500, ['message' => 'Something went wrong. Please try again later.']);
}
