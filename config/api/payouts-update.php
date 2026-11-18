<?php
declare(strict_types=1);

/* POST config/api/payouts-update.php */

require_once __DIR__ . '/_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    apiJson(405, ['message' => 'Method not allowed']);
}

$user = apiUser(['adjuster']);
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    apiJson(400, ['message' => 'Invalid request body']);
}

$payoutId = (int)($input['payout_id'] ?? 0);
$action = (string)($input['action'] ?? '');

if (!$payoutId) {
    apiJson(422, ['message' => 'A payout reference is required.']);
}
if (!in_array($action, ['mark_processing', 'mark_paid'], true)) {
    apiJson(422, ['message' => 'Unknown payout action.']);
}

try {
    $result = inTransaction($conn, static fn() => advancePayout($conn, $user, $payoutId, $action, $input));
    apiJson(200, ['ok' => true] + $result);
} catch (PDOException $e) {
    error_log($e->getMessage());
    apiJson(500, ['message' => 'Could not update the payout right now.']);
}

/**
 * The transactional part of payouts-update.php (runs inside inTransaction()).
 *
 * @return array{status:string, processed_at:string}
 */
function advancePayout(PDO $conn, array $user, int $payoutId, string $action, array $input): array
{
    // Read and lock before deciding: another request may have advanced this payout.
    // Use the same claim -> policy lock order as approval and submission.
    $lookup = $conn->prepare('SELECT claim_id FROM payouts WHERE id = ?');
    $lookup->execute([$payoutId]);
    $claimId = (int)$lookup->fetchColumn();
    if (!$claimId) throw new ApiAbort('That payout does not exist.', 404);
    $claim = claimLock($conn, $claimId);
    $sel = $conn->prepare('SELECT * FROM payouts WHERE id = ? FOR UPDATE');
    $sel->execute([$payoutId]);
    $payout = $sel->fetch();
    $payout['claim_status'] = $claim['status'];
    $payout['claim_number'] = $claim['claim_number'];

    /* fail closed: only the adjuster who approved can advance the payout */
    if ((int)$payout['approved_by'] !== (int)$user['id']) {
        throw new ApiAbort('Only the adjuster who approved this claim may advance its payout.', 403);
    }

    /* the claim must still be approved; "paid" is the only move out of it */
    claimAssertMove((string)$payout['claim_status'], 'paid',
        'This claim is ' . claimStatusWords((string)$payout['claim_status']) . ', so its payout cannot move forward.');

    /* Each action has one permitted starting state and one destination.
       These are payout states, separate from the claim's status. */
    $from = $payout['status'];
    $targets = [
        'mark_processing' => ['pending' => 'processing'],
        'mark_paid'       => ['processing' => 'completed'],
    ];
    if (!isset($targets[$action][$from])) {
        if ($from === 'pending') {
            $msg = 'Mark it as processing first.';
        } elseif ($from === 'completed') {
            $msg = 'This payout has already been completed.';
        } else {
            $msg = 'This payout has already been advanced to ' . $from . '.';
        }
        throw new ApiAbort($msg, 409);
    }
    $to = $targets[$action][$from];

    assertPolicyCoverage($conn, $claim, (float)$payout['amount']);

    assertNoOpenComplaint($conn, $claimId);
    $offer = $conn->prepare('SELECT status FROM claim_settlements WHERE claim_id = ?');
    $offer->execute([$claimId]);
    if ($offer->fetchColumn() !== 'accepted') throw new ApiAbort('The claimant must accept the settlement and confirm payment details first.', 409);
    if ($to === 'completed') {
        $reference = workflowText($input['external_reference'] ?? '', 'the external payment transaction reference', 120);
        $note = workflowText($input['verification_note'] ?? '', 'how the payment was verified');
        if (($input['payment_confirmed'] ?? false) !== true) throw new ApiAbort('Confirm that payment was checked against the external payment record.', 422);
        $check = $conn->prepare('SELECT payout_id FROM payout_evidence WHERE external_reference = ? OR payout_id = ?');
        $check->execute([$reference, $payoutId]);
        if ($check->fetchColumn()) throw new ApiAbort('This payment reference or evidence record has already been used.', 409);
        try {
            $conn->prepare('INSERT INTO payout_evidence (payout_id, external_reference, verification_note, confirmed_by) VALUES (?, ?, ?, ?)')
                ->execute([$payoutId, $reference, $note, $user['id']]);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') throw new ApiAbort('That transaction reference has already been recorded.', 409);
            throw $error;
        }
    }

    /* payouts has no updated_at column (the old query named one and failed on a
       database built from schema.php); processed_at is the timestamp we keep */
    $conn->prepare('UPDATE payouts SET status = :to, processed_at = NOW() WHERE id = :id')
         ->execute(['to' => $to, 'id' => $payoutId]);

    $conn->prepare(
        'INSERT INTO claim_status_history (claim_id, changed_by, from_status, to_status, notes)
         VALUES (:cid, :by, :from, :to, :notes)'
    )->execute([
        'cid' => (int)$payout['claim_id'],
        'by'  => (int)$user['id'],
        'from' => $claim['status'],
        'to'   => $to === 'completed' ? 'paid' : $claim['status'],
        'notes' => 'Payout ' . $payout['reference_number'] . ': ' . $from . ' -> ' . $to,
    ]);

    audit($conn, $user, 'payout.' . $to, 'payout', $payoutId,
          ['status' => $from], ['status' => $to, 'processed_at' => date('Y-m-d H:i:s')]);

    $titles = [
        'processing' => 'Payout in progress',
        'completed'  => 'Payout completed',
    ];
    $messages = [
        'processing' => 'AVIC has marked the ' . number_format((float)$payout['amount']) . ' UGX payout for ' . $payout['claim_number'] . ' as processing.',
        'completed'  => 'The ' . number_format((float)$payout['amount']) . ' UGX payout for ' . $payout['claim_number'] . ' has been completed. Check ' . $payout['payment_method'] . ' for reference ' . $payout['reference_number'] . '.',
    ];
    notify($conn, (int)$payout['user_id'], (int)$payout['claim_id'], 'payout',
           $titles[$to], $messages[$to]);

    if ($to === 'completed') {
        $conn->prepare('UPDATE claims SET status = "paid", resolved_at = NOW(), updated_at = NOW() WHERE id = :id')
             ->execute(['id' => (int)$payout['claim_id']]);
    }

    return ['status' => $to, 'processed_at' => date('Y-m-d H:i:s')];
}
