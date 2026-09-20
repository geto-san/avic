<?php
declare(strict_types=1);

/**
 * POST config/api/documents.php
 *
 *   { "action": "verify", "id": 7 }
 *
 * An adjuster stamps a document as verified. Only documents on claims the
 * adjuster actually holds are touchable; the claimant is told, and the
 * setter of the verified_by field is never a client-supplied value.
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

if (($input['action'] ?? '') !== 'verify') {
    api_json(422, ['message' => 'Unknown action.']);
}
$docId = (int)($input['id'] ?? 0);

try {
    $doc = $conn->prepare(
        'SELECT d.id, d.claim_id, d.is_verified, d.original_name FROM claim_documents d WHERE d.id = :id LIMIT 1'
    );
    $doc->execute(['id' => $docId]);
    $row = $doc->fetch();
    if (!$row) {
        api_json(404, ['message' => 'Document not found.']);
    }
    api_can_see_claim($conn, $user, (int)$row['claim_id']); // 403 for other desks' claims

    if (!(int)$row['is_verified']) {
        $conn->prepare('UPDATE claim_documents SET is_verified = 1, verified_by = :by, verified_at = NOW() WHERE id = :id')
             ->execute(['by' => (int)$user['id'], 'id' => $docId]);
        audit($conn, $user, 'document.verified', 'claim_document', $docId, ['is_verified' => 0], ['is_verified' => 1]);

        $claim = $conn->prepare('SELECT user_id, claim_number FROM claims WHERE id = :id');
        $claim->execute(['id' => $row['claim_id']]);
        $c = $claim->fetch();
        if ($c) {
            notify($conn, (int)$c['user_id'], (int)$row['claim_id'], 'doc_verified',
                'Document verified',
                $user['name'] . ' verified ' . $row['original_name'] . ' on ' . $c['claim_number'] . '.');
        }
    }

    api_json(200, ['ok' => true]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    api_json(500, ['message' => 'Could not update the document right now.']);
}