<?php
declare(strict_types=1);

/** A tiny test runner: no dependencies, so `php bin/test.php` works anywhere PHP does. */
final class T
{
    public static int $passed = 0;
    public static array $failed = [];
    private static array $tests = [];
    private static ?\Closure $before = null;

    public static function before(callable $fn): void
    {
        self::$before = \Closure::fromCallable($fn);
    }

    public static function test(string $name, callable $fn): void
    {
        self::$tests[] = [$name, $fn];
    }

    public static function run(string $filter = ''): void
    {
        foreach (self::$tests as [$name, $fn]) {
            if ($filter !== '' && stripos($name, $filter) === false) {
                continue;
            }
            try {
                if (self::$before) {
                    (self::$before)();
                }
                $fn();
                self::$passed++;
                echo "  ok   $name\n";
            } catch (\Throwable $e) {
                self::$failed[] = $name;
                echo "  FAIL $name\n       " . get_class($e) . ': ' . $e->getMessage() . "\n       "
                    . basename($e->getFile()) . ':' . $e->getLine() . "\n";
            }
        }
        self::$tests = [];
    }

    public static function same(mixed $expected, mixed $actual, string $msg = ''): void
    {
        if ($expected !== $actual) {
            throw new \RuntimeException(($msg ? "$msg: " : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
        }
    }

    public static function true(mixed $v, string $msg = 'expected true'): void
    {
        if ($v !== true) {
            throw new \RuntimeException($msg);
        }
    }

    public static function contains(string $needle, string $haystack, string $msg = ''): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new \RuntimeException(($msg ? "$msg: " : '') . "'" . substr($haystack, 0, 200) . "' does not contain '$needle'");
        }
    }

    /** Assert that $fn throws $class (message must contain $needle when given). */
    public static function throws(string $class, callable $fn, string $needle = ''): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            if (!$e instanceof $class) {
                throw new \RuntimeException("expected $class, got " . get_class($e) . ': ' . $e->getMessage());
            }
            if ($needle !== '' && !str_contains($e->getMessage(), $needle)) {
                throw new \RuntimeException("exception message '{$e->getMessage()}' does not contain '$needle'");
            }
            return;
        }
        throw new \RuntimeException("expected $class to be thrown");
    }
}
