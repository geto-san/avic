<?php
declare(strict_types=1);

namespace Avic\Core;

use RuntimeException;

/** Applies database/migrations/*.sql once each, in filename order. */
final class Migrator
{
    public static function dir(): string
    {
        return AVIC_ROOT . '/database/migrations';
    }

    /** @return array{applied:list<string>,pending:list<string>} */
    public static function status(): array
    {
        Db::pdo()->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
            filename VARCHAR(120) NOT NULL PRIMARY KEY,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $applied = array_column(Db::all('SELECT filename FROM schema_migrations ORDER BY filename'), 'filename');
        $files = array_map('basename', glob(self::dir() . '/*.sql') ?: []);
        sort($files);
        return ['applied' => $applied, 'pending' => array_values(array_diff($files, $applied))];
    }

    /** @return list<string> the files applied now */
    public static function run(): array
    {
        $done = [];
        foreach (self::status()['pending'] as $file) {
            $sql = (string)file_get_contents(self::dir() . '/' . $file);
            $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? '';
            foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [] as $stmt) {
                if (trim($stmt) !== '') {
                    Db::pdo()->exec($stmt);
                }
            }
            Db::run('INSERT INTO schema_migrations (filename) VALUES (?)', [$file]);
            $done[] = $file;
        }
        return $done;
    }

    /** Test helper: drop every table. Refuses any database whose name does not end in _test. */
    public static function wipe(): void
    {
        $name = (string)Db::val('SELECT DATABASE()');
        if (!str_ends_with($name, '_test')) {
            throw new RuntimeException("Refusing to wipe '$name': only databases ending in _test.");
        }
        Db::pdo()->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (Db::all('SHOW TABLES') as $row) {
            Db::pdo()->exec('DROP TABLE `' . reset($row) . '`');
        }
        Db::pdo()->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
