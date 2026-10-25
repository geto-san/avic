<?php
declare(strict_types=1);

/**
 * Schema bootstrap, kept so `php config/db/schema.php` (and seed-demo.php,
 * which includes this file) keep working. The tables themselves now live in
 * numbered migrations — database/migrations/*.sql — and are applied by the
 * runner in migrator.php; `php bin/migrate.php` does the same thing with
 * more options (--status).
 *
 * Safe to run against an existing database: the baseline migration only
 * creates what is missing, then records itself in schema_migrations.
 */

require_once __DIR__ . '/db_connection.php';
require_once __DIR__ . '/migrator.php';

$appliedMigrations = migrate($conn);

if (PHP_SAPI === 'cli') {
    echo $appliedMigrations === []
        ? "Schema ready (no pending migrations).\n"
        : 'Schema ready: applied ' . implode(', ', $appliedMigrations) . "\n";
}
