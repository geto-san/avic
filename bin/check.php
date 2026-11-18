<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../config/db/db_connection.php';
require_once __DIR__ . '/../config/db/migrator.php';
require_once __DIR__ . '/../config/api/upload-files.php';
require_once __DIR__ . '/../config/auth/reset-mail.php';

$failures = [];
try {
    $applied = $conn->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    if (array_diff(array_keys(migrationFiles()), $applied)) $failures[] = 'Apply pending migrations with php bin/migrate.php.';
} catch (PDOException $error) {
    $failures[] = 'Initialize the schema with php bin/migrate.php.';
}
$directory = uploadDir();
$parent = is_dir($directory) ? $directory : dirname($directory);
$realParent = realpath($parent);
$root = realpath(dirname(__DIR__));
if (!$realParent || !is_writable($parent)) $failures[] = 'AVIC_UPLOAD_DIR or its parent must be writable.';
if ($realParent && ($realParent === $root || str_starts_with($realParent, $root . '/'))) $failures[] = 'AVIC_UPLOAD_DIR must be outside the web root.';
try {
    resetMailConfig();
} catch (RuntimeException $error) {
    $failures[] = $error->getMessage();
}
$binary = explode(' ', trim((string)ini_get('sendmail_path')))[0];
if (!$binary || !is_executable($binary)) $failures[] = 'Configure PHP sendmail_path with a working mail transport.';
echo "Database connection: OK\n";
foreach ($failures as $failure) echo "CHECK: $failure\n";
if (!$failures) echo "Configuration checks: OK. Verify actual email delivery on the deployed host.\n";
exit($failures ? 1 : 0);
