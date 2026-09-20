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
 */

require_once __DIR__ . '/_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    exit;
}

$user = api_user();
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
        http_response_code(404);
        echo 'Document not found.';
        exit;
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
        http_response_code(403);
        echo 'You do not have access to that document.';
        exit;
    }

    /* extension whitelist — never guess a type from the client's header */
    $ext = upload_ext_from_mime((string)$doc['mime_type']);
    if ($ext === null) {
        http_response_code(415);
        echo 'Unsupported file type.';
        exit;
    }

    $path = __DIR__ . '/../../' . $doc['file_path'];
    if (!$doc['file_path'] || !is_file($path) || !is_readable($path)) {
        http_response_code(404);
        echo 'File is missing on disk.';
        exit;
    }

    /* file has been swapped since upload? a serving page shouldn't change ext/mime */
    $size = filesize($path);
    if ($doc['file_size'] && $size !== (int)$doc['file_size']) {
        http_response_code(404);
        echo 'File integrity check failed.';
        exit;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($path);
    if ($mime !== $doc['mime_type']) {
        http_response_code(404);
        echo 'File integrity check failed.';
        exit;
    }

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . $size);
    $disposition = 'inline';
    header('Content-Disposition: ' . $disposition . '; filename="' . $doc['original_name'] . '"');
    readfile($path);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo 'Server error.';
}