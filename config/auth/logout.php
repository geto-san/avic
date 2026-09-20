<?php
declare(strict_types=1);

require_once __DIR__ . '/../session.php';

avicSession();

$_SESSION = [];
session_destroy();

http_response_code(204);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['message' => 'Signed out']);
