<?php
declare(strict_types=1);

namespace Avic\Claims;

final class Lifecycle
{
    public const TRANSITIONS = [
        'draft'        => ['draft', 'submitted'],              // save, or submit
        'submitted'    => ['under_review', 'approved', 'rejected', 'pending_docs'],
        'under_review' => ['approved', 'rejected', 'pending_docs'],
        'pending_docs' => ['submitted', 'approved', 'rejected'], // claimant answers, or adjuster decides
        'approved'     => ['paid'],
        'paid'         => ['closed'],
        'rejected'     => ['closed'],
        'closed'       => [],
    ];

    public const LABELS = [
        'draft'        => 'a draft',
        'submitted'    => 'already submitted',
        'under_review' => 'under review',
        'pending_docs' => 'waiting for documents',
        'approved'     => 'approved',
        'rejected'     => 'rejected',
        'paid'         => 'paid',
        'closed'       => 'closed',
    ];

    public static function permits(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }
}
