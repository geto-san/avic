<?php
declare(strict_types=1);

namespace Avic\Tests\Feature;

use Avic\Tests\Support\TestDatabase;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/config/db/migrator.php';

/**
 * The migration runner against a real database. Each test starts from an EMPTY
 * test database and leaves it freshly migrated for the next test class.
 */
final class MigrationsTest extends TestCase
{
    private const TABLES = [
        'adjuster_reviews', 'audit_log', 'claim_documents', 'claim_status_history', 'claims', 'garage_estimates',
        'login_attempts', 'notifications', 'password_resets', 'payouts', 'policies', 'settings', 'users', 'work_orders',
    ];

    /** @var list<string> */
    private array $dirs = [];

    protected function setUp(): void
    {
        TestDatabase::dropAll();
    }

    protected function tearDown(): void
    {
        foreach ($this->dirs as $dir) {
            array_map('unlink', glob("$dir/*") ?: []);
            rmdir($dir);
        }
        TestDatabase::dropAll();
        TestDatabase::migrate();
    }

    /** @param array<string,string> $files filename => SQL */
    private function migrationsDirWith(array $files): string
    {
        $dir = sys_get_temp_dir() . '/avic-migs-' . uniqid();
        mkdir($dir);
        foreach ($files as $name => $sql) {
            file_put_contents("$dir/$name", $sql);
        }
        return $this->dirs[] = $dir;
    }

    /** @return list<string> */
    private function tables(): array
    {
        $tables = TestDatabase::connect()->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
        sort($tables);
        return $tables;
    }

    public function testTheBaselineBuildsTheWholeSchemaFromNothing(): void
    {
        $applied = migrate(TestDatabase::connect());

        $this->assertSame(['0001_baseline'], $applied);
        $expected = [...self::TABLES, 'schema_migrations'];
        sort($expected);
        $this->assertSame($expected, $this->tables());
    }

    public function testRunningItAgainDoesNothing(): void
    {
        migrate(TestDatabase::connect());

        $this->assertSame([], migrate(TestDatabase::connect()));
        $this->assertSame(1, (int)TestDatabase::connect()->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn());
    }

    public function testADatabaseBuiltBeforeMigrationsExistedIsAdoptedWithoutLosingData(): void
    {
        $pdo = TestDatabase::connect();
        migrate($pdo);
        $pdo->exec("INSERT INTO users (uuid, full_name, email, password_hash) VALUES (UUID(), 'Kept Person', 'kept@example.ug', 'x')");
        $pdo->exec('DROP TABLE schema_migrations');   // as if the ledger never existed (built by the old schema.php)

        $applied = migrate($pdo);

        $this->assertSame(['0001_baseline'], $applied, 'recorded, harmlessly');
        $this->assertSame(1, (int)$pdo->query("SELECT COUNT(*) FROM users WHERE email = 'kept@example.ug'")->fetchColumn());
    }

    public function testPendingMigrationsRunOnceInFilenameOrder(): void
    {
        $dir = $this->migrationsDirWith([
            '0002_second.sql' => "CREATE TABLE mig_b (id int) ;\nINSERT INTO mig_b VALUES (1), (2);",
            '0001_first.sql'  => 'CREATE TABLE mig_a (id int);',
        ]);
        $pdo = TestDatabase::connect();

        $this->assertSame(['0001_first', '0002_second'], migrate($pdo, $dir));
        $this->assertSame([], migrate($pdo, $dir));
        $this->assertSame(2, (int)$pdo->query('SELECT COUNT(*) FROM mig_b')->fetchColumn(), 'the second file ran exactly once');
    }

    public function testStatusShowsAppliedAndPending(): void
    {
        $dir = $this->migrationsDirWith(['0001_first.sql' => 'CREATE TABLE mig_a (id int);']);
        $pdo = TestDatabase::connect();
        migrate($pdo, $dir);
        file_put_contents("$dir/0002_later.sql", 'CREATE TABLE mig_c (id int);');

        $status = migrationStatus($pdo, $dir);

        $this->assertSame(['0001_first', '0002_later'], array_keys($status));
        $this->assertNotNull($status['0001_first']);
        $this->assertNull($status['0002_later'], 'pending');
    }

    public function testAFailingMigrationIsNotRecordedAndCanBeFixedAndRetried(): void
    {
        $dir = $this->migrationsDirWith([
            '0001_good.sql' => 'CREATE TABLE mig_a (id int);',
            '0002_bad.sql'  => "CREATE TABLE IF NOT EXISTS mig_b (id int);\nINSERT INTO no_such_table VALUES (1);",
        ]);
        $pdo = TestDatabase::connect();

        try {
            migrate($pdo, $dir);
            $this->fail('expected the bad migration to throw');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('0002_bad', $e->getMessage());
            $this->assertStringContainsString('statement 2', $e->getMessage());
        }
        $this->assertSame(['0001_good'], array_keys(array_filter(migrationStatus($pdo, $dir))), 'the failed one is not recorded');

        file_put_contents("$dir/0002_bad.sql", 'CREATE TABLE IF NOT EXISTS mig_b (id int);');
        $this->assertSame(['0002_bad'], migrate($pdo, $dir), 'after the fix only that one runs');
    }

    public function testASemicolonInsideAStringDoesNotSplitAStatement(): void
    {
        $dir = $this->migrationsDirWith(['0001_text.sql' => "CREATE TABLE mig_t (note varchar(50));\nINSERT INTO mig_t VALUES ('a;b; c');"]);
        $pdo = TestDatabase::connect();

        migrate($pdo, $dir);

        $this->assertSame('a;b; c', $pdo->query('SELECT note FROM mig_t')->fetchColumn());
    }
}
