<?php
declare(strict_types=1);
require_once __DIR__ . '/_helpers.php';
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET','POST'], true)) apiJson(405, ['message' => 'Method not allowed']);
$user = apiUser(['claimant','adjuster']);
if ($method === 'GET') {
    // The independent reviewer receives only the context needed for the complaint.
    $field = $user['role'] === 'claimant' ? 'q.opened_by' : 'q.reviewer_id';
    $query = $conn->prepare("SELECT q.*, c.claim_number, c.status AS claim_status, c.rejection_reason, c.approved_amount,
        c.incident_date, c.incident_description, c.estimated_damage, p.coverage_limit, p.coverage_type,
        (SELECT total_estimate FROM garage_estimates WHERE claim_id = c.id ORDER BY id DESC LIMIT 1) AS quoted_amount,
        u.full_name AS reviewer_name FROM claim_complaints q JOIN claims c ON c.id = q.claim_id
        JOIN policies p ON p.id = c.policy_id
        JOIN users u ON u.id = q.reviewer_id WHERE $field = ? ORDER BY q.created_at DESC, q.id DESC");
    $query->execute([$user['id']]);
    $claims = [];
    if ($user['role'] === 'claimant') {
        $queryClaims = $conn->prepare('SELECT id, claim_number FROM claims WHERE user_id = ? AND status <> "draft" ORDER BY created_at DESC');
        $queryClaims->execute([$user['id']]); $claims = $queryClaims->fetchAll();
    }
    $rows = $query->fetchAll();
    $documents = $conn->prepare('SELECT id, original_name, is_verified, file_path FROM claim_documents WHERE claim_id = ? ORDER BY id');
    foreach ($rows as &$row) {
        $documents->execute([$row['claim_id']]);
        $row['documents'] = array_map(static fn(array $document): array => [
            'id' => (int)$document['id'], 'name' => $document['original_name'],
            'verified' => (bool)$document['is_verified'],
            'has_file' => !empty($document['file_path']) && uploadResolvePath($document['file_path']) !== null,
        ], $documents->fetchAll());
    }
    unset($row);
    apiJson(200, ['complaints' => $rows, 'claims' => $claims]);
}
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) apiJson(400, ['message' => 'Invalid request body']);
try {
    $result = inTransaction($conn, static function () use ($conn, $user, $input): array {
        if ($user['role'] === 'claimant') {
            $claim = claimLock($conn, (int)($input['claim_id'] ?? 0));
            if ((int)$claim['user_id'] !== (int)$user['id']) throw new ApiAbort('This is not your claim.', 403);
            $id = openClaimComplaint($conn, $user, $claim, workflowText($input['details'] ?? '', 'the complaint details'));
            return ['id' => $id];
        }
        // Read the claim id, then follow the common claim -> related-row lock order.
        $query = $conn->prepare('SELECT claim_id FROM claim_complaints WHERE id = ?');
        $query->execute([(int)($input['id'] ?? 0)]); $claimId = (int)$query->fetchColumn();
        $claim = claimLock($conn, $claimId);
        $query = $conn->prepare('SELECT * FROM claim_complaints WHERE id = ? FOR UPDATE');
        $query->execute([(int)$input['id']]); $row = $query->fetch();
        if ((int)$row['reviewer_id'] !== (int)$user['id']) throw new ApiAbort('Only the assigned independent reviewer can update this complaint.', 403);
        if ($row['status'] === 'resolved') throw new ApiAbort('This complaint is already resolved.', 409);
        $action = $input['action'] ?? '';
        if ($action === 'start_review' && $row['status'] === 'open') {
            $status = 'under_review'; $response = null;
        } elseif ($action === 'resolve' && $row['status'] === 'under_review') {
            $status = 'resolved'; $response = workflowText($input['response'] ?? '', 'the investigation outcome and explanation');
        } else throw new ApiAbort('Start review before resolving the complaint.', 409);
        $conn->prepare('UPDATE claim_complaints SET status = ?, response = ?, reviewed_at = COALESCE(reviewed_at, NOW()), resolved_at = IF(? = "resolved", NOW(), NULL) WHERE id = ?')
            ->execute([$status, $response, $status, $row['id']]);
        audit($conn, $user, 'complaint.' . $status, 'complaint', (int)$row['id'], ['status' => $row['status']], ['status' => $status, 'response' => $response]);
        notify($conn, (int)$row['opened_by'], $claimId, 'status_changed', 'Complaint ' . str_replace('_',' ', $status),
            'Complaint #' . $row['id'] . ': ' . ($response ?? 'An independent reviewer has started investigating.'));
        return ['status' => $status];
    });
    apiJson(200, ['ok' => true] + $result);
} catch (PDOException $error) {
    error_log($error->getMessage()); apiJson(500, ['message' => 'Could not update the complaint.']);
}
