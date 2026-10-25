<?php
declare(strict_types=1);

/**
 * POST config/api/claims.php
 *
 * File + submit claims from the claimant wizard.
 *
 *   mode   = 'draft' | 'submit'
 *   id     = existing claim to update (draft resume, or pending_docs add-docs)
 *   fields = policy_id, claim_type, incident_date, incident_location,
 *            incident_description, police_report_ref, estimated_damage
 *   files  = files[] (multipart only when submitting)
 *
 * Drafts persist server-side so they survive reload and other devices;
 * submitted claims are routed to the quietest adjuster in one go.
 *
 * A claimant can only touch a claim that is still theirs to edit: a draft
 * (save or submit) or one waiting for documents (submit again). Anything
 * further along is refused with a 409 — see claim-state.php. The whole write
 * (claim row, uploads, history, audit, notifications) is one transaction.
 */

require_once __DIR__ . '/_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    apiJson(405, ['message' => 'Method not allowed']);
}

$user = apiUser(['claimant']);
$uid  = (int)$user['id'];

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (str_starts_with($contentType, 'application/json')) {
    $input = json_decode(file_get_contents('php://input'), true);
    $in    = is_array($input) ? $input : [];
    $files = [];
} else {
    $in    = $_POST;
    $files = $_FILES['files'] ?? [];
    if (!is_array($files) || !isset($files['name'])) {
        $files = [];
    }
}

$mode = $in['mode'] ?? 'submit';
if (!in_array($mode, ['draft', 'submit'], true)) {
    apiJson(400, ['message' => 'Unknown mode.']);
}
$existingId = isset($in['id']) ? (int)$in['id'] : 0;

try {
    if ($existingId) {
        apiCanSeeClaim($conn, $user, $existingId); // 404 / 403 for someone else's claim
    }

    $policyId = (int)($in['policy_id'] ?? 0);
    $claimType = (string)($in['claim_type'] ?? '');
    $incidentDate = (string)($in['incident_date'] ?? '');
    $location = trim((string)($in['incident_location'] ?? ''));
    $description = trim((string)($in['incident_description'] ?? ''));
    $policeRef = trim((string)($in['police_report_ref'] ?? '')) ?: null;
    $estDamage = isset($in['estimated_damage']) && $in['estimated_damage'] !== '' ? (float)$in['estimated_damage'] : null;

    /* drafts may be half-empty; keep the NOT NULL columns satisfied so the
       row can exist while the claimant works on it */
    if ($mode === 'draft') {
        if (!$policyId) {
            $first = $conn->prepare('SELECT id FROM policies WHERE user_id = :uid AND status = "active" ORDER BY id LIMIT 1');
            $first->execute(['uid' => $uid]);
            $policyId = (int)$first->fetchColumn();
        }
        if ($claimType === '' || !in_array($claimType, ['collision','theft','vandalism','fire','natural_disaster','other'], true)) {
            $claimType = 'other';
        }
        if ($incidentDate === '') {
            $incidentDate = date('Y-m-d');
        }
    }

    if ($mode === 'submit') {
        $required = [
            'policy_id' => "CHOOSE_POLICY",
            'claim_type' => "CHOOSE_TYPE",
            'incident_date' => "CHOOSE_DATE",
            'incident_location' => "LOCATION_REQUIRED",
            'incident_description' => "DESCRIPTION_REQUIRED",
        ];
        foreach ([['k' => 'policy_id', 'v' => $policyId], ['k' => 'claim_type', 'v' => $claimType],
                  ['k' => 'incident_date', 'v' => $incidentDate], ['k' => 'incident_location', 'v' => $location],
                  ['k' => 'incident_description', 'v' => $description]] as $f) {
            if ($f['v'] === '' || $f['v'] === 0) {
                apiJson(422, ['message' => 'Fill in every required field.']);
            }
        }
        $policy = $conn->prepare('SELECT * FROM policies WHERE id = :id AND user_id = :uid AND status = "active" LIMIT 1');
        $policy->execute(['id' => $policyId, 'uid' => $uid]);
        if (!$policy->fetch()) {
            apiJson(422, ['message' => 'Choose one of your own active policies.']);
        }
    }

    $fields = [
        'policy_id'             => $policyId,
        'claim_type'            => $claimType,
        'incident_date'         => $incidentDate,
        'incident_location'     => $location === '' ? null : $location,
        'incident_description'  => $description,
        'police_report_ref'     => $policeRef,
        'estimated_damage'      => $estDamage,
    ];

    $result = inTransaction($conn, static fn() => persistClaim($conn, $user, $mode, $existingId, $fields, $files));

    apiJson(200, ['ok' => true] + $result);
} catch (PDOException $e) {
    error_log($e->getMessage());
    apiJson(500, ['message' => 'Could not save the claim right now.']);
}

/**
 * The transactional part of claims.php. Runs inside inTransaction(): the claim
 * row is locked and re-read, the status move is checked against the lifecycle,
 * then everything is written together or not at all.
 *
 * @return array{id:int, claim_number:string, documents_saved:int}
 */
