<?php
declare(strict_types=1);

/* POST config/api/claims-decision.php */

require_once __DIR__ . '/_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    apiJson(405, ['message' => 'Method not allowed']);
}

$user = apiUser(['adjuster']);
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    apiJson(400, ['message' => 'Invalid request body']);
}

$claimId = (int)($input['claim_id'] ?? 0);
$decision = (string)($input['decision'] ?? '');
$amount = isset($input['amount']) && $input['amount'] !== '' && $input['amount'] !== null ? (float)$input['amount'] : null;
$notes = trim((string)($input['review_notes'] ?? '')) ?: null;

if (!in_array($decision, ['approve', 'reject', 'request_docs'], true)) {
    apiJson(422, ['message' => 'Choose approve, reject, or ask for documents.']);
}

try {
    apiCanSeeClaim($conn, $user, $claimId); // 404 / 403 before we take any lock
    $result = inTransaction($conn, static fn() => recordDecision($conn, $user, $claimId, $decision, $amount, $notes, is_array($input['documents'] ?? null) ? $input['documents'] : []));
    apiJson(200, ['ok' => true] + $result);
} catch (PDOException $e) {
    error_log($e->getMessage());
    apiJson(500, ['message' => 'Could not record the decision right now.']);
}

/**
 * The transactional part of claims-decision.php (runs inside inTransaction()).
 * Re-reads the claim under a row lock so every check below sees the current
 * status, not the one from before another request got in.
 *
 * @return array{status:string, claim_number:string}
 */
function recordDecision(PDO $conn, array $user, int $claimId, string $decision, ?float $amount, ?string $notes, array $documents = []): array
{
    // 1. Lock the claim and check whether its current state permits this decision.
    $claim = claimLock($conn, $claimId);
    $closure = ['approve' => 'approved', 'reject' => 'rejected', 'request_docs' => 'pending_docs'];
    $newStatus = $closure[$decision];

    $current = (string)$claim['status'];
    $refusal = ($current === 'pending_docs' && $decision === 'request_docs')
        ? 'This claim is already waiting for documents from the claimant.'
        : 'This claim is ' . claimStatusWords($current) . ', so a decision cannot be recorded on it.';
    claimAssertMove($current, $newStatus, $refusal);

    if ($decision === 'request_docs') {
        $structured = (bool)$documents;
        $documents = requestedDocumentChoices($documents, $notes);
        if ($structured) $notes = implode("\n", array_column($documents, 'document_name')) . ($notes ? "\n\n" . $notes : '');
    }
    if (in_array($decision, ['reject', 'request_docs'], true)) {
        $notes = workflowText($notes, $decision === 'reject' ? 'a clear rejection reason' : 'the exact documents required, one per line');
    }

    // 2. Approval needs a positive amount, a covered date/peril and a valid limit.
    if ($decision === 'approve') {
        assertPolicyCoverage($conn, $claim, $amount ?? 0);
    }

    // 3. Update the claim and record who decided, what changed and why.
    $conn->prepare(
        'UPDATE claims SET status = :status,
            approved_amount = IF(:approve = 1, :amount, approved_amount),
            rejection_reason = IF(:reject = 1, :reason, rejection_reason),
            reviewed_at = NOW(),
            resolved_at = IF(:resolved = 1, NOW(), resolved_at),
            updated_at = NOW()
         WHERE id = :id'
    )->execute([
        'status' => $newStatus,
        'approve' => $decision === 'approve' ? 1 : 0,
        'amount' => $amount,
        'reject' => $decision === 'reject' ? 1 : 0,
        'reason' => $notes,
        'resolved' => $decision === 'reject' ? 1 : 0,
        'id' => $claimId,
    ]);

    $conn->prepare('INSERT INTO claim_status_history (claim_id, changed_by, from_status, to_status, notes)
                    VALUES (:cid, :by, :from, :to, :notes)')
         ->execute(['cid' => $claimId, 'by' => (int)$user['id'], 'from' => $claim['status'], 'to' => $newStatus, 'notes' => $notes]);
    $conn->prepare('INSERT INTO adjuster_reviews (claim_id, adjuster_id, review_notes, recommended_amount, decision, decision_reason)
                    VALUES (:cid, :aid, :notes, :amount, :decision, :reason)')
         ->execute([
            'cid' => $claimId, 'aid' => (int)$user['id'], 'notes' => $notes,
            'amount' => $decision === 'approve' ? $amount : null,
            'decision' => $decision, 'reason' => $notes,
         ]);
    audit($conn, $user, 'claim.decision', 'claim', $claimId, ['status' => $claim['status']], ['status' => $newStatus, 'decision' => $decision, 'amount' => $amount]);

    // 4. Approval creates a pending payout record; every outcome notifies the owner.
    // This records a proposed settlement. It does not send money to a provider.
    $claimant = (int)$claim['user_id'];
    if ($decision === 'approve') {
        $conn->prepare('INSERT INTO claim_settlements (claim_id) VALUES (?)')->execute([$claimId]);
        $claimantRow = $conn->prepare('SELECT full_name, phone FROM users WHERE id = :id');
        $claimantRow->execute(['id' => $claimant]);
        $who = $claimantRow->fetch();
        $ref = 'PAY-' . date('Ymd') . '-' . str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT);
        $conn->prepare(
            'INSERT INTO payouts (claim_id, user_id, approved_by, amount, payment_method, account_name, account_number, reference_number, status, created_at)
             VALUES (:cid, :uid, :by, :amount, "mobile_money", :acc, :num, :ref, "pending", NOW())'
        )->execute([
            'cid' => $claimId, 'uid' => $claimant, 'by' => (int)$user['id'], 'amount' => $amount,
            'acc' => $who['full_name'] ?? null, 'num' => $who['phone'] ?? null, 'ref' => $ref,
        ]);
        notify($conn, $claimant, $claimId, 'payout',
            'Claim approved',
            'AVIC approved ' . number_format($amount) . ' UGX on claim ' . $claim['claim_number'] . '. Settlement is being prepared.');
    } elseif ($decision === 'reject') {
        notify($conn, $claimant, $claimId, 'status_changed',
            'Claim rejected',
            $claim['claim_number'] . ' was rejected. ' . ($notes ?? 'See your claim for the reason.'));
    } else {
        $conn->prepare('INSERT INTO claim_document_requests (claim_id, requested_by, details) VALUES (?, ?, ?)')->execute([$claimId, $user['id'], $notes]);
        $requestId = (int)$conn->lastInsertId();
        foreach ($documents as $document) {
            $conn->prepare('INSERT INTO requested_documents (request_id, document_type, document_name) VALUES (?, ?, ?)')->execute([$requestId, $document['document_type'], $document['document_name']]);
        }
        notify($conn, $claimant, $claimId, 'status_changed',
            'More documents needed',
            $claim['claim_number'] . ' — documents required: ' . $notes);
    }

    return ['status' => $newStatus, 'claim_number' => (string)$claim['claim_number']];
}
