<?php
declare(strict_types=1);

require_once __DIR__ . '/../db/db_connection.php';
require_once __DIR__ . '/../session.php';

header('Content-Type: application/json; charset=utf-8');

function respond(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['message' => 'Method not allowed']);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    respond(400, ['message' => 'Invalid request body']);
}

$email    = strtolower(trim((string)($input['email'] ?? '')));
$password = (string)($input['password'] ?? '');
$remember = !empty($input['remember']);

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    respond(400, ['message' => 'Enter your email and password.']);
}

const ATTEMPT_WINDOW_MINUTES = 15;
const ATTEMPT_THRESHOLD      = 5;
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

try {
    // Tidy the log so it stays small; old rows are irrelevant.
    $conn->prepare('DELETE FROM login_attempts WHERE at < (NOW() - INTERVAL 1 DAY)')->execute();

    // Lockout: too many failures for this email from this IP in the window.
    $count = $conn->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE ip = :ip AND email = :email AND ok = 0
           AND at > (NOW() - INTERVAL :mins MINUTE)'
    );
    $count->bindValue(':ip', $ip);
    $count->bindValue(':email', $email);
    $count->bindValue(':mins', ATTEMPT_WINDOW_MINUTES, PDO::PARAM_INT);
    $count->execute();
    if ((int)$count->fetchColumn() >= ATTEMPT_THRESHOLD) {
        respond(429, ['message' => sprintf('Too many failed attempts. Try again in %d minutes.', ATTEMPT_WINDOW_MINUTES)]);
    }

    $find = $conn->prepare(
        'SELECT id, uuid, full_name, email, password_hash, role, status
         FROM users WHERE email = :email LIMIT 1'
    );
    $find->execute(['email' => $email]);
    $user = $find->fetch();

    $bad = !$user || !password_verify($password, $user['password_hash']);

    // Log every attempt so both failures and successes are traceable.
    $log = $conn->prepare('INSERT INTO login_attempts (ip, email, ok) VALUES (:ip, :email, :ok)');
    $log->execute(['ip' => $ip, 'email' => $email, 'ok' => $bad ? 0 : 1]);

    if ($bad) {
        // One generic message: never hint whether the email exists.
        respond(401, ['message' => 'Invalid email or password.']);
    }

    if ($user['status'] !== 'active') {
        respond(403, ['message' => 'This account is ' . $user['status'] . ' and cannot sign in right now.']);
    }

    avic_session($remember);
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'    => (int)$user['id'],
        'uuid'  => $user['uuid'],
        'name'  => $user['full_name'],
        'email' => $user['email'],
        'role'  => $user['role'],
        'status'=> $user['status'],
    ];

    $conn->prepare('UPDATE users SET last_login = NOW() WHERE id = :id')->execute(['id' => $user['id']]);

    respond(200, [
        'message' => 'Signed in',
        'user' => [
            'id'        => (int)$user['id'],
            'uuid'      => $user['uuid'],
            'full_name' => $user['full_name'],
            'email'     => $user['email'],
            'role'      => $user['role'],
            'status'    => $user['status'],
        ],
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    respond(500, ['message' => 'Something went wrong. Please try again later.']);
}