function persistClaim(PDO $conn, array $user, string $mode, int $existingId, array $fields, array $files): array
{
    $uid = (int)$user['id'];
    $submit = $mode === 'submit';

    if ($existingId) {
        $row = claimLock($conn, $existingId);
        if ((int)$row['user_id'] !== $uid) {
            throw new ApiAbort('That claim belongs to another policy holder.', 403);
        }
        $oldStatus = (string)$row['status'];
        $newStatus = $submit ? 'submitted' : 'draft';
        claimAssertMove($oldStatus, $newStatus, claimEditRefusal($oldStatus, $submit));

        /* a draft that was saved earlier has no adjuster yet — hand it to
           someone now, or it sits in nobody's queue */
        $adjuster = $row['adjuster_id'] === null ? null : (int)$row['adjuster_id'];
        if ($submit && $adjuster === null) {
            $adjuster = leastBusyAdjuster($conn);
        }
        /* pending_docs add-docs flow reopens the queue without resetting the SLA clock */
        $stampSubmit = $submit && $oldStatus !== 'pending_docs';
        $conn->prepare(
            'UPDATE claims SET policy_id = :pid, claim_type = :ctype, incident_date = :idate,
                    incident_location = :loc, incident_description = :desc,
                    police_report_ref = :pref, estimated_damage = :est, status = :status,
                    adjuster_id = :aid,' .
                    ($stampSubmit ? ' submitted_at = NOW(),' : '') . '
                    updated_at = NOW()
             WHERE id = :id'
        )->execute([
            'pid' => $fields['policy_id'], 'ctype' => $fields['claim_type'], 'idate' => $fields['incident_date'],
            'loc' => $fields['incident_location'] ?? '', 'desc' => $fields['incident_description'],
            'pref' => $fields['police_report_ref'], 'est' => $fields['estimated_damage'],
            'status' => $newStatus, 'aid' => $adjuster, 'id' => $existingId,
        ]);
        $claimId = $existingId;
        $claimNumber = (string)$row['claim_number'];
    } else {
        $oldStatus = null;
        $claimNumber = nextClaimNumber($conn);
        $adjuster = $submit ? leastBusyAdjuster($conn) : null;
        $conn->prepare(
            'INSERT INTO claims (claim_number, user_id, policy_id, adjuster_id, incident_date, incident_location,
                                 incident_description, claim_type, estimated_damage, police_report_ref, status,
                                 submitted_at, created_at, updated_at)
             VALUES (:cn, :uid, :pid, :aid, :idate, :loc, :desc, :ctype, :est, :pref, :status,
                     :sub, NOW(), NOW())'
        )->execute([
            'cn' => $claimNumber, 'uid' => $uid, 'pid' => $fields['policy_id'], 'aid' => $adjuster,
            'idate' => $fields['incident_date'], 'loc' => $fields['incident_location'] ?? '',
            'desc' => $fields['incident_description'], 'ctype' => $fields['claim_type'],
            'est' => $fields['estimated_damage'], 'pref' => $fields['police_report_ref'],
            'status' => $submit ? 'submitted' : 'draft',
            'sub' => $submit ? date('Y-m-d H:i:s') : null,
        ]);
        $claimId = (int)$conn->lastInsertId();
    }

    /* ---- document uploads ---- */
    $savedDocs = 0;
    if ($submit && $files) {
        $savedDocs = saveUploads($conn, $claimId, $uid, filesToArrays($files));
    }

    if ($submit) {
        $conn->prepare('INSERT INTO claim_status_history (claim_id, changed_by, from_status, to_status, notes)
                        VALUES (:cid, :by, :from, :to, :notes)')
             ->execute(['cid' => $claimId, 'by' => $uid, 'from' => $oldStatus ?: 'draft', 'to' => 'submitted', 'notes' => 'Claim submitted by policy holder']);
        audit($conn, $user, 'claim.submitted', 'claim', $claimId, ['status' => $oldStatus], ['status' => 'submitted', 'documents' => $savedDocs]);

        if ($adjuster) {
            notify($conn, $adjuster, $claimId, 'claim_assigned',
                'New claim in your queue',
                $claimNumber . ' was routed to you for review.');
        }
        notify($conn, $uid, $claimId, 'status_changed', 'Claim submitted',
            $claimNumber . ' is in the queue. You will hear from an adjuster.');
    } else {
        audit($conn, $user, 'claim.draft_saved', 'claim', $claimId, null, ['status' => 'draft']);
    }

    return ['id' => $claimId, 'claim_number' => $claimNumber, 'documents_saved' => $savedDocs];
}

/** Why a claimant's save/submit on an existing claim was refused (shown to them as-is). */
function claimEditRefusal(string $status, bool $submit): string
{
    if ($status === 'pending_docs' && !$submit) {
        return 'This claim is waiting for documents. Add them and submit it; it cannot go back to a draft.';
    }
    return 'This claim is ' . claimStatusWords($status) . ' and can no longer be edited.';
}

function nextClaimNumber(PDO $conn): string
{
    for ($i = 0; $i < 20; $i++) {
        $year = date('Y');
        $seq = (int)$conn->query("SELECT COUNT(*) FROM claims WHERE claim_number LIKE 'CLM-$year-%'")->fetchColumn() + $i + 1;
        $number = sprintf('CLM-%d-%05d', $year, $seq);
        $exists = $conn->prepare('SELECT id FROM claims WHERE claim_number = :cn LIMIT 1');
        $exists->execute(['cn' => $number]);
        if (!$exists->fetch()) {
            return $number;
        }
    }
    return 'CLM-' . $year . '-' . bin2hex(random_bytes(3));
}

/** Normalize the $_FILES['files'] structure to an index-array of arrays. */
function filesToArrays(array $files): array
{
    $out = [];
    if (!is_array($files['name'] ?? null)) {
        return $out;
    }
    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        $out[$i] = [
            'name'     => $files['name'][$i],
            'type'     => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error'    => $files['error'][$i],
            'size'     => $files['size'][$i],
        ];
    }
    return $out;
}
