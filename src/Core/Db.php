<?php
declare(strict_types=1);

namespace Avic\Core;

use PDO;
use RuntimeException;
use Throwable;

final class Db
{
    private static ?PDO $pdo = null;
    private static int $depth = 0;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $pass = Env::get('AVIC_DB_PASS', '');
            if (Env::isProduction() && $pass === '') {
                throw new RuntimeException('AVIC_ENV=production requires AVIC_DB_PASS.');
            }
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                Env::get('AVIC_DB_HOST', '127.0.0.1'),
                (int)Env::get('AVIC_DB_PORT', '3306'),
                Env::get('AVIC_DB_NAME', 'avic_portal')
            );
            self::$pdo = new PDO($dsn, Env::get('AVIC_DB_USER', 'avic'), $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+03:00'",
            ]);
        }
        return self::$pdo;
    }

    public static function reset(): void
    {
        self::$pdo = null;
        self::$depth = 0;
    }

    /** @return array<string,mixed>|null */
    public static function one(string $sql, array $params = []): ?array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return list<array<string,mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function val(string $sql, array $params = []): mixed
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $v = $stmt->fetchColumn();
        return $v === false ? null : $v;
    }

    /** Run a write; returns affected rows. */
    public static function run(string $sql, array $params = []): int
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::run($sql, $params);
        return (int)self::pdo()->lastInsertId();
    }

    /**
     * Run $fn atomically. Nested calls join the outer transaction; any exception
     * rolls the whole thing back and is re-thrown.
     */
    public static function tx(callable $fn): mixed
    {
        $pdo = self::pdo();
        if (self::$depth > 0) {
            return $fn();
        }
        $pdo->beginTransaction();
        self::$depth = 1;
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        } finally {
            self::$depth = 0;
        }
    }
}
