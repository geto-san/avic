<?php
declare(strict_types=1);

namespace Avic\Core;

final class Flash
{
    public static function add(string $type, string $message): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return list<array{type:string,message:string}> */
    public static function pull(): array
    {
        $all = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $all;
    }
}
