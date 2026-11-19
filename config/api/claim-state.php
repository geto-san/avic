<?php
declare(strict_types=1);

/* The one place that says how a claim's status may change. */

require_once dirname(__DIR__, 2) . '/src/Claims/Lifecycle.php';

const CLAIM_TRANSITIONS = \Avic\Claims\Lifecycle::TRANSITIONS;
const CLAIM_STATUS_WORDS = \Avic\Claims\Lifecycle::LABELS;

/** Raised inside inTransaction() to roll back and answer with this HTTP status. */
final class ApiAbort extends RuntimeException
{
}

function claimCanMove(string $from, string $to): bool
{
    // Look up the allowed destinations for this starting state, then test membership.
    return \Avic\Claims\Lifecycle::permits($from, $to);
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
    // $fn is the operation supplied by the endpoint (for example recordDecision).
    // Commit means keep all its database writes; rollback means discard them.
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
        if ($e instanceof PDOException && ($e->getCode() === '40001' || in_array($e->errorInfo[1] ?? 0, [1020, 1205, 1213], true))) {
            apiJson(409, ['message' => 'Another request changed this record. Reload and try again.']);
        }
        throw $e;
    }
}
