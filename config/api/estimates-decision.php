<?php
declare(strict_types=1);

/**
 * POST config/api/estimates-decision.php
 *
 * Adjuster acts on a garage quote: approve it (work starts) or send it
 * back with notes for a revision. The garage is notified either way and
 * the work order moves to 'closed' or 'revision_requested'.
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

$estimateId = (int)($input['estimate_id'] ?? 0);
$decision = (string)($input['decision'] ?? '');
$notes = trim((string)($input['notes'] ?? '')) ?: null;

if (!in_array($decision, ['approve', 'send_back'], true)) {
    api_json(422, ['message' => 'Choose approve or send back.']);
}

try {
    $est = $conn->prepare('SELECT * FROM garage_estimates WHERE id = :id');
    $est->execute(['id' => $estimateId]);
    $estimate = $est->fetch();
    if (!$estimate) {
        api_json(404, ['message' => 'Estimate not found.']);
    }
    $claim = api_can_see_claim($conn, $user, (int)$estimate['claim_id']);

    if ($estimate['status'] !== 'pending') {
        api_json(409, ['message' => 'That estimate was already acted on.']);
    }

    $newStatus = $decision === 'approve' ? 'approved' : 'rejected';
    $conn->prepare('UPDATE garage_estimates SET status = :status, adjuster_notes = :notes, updated_at = NOW() WHERE id = :id')
         ->execute(['status' => $newStatus, 'notes' => $notes, 'id' => $estimateId]);

    $woStatus = $decision === 'approve' ? 'closed' : 'revision_requested';
    $conn->prepare('UPDATE work_orders SET status = :status WHERE claim_id = :cid')
         ->execute(['status' => $woStatus, 'cid' => (int)$estimate['claim_id']]);

    audit($conn, $user, 'estimate.decision', 'estimate', $estimateId,
        ['status' => $estimate['status']], ['status' => $newStatus, 'notes' => $notes]);

    $garageId = (int)($estimate['garage_user_id'] ?? 0);
    if ($garageId) {
        if ($decision === 'approve') {
            notify($conn, $garageId, (int)$estimate['claim_id'], 'estimate',
                'Estimate approved — work can start',
                'Your quote on ' . $claim['claim_number'] . ' was approved.');
        } else {
            notify($conn, $garageId, (int)$estimate['claim_id'], 'estimate',
                'Revision requested',
                'The adjuster asked for changes on ' . $claim['claim_number'] . ': ' . ($notes ?? '') );
        }
    }
    notify($conn, (int)$claim['user_id'], (int)$estimate['claim_id'], 'estimate',
        $decision === 'approve' ? 'Estimate approved by adjuster' : 'Quote sent back to the garage',
        'UGX ' . number_format((float)$estimate['total_estimate']) . ' quote on ' . $claim['claim_number'] .
            ($decision === 'approve' ? ' was approved.' : ' was sent back for revision.'));

    api_json(200, ['ok' => true, 'status' => $newStatus, 'claim_number' => $claim['claim_number']]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    api_json(500, ['message' => 'Could not record the estimate decision right now.']);
}