<?php
declare(strict_types=1);

/**
 * Shared plumbing for the AVIC write API.
 *
 * Every endpoint under config/api/ starts by requiring this file, which
 * boots the session, provides JSON helpers, and checks that the caller is
 * really the person the front-end claims to be. The `sessionStorage`
 * mirror on the client is only a convenience — authorization happens here,
 * on the server, against $_SESSION['user'].
 */

require_once __DIR__ . '/../db/db_connection.php';
require_once __DIR__ . '/../session.php';

header('Content-Type: application/json; charset=utf-8');

function api_json(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

/** The signed-in user, or a JSON error when there is none / the role is wrong. */
function api_user(array $roles = []): array
{
    avic_session();
    $u = $_SESSION['user'] ?? null;
    if (!$u) {
        api_json(401, ['message' => 'Not signed in.']);
    }
    if (($u['status'] ?? '') !== 'active') {
        api_json(403, ['message' => 'This account is ' . ($u['status'] ?? 'unknown') . ' and cannot use the portal.']);
    }
    if ($roles && !in_array($u['role'], $roles, true)) {
        api_json(403, ['message' => 'Forbidden for this role.']);
    }
    return $u;
}

/** Cast a decimal/string column to a JSON number (keeps front-end math intact). */
function num($v): ?float
{
    return $v === null || $v === '' ? null : (float)$v;
}

/** A number that is always a number (counts, percentages). */
function cnt($v): int
{
    return (int)($v ?? 0);
}

/** Claimant/SLA deadline = submitted_at + SLA days. */
function claim_due(string $submittedAt, int $slaDays): ?string
{
    if ($submittedAt === '') {
        return null;
    }
    return date('Y-m-d H:i:s', strtotime($submittedAt) + max(1, $slaDays) * 86400);
}

/** Flatten the settings table for the front end. */
function api_settings(PDO $conn): array
{
    $rows = $conn->query('SELECT key_name, value FROM settings')->fetchAll();
    $out  = [
        'company_name'    => 'AVIC Insurance Co.',
        'payout_currency' => 'UGX',
        'max_upload_mb'   => 10,
        'claims_sla_days' => 14,
        'auto_assign'     => 'round_robin',
        'support_email'   => 'support@avic.ug',
    ];
    foreach ($rows as $r) {
        $out[$r['key_name']] = $r['value'];
    }
    return $out;
}

/** Insert a notification for a user (used by every write endpoint). */
function notify(PDO $conn, int $userId, ?int $claimId, string $type, string $title, string $message): void
{
    $stmt = $conn->prepare(
        'INSERT INTO notifications (user_id, claim_id, type, title, message) VALUES (:uid, :cid, :type, :title, :msg)'
    );
    $stmt->execute(['uid' => $userId, 'cid' => $claimId, 'type' => $type, 'title' => $title, 'msg' => $message]);
}

/** Append to the audit trail. Old/new values are stored as JSON. */
function audit(PDO $conn, array $user, string $action, ?string $entityType = null, ?int $entityId = null, $old = null, $new = null): void
{
    $stmt = $conn->prepare(
        'INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_value, new_value, ip_address)
         VALUES (:uid, :action, :et, :eid, :old, :new, :ip)'
    );
    $stmt->execute([
        'uid'    => $user['id'] ?? null,
        'action' => $action,
        'et'     => $entityType,
        'eid'    => $entityId,
        'old'    => $old === null ? null : json_encode($old),
        'new'    => $new === null ? null : json_encode($new),
        'ip'     => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

/** Look up an active adjuster with the fewest open claims (simple routing). */
function least_busy_adjuster(PDO $conn): ?int
{
    $row = $conn->query(
        'SELECT u.id FROM users u
         LEFT JOIN claims c ON c.adjuster_id = u.id AND c.status IN ("submitted","under_review","pending_docs")
         WHERE u.role = "adjuster" AND u.status = "active"
         GROUP BY u.id
         ORDER BY COUNT(c.id) ASC, u.id ASC
         LIMIT 1'
    )->fetch();
    return $row ? (int)$row['id'] : null;
}

/** Fetch a claim row and assert the caller is allowed to see it (role matrix). */
function api_can_see_claim(PDO $conn, array $user, int $claimId): ?array
{
    $c = $conn->prepare('SELECT * FROM claims WHERE id = :id LIMIT 1');
    $c->execute(['id' => $claimId]);
    $claim = $c->fetch();
    if (!$claim) {
        api_json(404, ['message' => 'Claim not found.']);
    }
    if ($user['role'] === 'claimant' && (int)$claim['user_id'] !== (int)$user['id']) {
        api_json(403, ['message' => 'That claim belongs to another policy holder.']);
    }
    if ($user['role'] === 'adjuster' && (int)$claim['adjuster_id'] !== (int)$user['id']) {
        api_json(403, ['message' => 'That claim is assigned to a different adjuster.']);
    }
    /* garages may only reach a claim they actually hold a work order for —
       the same rule uploads.php enforces, now enforced for every caller. */
    if ($user['role'] === 'garage') {
        $wo = $conn->prepare(
            'SELECT id FROM work_orders WHERE claim_id = :cid AND garage_user_id = :gid LIMIT 1'
        );
        $wo->execute(['cid' => (int)$claim['id'], 'gid' => (int)$user['id']]);
        if (!$wo->fetch()) {
            api_json(403, ['message' => 'Your workshop does not hold a work order for that claim.']);
        }
    }
    return $claim;
}

const UPLOAD_DIR = __DIR__ . '/../../uploads';

/** Whitelist of MIME -> extension used for both validation and mime sniffing. */
function upload_ext_from_mime(string $mime): ?string
{
    $map = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];
    return $map[strtolower($mime)] ?? null;
}

/**
 * Persist an array of uploaded files (field name "files") onto a claim.
 * Files are stored under uploads/ with random names; the DB row records the
 * original name so the UI can keep showing it. Returns the count saved.
 */
function save_uploads(PDO $conn, int $claimId, int $userId, array $files): int
{
    $saved = 0;
    foreach ($files as $i => $file) {
        if (($file['error'] ?? 4) !== 0 || !isset($file['tmp_name'])) {
            continue;
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            continue;
        }
        $size = (int)$file['size'];
        if ($size <= 0 || $size > 12 * 1048576) {
            continue;
        }
        $finfo  = new finfo(FILEINFO_MIME_TYPE);
        $mime   = (string)$finfo->file($file['tmp_name']);
        $ext    = upload_ext_from_mime($mime);
        $docType = upload_doc_type($ext, $mime, (string)($file['name'] ?? ''));
        if ($ext === null) {
            continue; // not a permitted file type — skipped, not fatal
        }
        $storedName = bin2hex(random_bytes(10)) . '.' . $ext;
        if (!is_dir(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0775, true);
        }
        $target = UPLOAD_DIR . '/' . $storedName;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            continue;
        }
        $stmt = $conn->prepare(
            'INSERT INTO claim_documents (claim_id, uploaded_by, doc_type, original_name, stored_name, file_path, file_size, mime_type)
             VALUES (:cid, :uid, :dt, :oname, :sname, :fpath, :fsize, :mime)'
        );
        $stmt->execute([
            'cid'   => $claimId,
            'uid'   => $userId,
            'dt'    => $docType,
            'oname' => $file['name'],
            'sname' => $storedName,
            'fpath' => 'uploads/' . $storedName,
            'fsize' => $size,
            'mime'  => $mime,
        ]);
        $saved++;
    }
    return $saved;
}

/* Pick the doc_type bucket from the raw file name / mime. */
function upload_doc_type(?string $ext, string $mime, string $name): string
{
    if ($mime === 'application/pdf') {
        $n = strtolower($name);
        if (str_contains($n, 'police'))  return 'police_report';
        if (str_contains($n, 'quote') || str_contains($n, 'estim')) return 'repair_estimate';
        return 'other';
    }
    return $mime === 'image/jpeg' && str_contains(strtolower($name), 'photo') ? 'accident_photo' : 'vehicle_photo';
}