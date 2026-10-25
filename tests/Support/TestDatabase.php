<?php
declare(strict_types=1);

namespace Avic\Tests\Support;

use PDO;
use PDOException;
use RuntimeException;

/** The throwaway test database: connect, migrate, and reset to the demo seed between tests. */
final class TestDatabase
{
    private static ?PDO $pdo = null;
    private static bool $migrated = false;

    public static function connect(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }
        $port = (string)getenv('AVIC_DB_PORT');
        $dsn = sprintf(
            'mysql:host=%s;%sdbname=%s;charset=utf8mb4',
            getenv('AVIC_DB_HOST'),
            $port !== '' ? "port=$port;" : '',
            getenv('AVIC_DB_NAME')
        );
        try {
            return self::$pdo = new PDO($dsn, (string)getenv('AVIC_DB_USER'), (string)getenv('AVIC_DB_PASS'), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException(
                "Cannot reach the test database \"" . getenv('AVIC_DB_NAME') . "\": " . $e->getMessage() . "\n" .
                "Create it once (as a DB admin):  CREATE DATABASE avic_test; GRANT ALL ON avic_test.* TO 'avic'@'localhost';\n" .
                'or point AVIC_TEST_DB_HOST / _NAME / _USER / _PASS at one.',
                0,
                $e
            );
        }
    }

    /** Bring the schema up to date (once per run). */
    public static function migrate(): void
    {
        if (self::$migrated) {
            return;
        }
        require_once dirname(__DIR__, 2) . '/config/db/migrator.php';
        \migrate(self::connect());
        self::$migrated = true;
    }

    /** Empty every table (keeping the migration ledger) and load the demo seed. */
    public static function resetAndSeed(): void
    {
        $pdo = self::connect();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            if ($table !== 'schema_migrations') {
                $pdo->exec('TRUNCATE TABLE `' . $table . '`');
            }
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__, 2) . '/config/db/seed-demo.php') . ' 2>&1';
        exec($command, $output, $code);
        if ($code !== 0) {
            throw new RuntimeException("Seeding the test database failed:\n" . implode("\n", $output));
        }
    }

    /** Drop every table — for the migration tests, which start from an empty database. */
    public static function dropAll(): void
    {
        $pdo = self::connect();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $pdo->exec('DROP TABLE `' . $table . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        self::$migrated = false;
    }
}
