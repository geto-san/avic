<?php
declare(strict_types=1);

/**
 * The one place that says how a claim's status may change.
 *
 * Every endpoint that moves a claim (claimant submit/edit, adjuster decision,
 * payout completion) asks this file first, inside a transaction, after taking
 * a row lock on the claim. That makes two things true that were not before:
 *
 *   - a claim can only follow the lifecycle below (an approved or paid claim
 *     can no longer be pushed back to "draft" or "submitted");
 *   - two requests racing for the same claim cannot both win (the second one
 *     waits on the lock, then sees the new status and is refused with a 409).
 *
 *   draft ──> submitted ──> under_review ──> approved ──> paid ──> closed
 *                │  ▲            │              │
 *                │  └─ pending_docs <──┘         └ (payout completes)
 *                └──> rejected ──> closed
 *
 * "under_review" is part of the lifecycle but nothing sets it yet (Phase 2).
 */

const CLAIM_TRANSITIONS = [
    'draft'        => ['draft', 'submitted'],              // save, or submit
    'submitted'    => ['under_review', 'approved', 'rejected', 'pending_docs'],
    'under_review' => ['approved', 'rejected', 'pending_docs'],
    'pending_docs' => ['submitted', 'approved', 'rejected'], // claimant answers, or adjuster decides
    'approved'     => ['paid'],
    'paid'         => ['closed'],
    'rejected'     => ['closed'],
    'closed'       => [],
];

const CLAIM_STATUS_WORDS = [
    'draft'        => 'a draft',
    'submitted'    => 'already submitted',
    'under_review' => 'under review',
    'pending_docs' => 'waiting for documents',
    'approved'     => 'approved',
    'rejected'     => 'rejected',
    'paid'         => 'paid',
    'closed'       => 'closed',
];

/** Raised inside inTransaction() to roll back and answer with this HTTP status. */
final class ApiAbort extends RuntimeException
{
}

function claimCanMove(string $from, string $to): bool
{
    return in_array($to, CLAIM_TRANSITIONS[$from] ?? [], true);
}

/** "approved", "waiting for documents", ... for use in "This claim is ___." */
function claimStatusWords(string $status): string
{
    return CLAIM_STATUS_WORDS[$status] ?? $status;
}

/** Abort with a 409 unless the lifecycle allows $from -> $to. */
function claimAssertMove(string $from, string $to, ?string $message = null): void
{
    if (!claimCanMove($from, $to)) {
        throw new ApiAbort($message ?? ('This claim is ' . claimStatusWords($from) . '.'), 409);
    }
}

/**
 * Re-read the claim and hold its row lock until the transaction ends. Always
 * decide on THIS row, not on one read earlier — it is the current truth.
 * Must be called inside inTransaction().
 */
function claimLock(PDO $conn, int $claimId): array
{
    $stmt = $conn->prepare('SELECT * FROM claims WHERE id = :id FOR UPDATE');
    $stmt->execute(['id' => $claimId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new ApiAbort('Claim not found.', 404);
    }
    return $row;
}

/**
 * Run $fn atomically. Any ApiAbort inside rolls everything back and is sent to
 * the client as JSON; any other exception rolls back and is re-thrown for the
 * endpoint's own catch (PDOException -> generic 500, never raw DB text).
 *
 * @return mixed whatever $fn returns
 */
function inTransaction(PDO $conn, callable $fn)
{
    $conn->beginTransaction();
    try {
        $result = $fn();
        $conn->commit();
        return $result;
    } catch (ApiAbort $e) {
        $conn->rollBack();
        apiJson($e->getCode(), ['message' => $e->getMessage()]);
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
}
