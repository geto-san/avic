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

header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/../db/db_connection.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

$input     = json_decode(file_get_contents('php://input'), true);
$token     = (string)((is_array($input) ? $input['token'] : null) ?? '');
$password  = (string)((is_array($input) ? $input['password'] : null) ?? '');
$password2 = (string)((is_array($input) ? $input['password2'] : null) ?? '');

if ($token === '') {
    http_response_code(400);
    echo json_encode(['message' => 'This link is invalid or has expired.']);
    exit;
}
if (strlen($password) < 8) {
    http_response_code(422);
    echo json_encode(['message' => 'Password must be at least 8 characters.']);
    exit;
}
if ($password !== $password2) {
    http_response_code(422);
    echo json_encode(['message' => 'Passwords do not match.']);
    exit;
}

try {
    $row = $conn->prepare('SELECT email FROM password_resets WHERE token = :token AND expires_at > NOW() LIMIT 1');
    $row->execute(['token' => $token]);
    $reset = $row->fetch();

    if (!$reset) {
        http_response_code(400);
        echo json_encode(['message' => 'This link is invalid or has expired.']);
        exit;
    }

    $conn->prepare('UPDATE users SET password_hash = :hash WHERE email = :email')
         ->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'email' => $reset['email']]);

    // Single-use: this token, and any sibling tokens requested for the
    // same address, are dead now regardless of which one was clicked.
    $conn->prepare('DELETE FROM password_resets WHERE email = :email')->execute(['email' => $reset['email']]);

    echo json_encode(['message' => 'Password changed. Sign in with your new password.']);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['message' => 'Something went wrong. Please try again later.']);
}
