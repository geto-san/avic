<?php
declare(strict_types=1);

/**
 * POST config/api/estimates.php
 *
 * A garage submits (or revises) a quote for a claim it holds a work order
 * for. Revising after a send-back reopens the quote; the adjuster and the
 * claimant are both told the garage has responded.
 */

require_once __DIR__ . '/_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    api_json(405, ['message' => 'Method not allowed']);
}

$user = api_user(['garage']);
$gid  = (int)$user['id'];
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    api_json(400, ['message' => 'Invalid request body']);
}

$claimId = (int)($input['claim_id'] ?? 0);
$parts   = (float)($input['parts_cost'] ?? 0);
$labor   = (float)($input['labor_cost'] ?? 0);
$other   = (float)($input['other_cost'] ?? 0);
$days    = (int)($input['repair_days'] ?? 0);
$total   = $parts + $labor + $other;

try {
    $woQ = $conn->prepare('SELECT * FROM work_orders WHERE claim_id = :cid AND garage_user_id = :gid ORDER BY id DESC LIMIT 1');
    $woQ->execute(['cid' => $claimId, 'gid' => $gid]);
    $wo = $woQ->fetch();
    if (!$wo) {
        api_json(403, ['message' => 'Your workshop does not hold a work order for that claim.']);
    }
    if ($wo['status'] === 'closed') {
        api_json(409, ['message' => 'That work order is closed.']);
    }
    if ($total <= 0) {
        api_json(422, ['message' => 'The quote total cannot be zero.']);
    }
    if ($days < 1 || $days > 120) {
        api_json(422, ['message' => 'Set a realistic repair time in working days.']);
    }

    $claim = api_can_see_claim($conn, $user, $claimId); // garage allowed via work order above; also asserts claim exists

    $garage = $conn->prepare('SELECT full_name, garage_address, phone FROM users WHERE id = :id');
    $garage->execute(['id' => $gid]);
    $g = $garage->fetch();

    $existing = $conn->prepare('SELECT * FROM garage_estimates WHERE claim_id = :cid AND (garage_user_id = :gid OR garage_user_id IS NULL) ORDER BY id DESC LIMIT 1');
    $existing->execute(['cid' => $claimId, 'gid' => $gid]);
    $est = $existing->fetch();

    if ($est) {
        $conn->prepare(
            'UPDATE garage_estimates SET parts_cost = :p, labor_cost = :l, other_cost = :o, total_estimate = :t,
                    repair_days = :d, status = "pending", adjuster_notes = NULL, garage_name = :gn,
                    garage_address = :ga, garage_phone = :gp, updated_at = NOW()
             WHERE id = :id'
        )->execute([
            'p' => $parts, 'l' => $labor, 'o' => $other, 't' => $total, 'd' => $days,
            'gn' => $g['full_name'], 'ga' => $g['garage_address'], 'gp' => $g['phone'], 'id' => (int)$est['id'],
        ]);
        $estimateId = (int)$est['id'];
    } else {
        $conn->prepare(
            'INSERT INTO garage_estimates (claim_id, garage_user_id, garage_name, garage_address, garage_phone,
                                           parts_cost, labor_cost, other_cost, total_estimate, repair_days, status)
             VALUES (:cid, :gid, :gn, :ga, :gp, :p, :l, :o, :t, :d, "pending")'
        )->execute([
            'cid' => $claimId, 'gid' => $gid, 'gn' => $g['full_name'], 'ga' => $g['garage_address'], 'gp' => $g['phone'],
            'p' => $parts, 'l' => $labor, 'o' => $other, 't' => $total, 'd' => $days,
        ]);
        $estimateId = (int)$conn->lastInsertId();
    }

    $conn->prepare('UPDATE work_orders SET status = "quoted" WHERE id = :id')->execute(['id' => (int)$wo['id']]);
    audit($conn, $user, 'estimate.submitted', 'estimate', $estimateId, null, ['claim_id' => $claimId, 'total' => $total]);

    if ($claim['adjuster_id']) {
        notify($conn, (int)$claim['adjuster_id'], $claimId, 'estimate',
            'Garage estimate submitted',
            $g['full_name'] . ' quoted UGX ' . number_format($total) . ' on ' . $claim['claim_number'] . '.');
    }
    notify($conn, (int)$claim['user_id'], $claimId, 'estimate',
        'Garage estimate received',
        'A garage has quoted the repair on ' . $claim['claim_number'] . '.');

    api_json(200, ['ok' => true, 'estimate_id' => $estimateId, 'total_estimate' => $total]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    api_json(500, ['message' => 'Could not save the estimate right now.']);
}