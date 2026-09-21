<?php
declare(strict_types=1);

/**
 * Shared plumbing for the auth endpoints (register/login/forgot/reset).
 * Mirrors config/api/_helpers.php so both API layers follow the same shape.
 */

header('Content-Type: application/json; charset=utf-8');

function authJson(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

/**
 * Require a POST request with a JSON object body and return it as an array.
 * Emits 405/400 and exits when the method or body is wrong.
 */
function authRequirePostJson(): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        authJson(405, ['message' => 'Method not allowed']);
    }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        authJson(400, ['message' => 'Invalid request body']);
    }
    return $input;
}

/** RFC 4122 v4 UUID, used as the public identifier for new user accounts. */
function uuidv4(): string
{
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff),
        random_int(0, 0x0fff) | 0x4000,
        random_int(0, 0x3fff) | 0x8000,
        random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff));
}
