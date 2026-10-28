<?php
declare(strict_types=1);

// Tests run against their own database, which is wiped and rebuilt before every test.
putenv('AVIC_DB_NAME=' . (getenv('AVIC_TEST_DB_NAME') ?: 'avic_test'));
foreach (['HOST', 'PORT', 'USER', 'PASS'] as $k) {
    if (getenv("AVIC_TEST_DB_$k") !== false) {
        putenv("AVIC_DB_$k=" . getenv("AVIC_TEST_DB_$k"));
    }
}
require dirname(__DIR__) . '/src/bootstrap.php';
require __DIR__ . '/Harness.php';
require __DIR__ . '/Fixtures.php';

use Avic\Core\Db;
use Avic\Core\Migrator;

function fresh_database(): void
{
    Db::reset();
    Migrator::wipe();
    Migrator::run();
}
T::before('fresh_database');
