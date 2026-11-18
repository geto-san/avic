<?php
declare(strict_types=1);

namespace Avic\Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/config/db/migrator.php';

final class MigratorTest extends TestCase
{
    /** @dataProvider splitCases */
    public function testSplitSqlStatements(string $sql, array $expected): void
    {
        $this->assertSame($expected, splitSqlStatements($sql));
    }

    /** @return array<string,array{string,list<string>}> */
    public static function splitCases(): array
    {
        return [
            'two statements'               => ["SELECT 1; SELECT 2;", ['SELECT 1', 'SELECT 2']],
            'last one without semicolon'   => ["SELECT 1; SELECT 2", ['SELECT 1', 'SELECT 2']],
            'empty and whitespace'         => ["  \n ;; \n", []],
            'semicolon in single quotes'   => ["INSERT INTO t VALUES ('a;b'); SELECT 1;", ["INSERT INTO t VALUES ('a;b')", 'SELECT 1']],
            'semicolon in double quotes'   => ['INSERT INTO t VALUES ("a;b"); SELECT 1;', ['INSERT INTO t VALUES ("a;b")', 'SELECT 1']],
            'semicolon in backticks'       => ["CREATE TABLE `a;b` (id int); SELECT 1;", ['CREATE TABLE `a;b` (id int)', 'SELECT 1']],
            'doubled quote escape'         => ["SELECT 'it''s; fine'; SELECT 2;", ["SELECT 'it''s; fine'", 'SELECT 2']],
            'backslash escape'             => ["SELECT 'a\\'; b'; SELECT 2;", ["SELECT 'a\\'; b'", 'SELECT 2']],
            'line comment hides semicolon' => ["SELECT 1; -- not; a split\nSELECT 2;", ['SELECT 1', 'SELECT 2']],
            'hash comment'                 => ["SELECT 1; # nope;\nSELECT 2;", ['SELECT 1', 'SELECT 2']],
            'block comment'                => ["SELECT /* a; b */ 1; SELECT 2;", ['SELECT   1', 'SELECT 2']],
            'double dash is arithmetic'    => ["SELECT 5--3;", ['SELECT 5--3']],
            'comment only'                 => ["-- nothing here\n", []],
        ];
    }

    public function testTheRealBaselineSplitsIntoFourteenCreateTables(): void
    {
        $statements = splitSqlStatements((string)file_get_contents(migrationsDir() . '/0001_baseline.sql'));
        $this->assertCount(14, $statements);
        foreach ($statements as $statement) {
            $this->assertStringStartsWith('CREATE TABLE IF NOT EXISTS ', $statement);
        }
    }

    public function testCollationIsLeftAloneByDefault(): void
    {
        $sql = 'CREATE TABLE t (a int) COLLATE=' . MIGRATION_DEFAULT_COLLATION;
        $this->assertSame($sql, applyMigrationCollation($sql, null));
        $this->assertSame($sql, applyMigrationCollation($sql, ''));
        $this->assertSame($sql, applyMigrationCollation($sql, MIGRATION_DEFAULT_COLLATION));
    }

    public function testCollationCanBeSwappedForOlderServers(): void
    {
        $this->assertSame(
            'CREATE TABLE t (a int) COLLATE=utf8mb4_unicode_ci',
            applyMigrationCollation('CREATE TABLE t (a int) COLLATE=' . MIGRATION_DEFAULT_COLLATION, 'utf8mb4_unicode_ci')
        );
    }

    /** @dataProvider hostileCollations */
    public function testCollationIsNeverSplicedIntoSqlUnchecked(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        applyMigrationCollation('x', $value);
    }

    /** @return array<string,array{string}> */
    public static function hostileCollations(): array
    {
        return ['injection' => ['x; DROP TABLE users'], 'quote' => ["a'b"], 'space' => ['a b'], 'dash' => ['a-b']];
    }

    public function testMigrationFilesAreSortedAndKeyedByVersion(): void
    {
        $dir = sys_get_temp_dir() . '/avic-mig-' . uniqid();
        mkdir($dir);
        foreach (['0010_late.sql', '0002_second.sql', '0001_first.sql', 'notes.txt'] as $name) {
            file_put_contents("$dir/$name", '');
        }
        try {
            $this->assertSame(['0001_first', '0002_second', '0010_late'], array_keys(migrationFiles($dir)));
        } finally {
            array_map('unlink', glob("$dir/*") ?: []);
            rmdir($dir);
        }
    }
}
