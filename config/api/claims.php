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
 */

require_once __DIR__ . '/_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    api_json(405, ['message' => 'Method not allowed']);
}

$user = api_user(['claimant']);
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
    api_json(400, ['message' => 'Unknown mode.']);
}
$existingId = isset($in['id']) ? (int)$in['id'] : 0;

try {
    $claim = null;
    if ($existingId) {
        $claim = api_can_see_claim($conn, $user, $existingId);
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
                api_json(422, ['message' => 'Fill in every required field.']);
            }
        }
        $policy = $conn->prepare('SELECT * FROM policies WHERE id = :id AND user_id = :uid AND status = "active" LIMIT 1');
        $policy->execute(['id' => $policyId, 'uid' => $uid]);
        if (!$policy->fetch()) {
            api_json(422, ['message' => 'Choose one of your own active policies.']);
        }
    }

    $fields = [
        'policy_id'             => $policyId,
        'claim_type'            => $claimType,
        'incident_date'         => $incidentDate,
        'incident_location'     => $incidentDate === '' ? null : $location,
        'incident_description'  => $description,
        'police_report_ref'     => $policeRef,
        'estimated_damage'      => $estDamage,
    ];

    /* ---- create or update the claim row ---- */
    if (!$claim) {
        $claim_number = next_claim_number($conn);
        $status = $mode === 'draft' ? 'draft' : 'submitted';
        $adjuster = $mode === 'submit' ? least_busy_adjuster($conn) : null;
        $stmt = $conn->prepare(
            'INSERT INTO claims (claim_number, user_id, policy_id, adjuster_id, incident_date, incident_location,
                                 incident_description, claim_type, estimated_damage, police_report_ref, status,
                                 submitted_at, created_at, updated_at)
             VALUES (:cn, :uid, :pid, :aid, :idate, :loc, :desc, :ctype, :est, :pref, :status,
                     :sub, NOW(), NOW())'
        );
        $stmt->execute([
            'cn' => $claim_number, 'uid' => $uid, 'pid' => $policyId, 'aid' => $adjuster,
            'idate' => $incidentDate, 'loc' => $location, 'desc' => $description,
            'ctype' => $claimType, 'est' => $estDamage, 'pref' => $policeRef, 'status' => $status,
            'sub' => $mode === 'submit' ? date('Y-m-d H:i:s') : null,
        ]);
        $claimId = (int)$conn->lastInsertId();
        $audit_target = $claim_number;
        $old_status = null;
    } else {
        $claimId = (int)$claim['id'];
        $claim_number = $claim['claim_number'];
        $old_status = $claim['status'];

        /* pending_docs add-docs flow reopens the queue; drafts just update */
        $newStatus = $mode === 'draft' ? 'draft' : 'submitted';
        $stampSubmit = $mode === 'submit' && $old_status !== 'pending_docs';
        $stmt = $conn->prepare(
            'UPDATE claims SET policy_id = :pid, claim_type = :ctype, incident_date = :idate,
                    incident_location = :loc, incident_description = :desc,
                    police_report_ref = :pref, estimated_damage = :est, status = :status,' .
                    ($stampSubmit ? ' submitted_at = NOW(),' : '') . '
                    updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'pid' => $policyId, 'ctype' => $claimType, 'idate' => $incidentDate, 'loc' => $location,
            'desc' => $description, 'pref' => $policeRef, 'est' => $estDamage,
            'status' => $newStatus, 'id' => $claimId,
        ]);
        $audit_target = $claim_number;
    }

    /* ---- document uploads ---- */
    $savedDocs = 0;
    if ($mode === 'submit' && $files) {
        $savedDocs = save_uploads($conn, $claimId, $uid, files_to_arrays($files));
    }

    if ($mode === 'submit') {
        $finalStatus = 'submitted';
        $conn->prepare('INSERT INTO claim_status_history (claim_id, changed_by, from_status, to_status, notes)
                        VALUES (:cid, :by, :from, :to, :notes)')
             ->execute(['cid' => $claimId, 'by' => $uid, 'from' => $old_status ?: 'draft', 'to' => $finalStatus, 'notes' => 'Claim submitted by policy holder']);
        audit($conn, $user, 'claim.submitted', 'claim', $claimId, ['status' => $old_status], ['status' => $finalStatus, 'documents' => $savedDocs]);

        $adjuster = $conn->prepare('SELECT adjuster_id FROM claims WHERE id = :id');
        $adjuster->execute(['id' => $claimId]);
        $aid = $adjuster->fetch();
        if ($aid && $aid['adjuster_id']) {
            notify($conn, (int)$aid['adjuster_id'], $claimId, 'claim_assigned',
                'New claim in your queue',
                $claim_number . ' was routed to you for review.');
        }
        notify($conn, $uid, $claimId, 'status_changed', 'Claim submitted',
            $claim_number . ' is in the queue. You will hear from an adjuster.');
    } else {
        audit($conn, $user, 'claim.draft_saved', 'claim', $claimId, null, ['status' => 'draft']);
    }

    api_json(200, [
        'ok' => true,
        'id' => $claimId,
        'claim_number' => $claim_number,
        'documents_saved' => $savedDocs,
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    api_json(500, ['message' => 'Could not save the claim right now.']);
}

function next_claim_number(PDO $conn): string
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
function files_to_arrays(array $files): array
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