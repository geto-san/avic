<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') apiJson(405, ['message' => 'Method not allowed']);
$user = apiUser(['adjuster']);
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) apiJson(400, ['message' => 'Invalid request body']);
$claimId = (int)($input['claim_id'] ?? 0);
$action = $input['action'] ?? '';
$targets = ['start_review' => 'under_review', 'close' => 'closed'];
if (!is_string($action) || !isset($targets[$action])) apiJson(422, ['message' => 'Choose start review or close.']);

try {
    apiCanSeeClaim($conn, $user, $claimId);
    $status = inTransaction($conn, static function () use ($conn, $user, $claimId, $targets, $action): string {
        $claim = claimLock($conn, $claimId);
        $status = $targets[$action];
        claimAssertMove($claim['status'], $status);
        $conn->prepare('UPDATE claims SET status = ?, updated_at = NOW() WHERE id = ?')->execute([$status, $claimId]);
        if ($status === 'closed') {
            assertNoOpenComplaint($conn, $claimId);
            $conn->prepare('UPDATE work_orders SET status = "closed" WHERE claim_id = ?')->execute([$claimId]);
        }
        $conn->prepare('INSERT INTO claim_status_history (claim_id, changed_by, from_status, to_status, notes) VALUES (?, ?, ?, ?, ?)')
            ->execute([$claimId, $user['id'], $claim['status'], $status, $action === 'close' ? 'Case closed by adjuster' : 'Review started']);
        audit($conn, $user, 'claim.' . $action, 'claim', $claimId, ['status' => $claim['status']], ['status' => $status]);
        notify($conn, (int)$claim['user_id'], $claimId, 'status_changed',
            $action === 'close' ? 'Claim closed' : 'Review started', $claim['claim_number'] . ': ' . claimStatusWords($status));
        return $status;
    });
    apiJson(200, ['ok' => true, 'status' => $status]);
} catch (PDOException $error) {
    error_log($error->getMessage());
    apiJson(500, ['message' => 'Could not update the claim.']);
}
