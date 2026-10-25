<?php
declare(strict_types=1);

/**
 * GET config/uploads.php?doc=N
 *
 * The only way files are served back out. The id maps to a claim_documents
 * row, the path on disk comes from the database (never from the client),
 * and the viewer must be someone who could have seen the claim in the
 * first place — the same role matrix as the rest of the API. Files that
 * would not have been accepted on upload (non image/PDF, too large) are
 * refused here too.
 *
 * Files live in uploadDir() (outside the web root); files uploaded before that
 * move are still found in <project>/uploads/ — see uploadResolvePath().
 */

require_once __DIR__ . '/api/_helpers.php';

/** Plain-text error (the shared API helpers default to a JSON content type). */
function uploadFail(int $code, string $message): void
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    uploadFail(405, 'Method not allowed.');
}

$user = apiUser();
$docId = (int)($_GET['doc'] ?? 0);

try {
    $q = $conn->prepare(
        'SELECT d.id, d.claim_id, d.original_name, d.file_path, d.file_size, d.mime_type,
                c.user_id, c.adjuster_id
         FROM claim_documents d JOIN claims c ON c.id = d.claim_id
         WHERE d.id = :id LIMIT 1'
    );
    $q->execute(['id' => $docId]);
    $doc = $q->fetch();
    if (!$doc) {
        uploadFail(404, 'Document not found.');
    }

    /* role matrix for viewing */
    $allowed = false;
    if ($user['role'] === 'claimant') {
        $allowed = (int)$doc['user_id'] === (int)$user['id'];
    } elseif ($user['role'] === 'adjuster') {
        $allowed = (int)$doc['adjuster_id'] === (int)$user['id'];
    } elseif ($user['role'] === 'garage') {
        $wo = $conn->prepare('SELECT id FROM work_orders WHERE claim_id = :cid AND garage_user_id = :gid LIMIT 1');
        $wo->execute(['cid' => (int)$doc['claim_id'], 'gid' => (int)$user['id']]);
        $allowed = (bool)$wo->fetch();
    }
    if (!$allowed) {
        uploadFail(403, 'You do not have access to that document.');
    }

    /* extension whitelist — never guess a type from the client's header */
    $ext = uploadExtFromMime((string)$doc['mime_type']);
    if ($ext === null) {
        uploadFail(415, 'Unsupported file type.');
    }

    $path = $doc['file_path'] ? uploadResolvePath((string)$doc['file_path']) : null;
    if ($path === null) {
        uploadFail(404, 'File is missing on disk.');
    }

    /* file has been swapped since upload? a serving page shouldn't change ext/mime */
    $size = filesize($path);
    if ($doc['file_size'] && $size !== (int)$doc['file_size']) {
        uploadFail(404, 'File integrity check failed.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($path);
    if ($mime !== $doc['mime_type']) {
        uploadFail(404, 'File integrity check failed.');
    }

    /* the stored name came from the uploader: strip it to a safe ASCII fallback for
       filename="..." and send the real one percent-encoded in filename*= (RFC 5987),
       so a quote or ; in it can never break out of the header */
    $original = (string)($doc['original_name'] ?? '');
    $ascii = trim((string)preg_replace('/[^A-Za-z0-9._-]+/', '_', $original), '_');
    $ascii = $ascii === '' ? 'document' : $ascii;

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . $size);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    header('Content-Disposition: inline; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($original !== '' ? $original : $ascii));
    readfile($path);
} catch (PDOException $e) {
    error_log($e->getMessage());
    uploadFail(500, 'Server error.');
}
