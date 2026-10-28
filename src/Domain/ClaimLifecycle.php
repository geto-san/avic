<?php
declare(strict_types=1);

namespace Avic\Domain;

/**
 * The one table that says how a claim's status may change.
 *
 *   open ──> investigating ──> settled ──> closed ──(reopen)──> open
 *     └──────────┴──────────────────────────^
 *
 * ClaimService::transition() asks this table first, inside a transaction, after locking
 * the claim row, and then applies the guards for the target status.
 */
final class ClaimLifecycle
{
    public const TRANSITIONS = [
        'open' => ['investigating', 'closed'],
        'investigating' => ['settled', 'closed'],
        'settled' => ['closed'],
        'closed' => ['open'],
    ];

    /** Button text for moving INTO each status. */
    public const ACTIONS = [
        'investigating' => 'Start investigation',
        'settled' => 'Mark as settled',
        'closed' => 'Close claim',
    ];

    public static function canMove(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /** @return list<string> */
    public static function targets(string $from): array
    {
        return self::TRANSITIONS[$from] ?? [];
    }

    public static function actionLabel(string $from, string $to): string
    {
        return $from === 'closed' && $to === 'open' ? 'Reopen claim' : (self::ACTIONS[$to] ?? $to);
    }

    /** Which outcomes are allowed when closing from $from. */
    public static function outcomesFor(string $from): array
    {
        return $from === 'settled' ? ['paid'] : ['denied', 'withdrawn'];
    }
}
