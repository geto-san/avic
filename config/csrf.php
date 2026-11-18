<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';

/** A random secret bound to the browser's server session, not to its user ID. */
function csrfToken(): string
{
    avicSession();
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** All writes, including multipart uploads and login, must prove same-session intent. */
function requireCsrf(): void
{
    avicSession();
    $expected = $_SESSION['csrf_token'] ?? '';
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['message' => 'Your form session expired. Reload the page and try again.']);
        exit;
    }
}
