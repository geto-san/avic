<?php
declare(strict_types=1);

namespace Avic\Core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function valid(?string $submitted): bool
    {
        return is_string($submitted) && $submitted !== '' && hash_equals(self::token(), $submitted);
    }
}
