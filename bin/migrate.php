<?php
declare(strict_types=1);

/**
 * Apply database migrations.
 *
 *   php bin/migrate.php            apply whatever is pending
 *   php bin/migrate.php --status   list applied / pending, change nothing
 *
 * Connection settings come from the environment (see .env.example).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/db/db_connection.php';
require_once __DIR__ . '/../config/db/migrator.php';

try {
    if (in_array('--status', $argv, true)) {
        foreach (migrationStatus($conn) as $version => $appliedAt) {
            printf("  %-8s %s\n", $appliedAt === null ? 'pending' : 'applied', $version);
        }
        exit(0);
    }

    $applied = migrate($conn);
    if ($applied === []) {
        echo "Database is up to date.\n";
    } else {
        foreach ($applied as $version) {
            echo "  applied $version\n";
        }
        echo count($applied) . " migration(s) applied.\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}
