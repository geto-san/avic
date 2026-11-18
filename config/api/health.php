<?php
declare(strict_types=1);

// Public readiness probe returns no database credentials, versions or user data.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}
require_once __DIR__ . '/../db/db_connection.php';
require_once __DIR__ . '/../db/migrator.php';
try {
    $conn->query('SELECT 1 FROM users LIMIT 1');
    $applied = $conn->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    $ok = !array_diff(array_keys(migrationFiles()), $applied);
    http_response_code($ok ? 200 : 503);
    echo json_encode(['ok' => $ok]);
} catch (PDOException $error) {
    http_response_code(503);
    echo json_encode(['ok' => false]);
}
