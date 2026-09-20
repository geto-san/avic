<?php
declare(strict_types=1);

/**
 * POST config/auth/forgot-password.php   { "email": "..." }
 *
 * Always responds with the same generic message, whether or not the
 * address has an account — the response itself must never be usable to
 * discover which emails are registered.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db/db_connection.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = strtolower(trim((string)((is_array($input) ? $input['email'] : null) ?? '')));

$generic = ['message' => 'If that address has an account, a reset link is on its way.'];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode($generic); // still generic — don't confirm the format check even
    exit;
}

try {
    $user = $conn->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $user->execute(['email' => $email]);

    $account = $user->fetch();
    if ($account) {
        // A fresh request invalidates any link(s) sent earlier.
        $conn->prepare('DELETE FROM password_resets WHERE email = :email')->execute(['email' => $email]);

        $token = bin2hex(random_bytes(32)); // 64 hex chars — matches the column exactly
        $conn->prepare('INSERT INTO password_resets (email, token, expires_at) VALUES (:email, :token, :exp)')
             ->execute([
                'email' => $email,
                'token' => $token,
                'exp'   => (new DateTime('+1 hour'))->format('Y-m-d H:i:s'),
             ]);

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $path = '/pages/auth/reset-password.html?token=' . $token;
        $link = $scheme . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $path;

        $sent = @mail($email, 'Reset your AVIC password',
            "Use this link within the next hour to choose a new password:\n\n$link\n\n" .
            "If you didn't ask for this, you can ignore this email.");

        // Most local/dev setups have no MTA configured, so mail() silently
        // fails. Log the link so the flow is still testable end to end
        // without a real mail server. Remove this in production.
        // Only server-generated values are logged (DB id + token path): the
        // address and Host header come from the request, so they stay out of
        // the log (CWE-117 log injection).
        if (!$sent) {
            error_log('[password reset] user #' . (int)$account['id'] . ' -> ' . $path);
        }
    }

    echo json_encode($generic);
} catch (PDOException $e) {
    error_log($e->getMessage());
    echo json_encode($generic); // stay generic even on failure — see note above
}
