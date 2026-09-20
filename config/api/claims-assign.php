<?php
declare(strict_types=1);

/**
 * POST config/api/claims-assign.php
 *
 * Assign a claim to a garage so it can quote the repair. Creates (or
 * reopens) a work order, alerts the workshop, and records the action.
 * One open work order per claim — an already-assigned claim cannot be
 * sent to a second garage.
 */

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
$garageId = (int)($input['garage_id'] ?? 0);

try {
    $claim = apiCanSeeClaim($conn, $user, $claimId);

    $garage = $conn->prepare('SELECT id, full_name, garage_address, phone FROM users WHERE id = :id AND role = "garage" AND status = "active" LIMIT 1');
    $garage->execute(['id' => $garageId]);
    $g = $garage->fetch();
    if (!$g) {
        apiJson(422, ['message' => 'Choose an active garage.']);
    }

    /* an open work order already exists? only closed ones may be redone */
    $existing = $conn->prepare('SELECT * FROM work_orders WHERE claim_id = :cid ORDER BY id DESC LIMIT 1');
    $existing->execute(['cid' => $claimId]);
    $wo = $existing->fetch();

    if ($wo && $wo['status'] !== 'closed') {
        apiJson(409, ['message' => 'This claim is already with a garage.']);
    }

    $due = date('Y-m-d', strtotime('+7 days'));
    if ($wo) {
        $conn->prepare('UPDATE work_orders SET status = "open", garage_user_id = :gid, assigned_by = :by, assigned_at = NOW(), due = :due WHERE id = :wid')
             ->execute(['gid' => $garageId, 'by' => (int)$user['id'], 'due' => $due, 'wid' => (int)$wo['id']]);
        $orderId = (int)$wo['id'];
    } else {
        $conn->prepare('INSERT INTO work_orders (claim_id, garage_user_id, assigned_by, assigned_at, due, status)
                        VALUES (:cid, :gid, :by, NOW(), :due, "open")')
             ->execute(['cid' => $claimId, 'gid' => $garageId, 'by' => (int)$user['id'], 'due' => $due]);
        $orderId = (int)$conn->lastInsertId();
    }

    audit($conn, $user, 'claim.assign_garage', 'claim', $claimId,
        ['garage_id' => $g['garage_id'] ?? $g['id']],
        ['garage_id' => $garageId, 'garage' => $g['full_name']]);

    notify($conn, (int)$g['id'], $claimId, 'work_order',
        'Work order assigned',
        'You have been assigned ' . $claim['claim_number'] . '. Quote due ' . date('d M', strtotime($due)) . '.');
    notify($conn, (int)$claim['user_id'], $claimId, 'status_changed',
        'Garage assigned',
        'A garage (' . $g['full_name'] . ') will inspect ' . $claim['claim_number'] . ' and quote the repair.');

    apiJson(200, ['ok' => true, 'work_order_id' => $orderId, 'claim_number' => $claim['claim_number']]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    apiJson(500, ['message' => 'Could not assign the garage right now.']);
}
