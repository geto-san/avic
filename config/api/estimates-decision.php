<?php
declare(strict_types=1);

/* POST config/api/estimates-decision.php */

require_once __DIR__ . '/_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    apiJson(405, ['message' => 'Method not allowed']);
}

$user = apiUser(['adjuster']);
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    apiJson(400, ['message' => 'Invalid request body']);
}

$estimateId = (int)($input['estimate_id'] ?? 0);
$decision = (string)($input['decision'] ?? '');
$notes = trim((string)($input['notes'] ?? '')) ?: null;

if (!in_array($decision, ['approve', 'send_back'], true)) {
    apiJson(422, ['message' => 'Choose approve or send back.']);
}

try {
    $lookup = $conn->prepare('SELECT claim_id FROM garage_estimates WHERE id = ?');
    $lookup->execute([$estimateId]);
    $claimId = (int)$lookup->fetchColumn();
    if (!$claimId) apiJson(404, ['message' => 'Estimate not found.']);
    $result = inTransaction($conn, static function () use ($conn, $user, $estimateId, $decision, $notes, $claimId) {
        $claim = claimLock($conn, $claimId);
        apiCanSeeClaim($conn, $user, $claimId);
        if (in_array($claim['status'], ['paid', 'rejected', 'closed'], true)) {
            throw new ApiAbort('Quote decisions are closed for this claim.', 409);
        }
        $est = $conn->prepare('SELECT * FROM garage_estimates WHERE id = :id FOR UPDATE');
        $est->execute(['id' => $estimateId]);
        $estimate = $est->fetch();
        if (!$estimate) {
            throw new ApiAbort('Estimate not found.', 404);
        }

        if ($estimate['status'] !== 'pending') {
            throw new ApiAbort('That estimate was already acted on.', 409);
        }

        $newStatus = $decision === 'approve' ? 'approved' : 'rejected';
        $conn->prepare('UPDATE garage_estimates SET status = :status, adjuster_notes = :notes, updated_at = NOW() WHERE id = :id')
             ->execute(['status' => $newStatus, 'notes' => $notes, 'id' => $estimateId]);

        $workOrder = $conn->prepare('SELECT * FROM work_orders WHERE claim_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE');
        $workOrder->execute([$claimId]);
        $order = $workOrder->fetch();
        if (!$order || ($estimate['garage_user_id'] !== null && (int)$order['garage_user_id'] !== (int)$estimate['garage_user_id'])) {
            throw new ApiAbort('This quote no longer belongs to the assigned garage.', 409);
        }
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

        return ['ok' => true, 'status' => $newStatus, 'claim_number' => $claim['claim_number']];
    });
    apiJson(200, $result);
} catch (PDOException $e) {
    error_log($e->getMessage());
    apiJson(500, ['message' => 'Could not record the estimate decision right now.']);
}
