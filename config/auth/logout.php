<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    authJson(405, ['message' => 'Method not allowed']);
}
requireCsrf();

$_SESSION = [];
session_destroy();
$cookie = session_get_cookie_params();
unset($cookie['lifetime']);
setcookie(session_name(), '', ['expires' => time() - 3600] + $cookie);

http_response_code(204);
header('Content-Type: application/json; charset=utf-8');
