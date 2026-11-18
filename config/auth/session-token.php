<?php
declare(strict_types=1);

require_once __DIR__ . '/../csrf.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}
echo json_encode(['csrf_token' => csrfToken()]);
