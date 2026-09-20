<?php
declare(strict_types=1);

/**
 * POST config/api/claims-decision.php
 *
 * Adjuster decision on a claim: approve, reject, or request documents.
 * Approving opens a payout row; rejecting records the reason; asking for
 * documents parks the claim back on the claimant. Everything lands in the
 * status history, the adjuster_reviews table, the audit log and the
 * claimant's notification feed.
 */

require_once __DIR__ . '/_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    api_json(405, ['message' => 'Method not allowed']);
}

$user = api_user(['adjuster']);
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    api_json(400, ['message' => 'Invalid request body']);
}

$claimId = (int)($input['claim_id'] ?? 0);
$decision = (string)($input['decision'] ?? '');
$amount = isset($input['amount']) && $input['amount'] !== '' && $input['amount'] !== null ? (float)$input['amount'] : null;
$notes = trim((string)($input['review_notes'] ?? '')) ?: null;

if (!in_array($decision, ['approve', 'reject', 'request_docs'], true)) {
    api_json(422, ['message' => 'Choose approve, reject, or ask for documents.']);
}

try {
    $claim = api_can_see_claim($conn, $user, $claimId);
    $closure = ['approve' => 'approved', 'reject' => 'rejected', 'request_docs' => 'pending_docs'];
    $newStatus = $closure[$decision];

    if ($decision === 'approve') {
        $policy = $conn->prepare('SELECT * FROM policies WHERE id = :id');
        $policy->execute(['id' => $claim['policy_id']]);
        $pol = $policy->fetch();
        if (!$pol) {
            /* fail closed: never approve against a policy that is not on file */
            api_json(422, ['message' => 'There is no policy on file for this claim, so it cannot be approved.']);
        }
        if ($amount === null || $amount <= 0) {
            api_json(422, ['message' => 'Set an amount to approve.']);
        }
        $inPeriod = ($claim['incident_date'] >= $pol['start_date']) && ($claim['incident_date'] <= $pol['end_date']);
        if (!$inPeriod) {
            api_json(422, [
                'message' => 'The incident falls outside the policy period (' . substr($pol['start_date'], 0, 10) .
                             ' to ' . substr($pol['end_date'], 0, 10) . '), so there is no cover.',
            ]);
        }
        if (in_array($pol['coverage_type'], ['basic'], true) &&
            in_array($claim['claim_type'], ['fire', 'theft', 'natural_disaster'], true)) {
            api_json(422, [
                'message' => 'This peril is not covered under the basic tier of the policy.',
            ]);
        }
        if ($amount > (float)$pol['coverage_limit']) {
            api_json(422, ['message' => 'The approved amount exceeds the cover limit on this policy.']);
        }
    }

    $stmt = $conn->prepare(
        'UPDATE claims SET status = :status,
            approved_amount = IF(:approve = 1, :amount, approved_amount),
            rejection_reason = IF(:reject = 1, :reason, rejection_reason),
            reviewed_at = NOW(),
            resolved_at = IF(:resolved = 1, NOW(), reviewed_at),
            updated_at = NOW()
         WHERE id = :id'
    );
    $stmt->execute([
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

    $claimant = (int)$claim['user_id'];
    if ($decision === 'approve') {
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
        notify($conn, $claimant, $claimId, 'status_changed',
            'More documents needed',
            $claim['claim_number'] . ' needs more documents before it can be assessed.');
    }

    api_json(200, ['ok' => true, 'status' => $newStatus, 'claim_number' => $claim['claim_number']]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    api_json(500, ['message' => 'Could not record the decision right now.']);
}