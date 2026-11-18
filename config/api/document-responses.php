<?php
declare(strict_types=1);

// A response uploads one requested document. It never edits the original claim form.
require_once __DIR__ . '/_helpers.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') apiJson(405, ['message'=>'Method not allowed']);
$user = apiUser(['claimant']);
$itemId = (int)($_POST['requested_document_id'] ?? 0);
$storedPath = null;
try {
    $lookup = $conn->prepare('SELECT r.claim_id FROM requested_documents d JOIN claim_document_requests r ON r.id = d.request_id WHERE d.id = ?');
    $lookup->execute([$itemId]);
    $claimId = $itemId ? (int)$lookup->fetchColumn() : (int)($_POST['claim_id'] ?? 0);
    if (!$claimId) throw new ApiAbort('Requested document not found.', 404);
    apiCanSeeClaim($conn, $user, $claimId);
    $conn->beginTransaction();
    $claim = claimLock($conn, $claimId);
    // Older claims may have a pending-documents state without a named request.
    // Give those cases the same focused upload path without changing their details.
    if (!$itemId) {
        $old = $conn->prepare('SELECT id FROM claim_document_requests WHERE claim_id = ?');
        $old->execute([$claimId]);
        if ($claim['status'] !== 'pending_docs' || $old->fetchColumn()) throw new ApiAbort('Choose an existing requested document.', 409);
        $conn->prepare('INSERT INTO claim_document_requests (claim_id, requested_by, details) VALUES (?, ?, "Supporting document requested in the earlier claim review")')->execute([$claimId, $claim['adjuster_id']]);
        $requestId = (int)$conn->lastInsertId();
        $conn->prepare('INSERT INTO requested_documents (request_id, document_type, document_name) VALUES (?, "other", "Supporting document")')->execute([$requestId]);
        $itemId = (int)$conn->lastInsertId();
    }
    $query = $conn->prepare('SELECT d.*, r.status AS request_status FROM requested_documents d JOIN claim_document_requests r ON r.id = d.request_id WHERE d.id = ? FOR UPDATE');
    $query->execute([$itemId]); $item = $query->fetch();
    if ($claim['status'] !== 'pending_docs' || $item['document_id'] || $item['request_status'] !== 'open') {
        throw new ApiAbort('This document has already been supplied or the request is no longer open.', 409);
    }
    $files = $_FILES['files'] ?? [];
    if (!is_array($files['name'] ?? null) || count($files['name']) !== 1 || ($files['error'][0] ?? 4) !== UPLOAD_ERR_OK) {
        throw new ApiAbort('Choose one file for this requested document.', 422);
    }
    $file = ['name'=>$files['name'][0], 'tmp_name'=>$files['tmp_name'][0], 'size'=>$files['size'][0], 'error'=>$files['error'][0]];
    $limit = min(12, max(1, (int)apiSettings($conn)['max_upload_mb'])) * 1048576;
    if ((int)$file['size'] <= 0 || (int)$file['size'] > $limit || !is_uploaded_file($file['tmp_name']) || !uploadExtFromMime((string)(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']))) {
        throw new ApiAbort('Use a JPEG, PNG, WEBP or PDF within the displayed size limit.', 422);
    }
    if (saveUploads($conn, $claimId, (int)$user['id'], [$file]) !== 1) throw new ApiAbort('Could not store the document. Try again.', 500);
    $documentId = (int)$conn->lastInsertId();
    $pathQuery = $conn->prepare('SELECT file_path FROM claim_documents WHERE id = ?');
    $pathQuery->execute([$documentId]); $storedPath = uploadResolvePath((string)$pathQuery->fetchColumn());
    $conn->prepare('UPDATE claim_documents SET doc_type = ? WHERE id = ?')->execute([$item['document_type'], $documentId]);
    $conn->prepare('UPDATE requested_documents SET document_id = ?, uploaded_at = NOW() WHERE id = ?')->execute([$documentId, $itemId]);
    $remaining = $conn->prepare('SELECT COUNT(*) FROM requested_documents WHERE request_id = ? AND document_id IS NULL');
    $remaining->execute([$item['request_id']]);
    if (!(int)$remaining->fetchColumn()) {
        $conn->prepare('UPDATE claim_document_requests SET status = "answered", answered_at = NOW() WHERE id = ?')->execute([$item['request_id']]);
    }
    $open = $conn->prepare('SELECT COUNT(*) FROM claim_document_requests WHERE claim_id = ? AND status = "open"');
    $open->execute([$claimId]); $complete = !(int)$open->fetchColumn();
    if ($complete) {
        $conn->prepare('UPDATE claims SET status = "submitted", updated_at = NOW() WHERE id = ?')->execute([$claimId]);
        $conn->prepare('INSERT INTO claim_status_history (claim_id, changed_by, from_status, to_status, notes) VALUES (?, ?, "pending_docs", "submitted", "Requested documents supplied")')->execute([$claimId, $user['id']]);
    }
    audit($conn, $user, 'document.response', 'claim_document', $documentId, null, ['claim_id'=>$claimId,'requested_document_id'=>$itemId]);
    notify($conn, (int)$claim['adjuster_id'], $claimId, 'doc_verified', 'Requested document uploaded', $claim['claim_number'] . ': ' . $item['document_name'] . ' is ready for assessment.');
    $conn->commit();
    apiJson(200, ['ok'=>true,'complete'=>$complete,'document_id'=>$documentId]);
} catch (Throwable $error) {
    if ($conn->inTransaction()) $conn->rollBack();
    if ($storedPath && is_file($storedPath)) unlink($storedPath);
    if ($error instanceof ApiAbort) apiJson($error->getCode(), ['message'=>$error->getMessage()]);
    error_log($error->getMessage()); apiJson(500, ['message'=>'Could not save this document. Try again.']);
}
