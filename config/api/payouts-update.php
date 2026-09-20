<?php
declare(strict_types=1);

/**
 * POST config/api/payouts-update.php
 *
 * Move a payout forward on its lifecycle: pending -> processing -> completed.
 * Only the adjuster who approved the claim (approved_by) may do this, and only
 * one step at a time — you cannot skip to completed or move a payout backwards.
 * Reaching "completed" also lands the underlying claim at "paid" so the case
 * leaves the pending sink for good. Every transition is written to the status
 * history, the audit log and the claimant's notification feed.
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

$payoutId = (int)($input['payout_id'] ?? 0);
$action = (string)($input['action'] ?? '');

if (!$payoutId) {
    api_json(422, ['message' => 'A payout reference is required.']);
}
if (!in_array($action, ['mark_processing', 'mark_paid'], true)) {
    api_json(422, ['message' => 'Unknown payout action.']);
}

$sel = $conn->prepare(
    'SELECT p.*, c.claim_number FROM payouts p JOIN claims c ON c.id = p.claim_id WHERE p.id = :id LIMIT 1'
);
$sel->execute(['id' => $payoutId]);
$payout = $sel->fetch();
if (!$payout) {
    api_json(404, ['message' => 'That payout does not exist.']);
}

/* fail closed: only the adjuster who approved can advance the payout */
if ((int)$payout['approved_by'] !== (int)$user['id']) {
    api_json(403, ['message' => 'Only the adjuster who approved this claim may advance its payout.']);
}

/* single-step forward state machine only */
$from = $payout['status'];
$targets = [
    'mark_processing' => ['pending' => 'processing'],
    'mark_paid'       => ['processing' => 'completed'],
];
if (!isset($targets[$action][$from])) {
    api_json(409, [
        'message' => $from === 'pending'
            ? 'Mark it as processing first.'
            : 'This payout has already been ' . ($from === 'completed' ? 'completed.' : 'advanced to ' . $from . '.'),
    ]);
}
$to = $targets[$action][$from];

$conn->prepare(
    'UPDATE payouts SET status = :to, processed_at = NOW(), updated_at = NOW() WHERE id = :id'
)->execute(['to' => $to, 'id' => $payoutId]);

$who = $payout['claim_number'] . ' to ' . (string)$payout['user_id'];
$conn->prepare(
    'INSERT INTO claim_status_history (claim_id, changed_by, from_status, to_status, notes)
     VALUES (:cid, :by, :from, :to, :notes)'
)->execute([
    'cid' => (int)$payout['claim_id'],
    'by'  => (int)$user['id'],
    'from' => $from,
    'to'   => $to,
    'notes' => 'Payout ' . $payout['reference_number'] . ': ' . $from . ' -> ' . $to,
]);

audit($conn, $user, 'payout.' . $to, 'payout', $payoutId,
      ['status' => $from], ['status' => $to, 'processed_at' => date('Y-m-d H:i:s')]);

$titles = [
    'processing' => 'Payout in progress',
    'completed'  => 'Payout completed',
];
$messages = [
    'processing' => 'AVIC has started processing the ' . number_format((float)$payout['amount']) . ' UGX payout for ' . $payout['claim_number'] . '. It usually lands within 2 working days.',
    'completed'  => 'The ' . number_format((float)$payout['amount']) . ' UGX payout for ' . $payout['claim_number'] . ' has been completed. Check ' . $payout['payment_method'] . ' for reference ' . $payout['reference_number'] . '.',
];
notify($conn, (int)$payout['user_id'], (int)$payout['claim_id'], 'payout',
       $titles[$to], $messages[$to]);

if ($to === 'completed') {
    $conn->prepare(
        'UPDATE claims SET status = "paid", updated_at = NOW() WHERE id = :id'
    )->execute(['id' => (int)$payout['claim_id']]);
}

api_json(200, ['ok' => true, 'status' => $to, 'processed_at' => date('Y-m-d H:i:s')]);
