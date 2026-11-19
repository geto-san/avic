<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

$user = apiUser(['adjuster']);
$id = (int)$user['id'];
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    $cursor = max(0, (int)($_GET['after'] ?? 0));
    $query = $conn->prepare("SELECT c.id, c.claim_number, c.adjuster_id, u.full_name AS owner, t.due_at
        FROM coverage_checks t JOIN claims c ON c.id = t.claim_id
        LEFT JOIN users u ON u.id = c.adjuster_id
        WHERE t.completed_at IS NULL AND c.status IN ('submitted','under_review','pending_docs')
        AND c.id > ? AND (c.adjuster_id = ? OR EXISTS (
            SELECT 1 FROM staff_permissions WHERE user_id = ? AND permission = 'coverage.view_all'))
        ORDER BY c.id LIMIT 51");
    $query->execute([$cursor, $id, $id]);
    $rows = $query->fetchAll();
    $more = count($rows) > 50;
    if ($more) {
        array_pop($rows);
    }
    apiJson(200, ['tasks' => $rows, 'next' => $more ? (int)end($rows)['id'] : null]);
}
if ($method !== 'POST') {
    apiJson(405, ['message' => 'Method not allowed']);
}
$data = json_decode(file_get_contents('php://input'), true);
inTransaction($conn, static function () use ($conn, $data, $id, $user) {
    $claim = claimLock($conn, (int)($data['claim_id'] ?? 0));
    if ((int)$claim['adjuster_id'] !== $id) {
        throw new ApiAbort('Only the assigned adjuster can record this check.', 403);
    }
    if (!in_array($claim['status'], ['submitted','under_review','pending_docs'], true)) {
        throw new ApiAbort('This claim is no longer in review.', 409);
    }
    $note = workflowText($data['evidence'] ?? null, 'the policy evidence checked', 500);
    $update = $conn->prepare('UPDATE coverage_checks SET completed_by = ?, completed_at = UTC_TIMESTAMP(), evidence = ?
        WHERE claim_id = ? AND completed_at IS NULL');
    $update->execute([$id, $note, $claim['id']]);
    if ($update->rowCount() !== 1) {
        throw new ApiAbort('This task is no longer open.', 409);
    }
    audit($conn, $user, 'coverage.checked', 'claim', (int)$claim['id'], null, ['evidence' => $note]);
});
apiJson(200, ['ok' => true]);
