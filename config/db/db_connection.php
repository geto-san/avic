<?php
declare(strict_types=1);

// Store and compare timestamps in UTC in both PHP and MySQL sessions.
date_default_timezone_set('UTC');

/* Database connection, configured from the environment: */

const AVIC_DEV_DB_PASSWORD = '12345678';

/** Stop the request with a generic JSON error (the real reason goes to the log only). */
function avicConfigFail(string $logMessage, string $publicMessage): void
{
    error_log($logMessage);
    if (PHP_SAPI !== 'cli') {
        http_response_code(500);
        header('Content-Type: application/json');
    }
    echo json_encode(['message' => $publicMessage]);
    exit(1);
}

$server   = getenv('AVIC_DB_HOST') ?: 'localhost';
$port     = (string)(getenv('AVIC_DB_PORT') ?: '');
$socket   = (string)(getenv('AVIC_DB_SOCKET') ?: ''); // Optional private local database socket.
$dbname   = getenv('AVIC_DB_NAME') ?: 'avic_portal';
$user     = getenv('AVIC_DB_USER') ?: 'avic';
$password = getenv('AVIC_DB_PASS');
$isProduction = strtolower((string)getenv('AVIC_ENV')) === 'production';

if ($password === false || $password === '') {
    if ($isProduction) {
        avicConfigFail('AVIC_DB_PASS must be set when AVIC_ENV=production.', 'Server is not configured correctly');
    }
    $password = AVIC_DEV_DB_PASSWORD;
}
if ($isProduction && $password === AVIC_DEV_DB_PASSWORD) {
    avicConfigFail('The development database password may not be used when AVIC_ENV=production.', 'Server is not configured correctly');
}
if ($port !== '' && !ctype_digit($port)) {
    avicConfigFail('AVIC_DB_PORT must be a number.', 'Server is not configured correctly');
}

try {
    $conn = new PDO(
        'mysql:' . ($socket !== '' ? "unix_socket=$socket;" : "host=$server;" . ($port !== '' ? "port=$port;" : '')) . "dbname=$dbname;charset=utf8mb4", // utf8mb4 preserves accented names and other Unicode text.
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // DB errors throw exceptions instead of failing silently.
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch results as associative arrays by default (e.g. $row['column_name'] instead of $row[0])
            PDO::ATTR_EMULATE_PREPARES   => false,                  // Use native prepared statements if possible (more secure, faster)
        ]
    );
    $conn->exec("SET time_zone = '+00:00'");
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['message' => 'Database connection failed']);
    exit(1); // non-zero so migrate/seed scripts (and CI) fail loudly instead of looking fine
}

unset($password); // no reason to keep the credential in scope once connected
