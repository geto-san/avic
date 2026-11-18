<?php
declare(strict_types=1);

/* POST config/auth/reset-password.php   { "token": "...", "password": "...", "password2": "..." } */

require_once __DIR__ . '/../db/db_connection.php';
require_once __DIR__ . '/_helpers.php';

$input     = authRequirePostJson();
$token     = (string)($input['token'] ?? '');
$password  = (string)($input['password'] ?? '');
$password2 = (string)($input['password2'] ?? '');

if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
    authJson(400, ['message' => 'This link is invalid or has expired.']);
}
if (strlen($password) < 8) {
    authJson(422, ['message' => 'Password must be at least 8 characters.']);
}
if ($password !== $password2) {
    authJson(422, ['message' => 'Passwords do not match.']);
}

try {
    $hash = hash('sha256', $token);
    $lookup = $conn->prepare('SELECT email FROM password_resets WHERE token = ?');
    $lookup->execute([$hash]);
    $email = $lookup->fetchColumn();
    if (!$email) authJson(400, ['message' => 'This link is invalid or has expired.']);
    $conn->beginTransaction();
    // Match the user -> reset-row lock order used when issuing a reset link.
    $owner = $conn->prepare('SELECT id FROM users WHERE email = ? FOR UPDATE');
    $owner->execute([$email]);
    $row = $conn->prepare('SELECT email FROM password_resets WHERE token = :token AND expires_at > NOW() LIMIT 1 FOR UPDATE');
    $row->execute(['token' => $hash]);
    $reset = $row->fetch();

    if (!$reset) {
        $conn->rollBack();
        authJson(400, ['message' => 'This link is invalid or has expired.']);
    }

    $conn->prepare('UPDATE users SET password_hash = :hash WHERE email = :email')
         ->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'email' => $reset['email']]);

    // Single-use: this token, and any sibling tokens requested for the
    // same address, are dead now regardless of which one was clicked.
    $conn->prepare('DELETE FROM password_resets WHERE email = :email')->execute(['email' => $reset['email']]);

    $conn->commit();
    authJson(200, ['message' => 'Password changed. Sign in with your new password.']);
} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log($e->getMessage());
    authJson(500, ['message' => 'Something went wrong. Please try again later.']);
}
