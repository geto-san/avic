<?php
require_once __DIR__ . '/../db/db_connection.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_error('method', 'Use POST.', 405);
}

?>