<?php
declare(strict_types=1);

/**
 * Minimal SQL-file migrations.
 *
 *   database/migrations/0001_baseline.sql, 0002_add_something.sql, ...
 *
 * Files run once each, in filename order; the ones already run are listed in
 * the schema_migrations table. Run them with:
 *
 *   php bin/migrate.php            apply whatever is pending
 *   php bin/migrate.php --status   show applied / pending
 *
 * Rules for writing a migration:
 *   - plain SQL, statements separated by ";" (no procedures / DELIMITER);
 *   - MySQL/MariaDB cannot roll back DDL, so a migration that fails half way
 *     leaves its earlier statements in place and is NOT recorded — write each
 *     statement so it can safely run again (IF NOT EXISTS, IF EXISTS);
 *   - never edit a migration that has been applied anywhere; add a new one.
 *
 * This file only defines functions (no side effects), so it can be unit tested.
 */

/** The collation the baseline was written with (MariaDB 11.3+). */
const MIGRATION_DEFAULT_COLLATION = 'utf8mb4_uca1400_ai_ci';

function migrationsDir(): string
{
    return dirname(__DIR__, 2) . '/database/migrations';
}

/**
 * @return array<string,string> version (filename without .sql) => full path, in apply order
 */
function migrationFiles(?string $dir = null): array
{
    $dir ??= migrationsDir();
    $files = glob(rtrim($dir, '/') . '/*.sql') ?: [];
    sort($files, SORT_STRING);
    $out = [];
    foreach ($files as $file) {
        $out[basename($file, '.sql')] = $file;
    }
    return $out;
}

/**
 * Swap the baseline's collation for another (AVIC_DB_COLLATION) so the same
 * files work on MySQL and on MariaDB older than 11.3. Only a plain identifier
 * is accepted, since it is spliced into SQL.
 */
function applyMigrationCollation(string $sql, ?string $collation): string
{
    if ($collation === null || $collation === '' || $collation === MIGRATION_DEFAULT_COLLATION) {
        return $sql;
    }
    if (!preg_match('/^[A-Za-z0-9_]+$/', $collation)) {
        throw new InvalidArgumentException('AVIC_DB_COLLATION must be a plain collation name such as utf8mb4_unicode_ci.');
    }
    return str_replace(MIGRATION_DEFAULT_COLLATION, $collation, $sql);
}

/**
 * Split a SQL script into statements on ";" — but not inside quotes, backticks
 * or comments. Comments are dropped; empty statements are skipped.
 *
 * @return string[]
 */
function splitSqlStatements(string $sql): array
{
    $statements = [];
    $current = '';
    $len = strlen($sql);
    $quote = null; // ' " or `

    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';

        if ($quote !== null) {
            $current .= $ch;
            if ($ch === '\\' && $quote !== '`' && $i + 1 < $len) {
                $current .= $sql[++$i];            // backslash escape inside a string
            } elseif ($ch === $quote) {
                if ($next === $quote) {
                    $current .= $sql[++$i];        // doubled quote = literal quote
                } else {
                    $quote = null;
                }
            }
            continue;
        }

        if ($ch === '-' && $next === '-' && ($i + 2 >= $len || ctype_space($sql[$i + 2]))) {
            $nl = strpos($sql, "\n", $i);
            $i = $nl === false ? $len : $nl;       // line comment
            $current .= "\n";
            continue;
        }
        if ($ch === '#') {
            $nl = strpos($sql, "\n", $i);
            $i = $nl === false ? $len : $nl;
            $current .= "\n";
            continue;
        }
        if ($ch === '/' && $next === '*') {
            $end = strpos($sql, '*/', $i + 2);
            $i = $end === false ? $len : $end + 1;  // block comment
            $current .= ' ';
            continue;
        }
        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $quote = $ch;
            $current .= $ch;
            continue;
        }
        if ($ch === ';') {
            if (trim($current) !== '') {
                $statements[] = trim($current);
            }
            $current = '';
            continue;
        }
        $current .= $ch;
    }
    if (trim($current) !== '') {
        $statements[] = trim($current);
    }
    return $statements;
}

function ensureMigrationsTable(PDO $conn): void
{
    $conn->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
           version varchar(100) NOT NULL,
           applied_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
           PRIMARY KEY (version)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

/**
 * @return array<string,?string> version => applied_at (null = pending), in apply order
 */
function migrationStatus(PDO $conn, ?string $dir = null): array
{
    ensureMigrationsTable($conn);
    $applied = $conn->query('SELECT version, applied_at FROM schema_migrations')->fetchAll(PDO::FETCH_KEY_PAIR);
    $status = [];
    foreach (array_keys(migrationFiles($dir)) as $version) {
        $status[$version] = $applied[$version] ?? null;
    }
    return $status;
}

/**
 * Apply every pending migration, in order. A named lock keeps two runners
 * (two deploys, a seed + a test run) from applying the same file at once.
 *
 * @return string[] versions applied by this call
 */
function migrate(PDO $conn, ?string $dir = null, ?string $collation = null): array
{
    $collation ??= (getenv('AVIC_DB_COLLATION') ?: null);
    $files = migrationFiles($dir);

    $locked = (int)$conn->query("SELECT GET_LOCK('avic_migrate', 30)")->fetchColumn();
    if ($locked !== 1) {
        throw new RuntimeException('Another migration run is in progress (could not get the lock in 30s).');
    }

    try {
        $applied = [];
        foreach (migrationStatus($conn, $dir) as $version => $appliedAt) {
            if ($appliedAt !== null) {
                continue;
            }
            $sql = applyMigrationCollation((string)file_get_contents($files[$version]), $collation);
            foreach (splitSqlStatements($sql) as $n => $statement) {
                try {
                    $conn->exec($statement);
                } catch (PDOException $e) {
                    throw new RuntimeException(sprintf(
                        'Migration %s failed at statement %d: %s (nothing was recorded; fix it and run again)',
                        $version, $n + 1, $e->getMessage()
                    ), 0, $e);
                }
            }
            $conn->prepare('INSERT INTO schema_migrations (version) VALUES (:v)')->execute(['v' => $version]);
            $applied[] = $version;
        }
        return $applied;
    } finally {
        $conn->query("SELECT RELEASE_LOCK('avic_migrate')")->fetchAll();
    }
}
