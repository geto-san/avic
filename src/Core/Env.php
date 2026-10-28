<?php
declare(strict_types=1);

namespace Avic\Core;

final class Env
{
    /** Load KEY=VALUE lines from a .env file without overriding real environment variables. */
    public static function load(string $file): void
    {
        if (!is_readable($file)) {
            return;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $value = trim($value, " \t\"'");
            if ($key !== '' && getenv($key) === false) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $v = getenv($key);
        return ($v === false || $v === '') ? $default : $v;
    }

    public static function isProduction(): bool
    {
        return self::get('AVIC_ENV') === 'production';
    }
}
