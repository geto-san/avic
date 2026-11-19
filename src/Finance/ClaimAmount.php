<?php
declare(strict_types=1);

namespace Avic\Finance;

use InvalidArgumentException;

/** Exact two-decimal posting units, matching the existing DECIMAL(12,2) columns. */
final class ClaimAmount
{
    public static function units(mixed $value): int
    {
        if (is_float($value) && is_finite($value)) {
            $value = json_encode($value, JSON_THROW_ON_ERROR);
        }
        if (!is_int($value) && !is_string($value)) {
            throw new InvalidArgumentException('Enter a monetary amount.');
        }
        if (!preg_match('/\A([0-9]{1,10})(?:\.([0-9]{1,2}))?\z/', (string)$value, $parts)) {
            throw new InvalidArgumentException('Use a nonnegative amount with up to ten whole digits and two decimal places.');
        }
        return (int)$parts[1] * 100 + (int)str_pad($parts[2] ?? '', 2, '0');
    }

    public static function decimal(int $units): string
    {
        return sprintf('%d.%02d', intdiv($units, 100), $units % 100);
    }
}
