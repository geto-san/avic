<?php
declare(strict_types=1);
require_once __DIR__ . '/_helpers.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') apiJson(405, ['message' => 'Method not allowed']);
$user = apiUser(['claimant']);
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) apiJson(400, ['message' => 'Invalid request body']);
$claimId = (int)($input['claim_id'] ?? 0);
apiCanSeeClaim($conn, $user, $claimId);
try {
    $result = inTransaction($conn, static function () use ($conn, $user, $input, $claimId): array {
        $claim = claimLock($conn, $claimId);
        if ($claim['status'] !== 'approved') throw new ApiAbort('Only an approved settlement can be accepted or disputed.', 409);
        $query = $conn->prepare('SELECT * FROM claim_settlements WHERE claim_id = ? FOR UPDATE');
        $query->execute([$claimId]);
        $offer = $query->fetch();
        if (!$offer || $offer['status'] === 'accepted') throw new ApiAbort('This offer is unavailable or already accepted.', 409);
        if (($input['action'] ?? '') === 'dispute') {
            $details = workflowText($input['details'] ?? '', 'your reason for disputing the offer');
            $id = openClaimComplaint($conn, $user, $claim, $details);
            $conn->prepare('UPDATE claim_settlements SET status = "disputed" WHERE claim_id = ?')->execute([$claimId]);
            return ['complaint_id' => $id];
        }
        if (($input['action'] ?? '') !== 'accept') throw new ApiAbort('Choose accept or dispute.', 422);
        assertNoOpenComplaint($conn, $claimId);
        if (($input['confirmed'] ?? false) !== true) throw new ApiAbort('Confirm that you accept the amount and payment details.', 422);
        $method = $input['payment_method'] ?? '';
        if (!in_array($method, ['bank_transfer','mobile_money','cheque'], true)) throw new ApiAbort('Choose a payment method.', 422);
        $name = workflowText($input['account_name'] ?? '', 'the payee name', 100);
        $number = workflowText($input['account_number'] ?? '', 'the account, mobile number or cheque delivery address', 60);
        if ($method === 'mobile_money' && !preg_match('/^\+?[0-9\s-]{7,20}$/', $number)) {
            throw new ApiAbort('Enter a valid mobile money number.', 422);
        }
        $bank = $method === 'bank_transfer' ? workflowText($input['bank_name'] ?? '', 'the bank name', 100) : null;
        $conn->prepare('UPDATE claim_settlements SET status = "accepted", accepted_by = ?, accepted_at = NOW(), payment_method = ?, account_name = ?, account_number = ?, bank_name = ? WHERE claim_id = ?')
            ->execute([$user['id'], $method, $name, $number, $bank, $claimId]);
        $conn->prepare('UPDATE payouts SET payment_method = ?, account_name = ?, account_number = ?, bank_name = ? WHERE claim_id = ? AND status IN ("pending","processing")')
            ->execute([$method, $name, $number, $bank, $claimId]);
        audit($conn, $user, 'settlement.accepted', 'claim', $claimId, ['status' => $offer['status']], ['status' => 'accepted', 'amount' => $claim['approved_amount']]);
        notify($conn, (int)$claim['adjuster_id'], $claimId, 'payout', 'Settlement accepted', $claim['claim_number'] . ': claimant accepted the offer and confirmed payment details.');
        notify($conn, (int)$user['id'], $claimId, 'payout', 'Settlement acceptance recorded', $claim['claim_number'] . ': your acceptance and payment details have been recorded.');
        return ['status' => 'accepted'];
    });
    apiJson(200, ['ok' => true] + $result);
} catch (PDOException $error) {
    error_log($error->getMessage()); apiJson(500, ['message' => 'Could not record your settlement response.']);
}
