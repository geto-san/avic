<?php
declare(strict_types=1); // Strict typing for better type safety (makes PHP enforce argument types strictly instead of silently coercing (e.g. a string "5" won't quietly become an int))

$server   = "localhost";
$dbname   = "avic_portal";
$user     = "avic";
$password = "12345678";

try {
    $conn = new PDO(
        "mysql:host=$server;dbname=$dbname;charset=utf8mb4", // utf8mb4 so that multi-byte characters (emoji, accented names) store correctly.
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // DB errors throw exceptions instead of failing silently.
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch results as associative arrays by default (e.g. $row['column_name'] instead of $row[0])
            PDO::ATTR_EMULATE_PREPARES   => false,                  // Use native prepared statements if possible (more secure, faster)
        ]
    );
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Database connection failed']);
    exit;
}
