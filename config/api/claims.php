<?php
declare(strict_types=1);

/* POST config/api/claims.php */

require_once __DIR__ . '/_helpers.php';

// Reading path: request checks -> input validation -> persistClaim() -> JSON reply.
// Follow the browser side in Claimant.wizard() in assets/js/pages-claimant.js.

const CLAIM_TYPES = ['collision', 'theft', 'vandalism', 'fire', 'natural_disaster', 'other'];

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    apiJson(405, ['message' => 'Method not allowed']);
}

// 1. Identify the caller from the PHP session, never from a submitted user ID.
$user = apiUser(['claimant']);
$uid  = (int)$user['id'];

// 2. Drafts arrive as JSON; submissions can carry files using multipart form data.
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
        if (!in_array($claimType, CLAIM_TYPES, true)) {
            $claimType = 'other';
        }
        if (!validDate($incidentDate)) {
            $incidentDate = date('Y-m-d');
        }
    }

    // 3. A submission needs complete details and an active policy owned by this user.
    if ($mode === 'submit') {
        $requiredValues = [
            $policyId,
            $claimType,
            $incidentDate,
            $location,
            $description,
        ];
        foreach ($requiredValues as $value) {
            if ($value === '' || $value === 0) {
                apiJson(422, ['message' => 'Fill in every required field.']);
            }
        }
        if (!in_array($claimType, CLAIM_TYPES, true)) {
            apiJson(422, ['message' => 'Choose a valid claim type.']);
        }
        if (!validDate($incidentDate)) {
            apiJson(422, ['message' => 'Enter the incident date as a real date (YYYY-MM-DD).']);
        }
        /* a day of slack so a claimant whose "today" is ahead of the server's is not refused */
        if ($incidentDate > date('Y-m-d', strtotime('+1 day'))) {
            apiJson(422, ['message' => 'The incident date cannot be in the future.']);
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

    // 4. Save related database records in one transaction; then return the result.
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

    $row = $existingId ? claimLock($conn, $existingId) : null;
    if ($row && $row['status'] === 'pending_docs') {
        $requests = $conn->prepare('SELECT d.id FROM requested_documents d JOIN claim_document_requests r ON r.id = d.request_id WHERE r.claim_id = ? AND r.status = "open" LIMIT 1');
        $requests->execute([$existingId]);
        if ($requests->fetchColumn()) throw new ApiAbort('Use the requested-document upload screen to answer this request.', 409);
    }
    $policy = policyLock($conn, (int)$fields['policy_id']);
    if ((int)$policy['user_id'] !== $uid || $policy['status'] !== 'active') {
        throw new ApiAbort('Choose one of your own active policies.', 422);
    }
    if ($submit) assertNoDuplicateIncident($conn, $fields, $existingId);

    // Existing claim: check its current state. New claim: insert a row.
    if ($existingId) {
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
        $claimNumber = 'CLM-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(8)));
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

    /* Store file bytes on disk and document metadata in MySQL. A database
       rollback does not undo files already moved to disk. */
    $savedDocs = 0;
    if ($submit && $files) {
        $savedDocs = saveUploads($conn, $claimId, $uid, filesToArrays($files));
    }

    if ($submit) {
        if ($oldStatus === 'pending_docs' && $savedDocs === 0) {
            $request = $conn->prepare('SELECT id FROM claim_document_requests WHERE claim_id = ? AND status = "open"');
            $request->execute([$claimId]);
            if ($request->fetchColumn()) throw new ApiAbort('Attach the requested documents before submitting your response.', 422);
        }
        if ($oldStatus === 'pending_docs' && $savedDocs > 0) {
            $conn->prepare('UPDATE claim_document_requests SET status = "answered", answered_at = NOW() WHERE claim_id = ? AND status = "open"')->execute([$claimId]);
        }
        $conn->prepare('INSERT INTO claim_status_history (claim_id, changed_by, from_status, to_status, notes)
                        VALUES (:cid, :by, :from, :to, :notes)')
             ->execute(['cid' => $claimId, 'by' => $uid, 'from' => $oldStatus ?: 'draft', 'to' => 'submitted', 'notes' => 'Claim submitted by policy holder']);
        audit($conn, $user, 'claim.submitted', 'claim', $claimId, ['status' => $oldStatus], ['status' => 'submitted', 'documents' => $savedDocs]);

        if ($oldStatus === 'pending_docs') {
            // This legacy form can change policy or loss details. Recheck evidence on resubmission.
            $conn->prepare('UPDATE coverage_checks SET completed_by = NULL, completed_at = NULL, evidence = NULL WHERE claim_id = ?')
                ->execute([$claimId]);
        } else {
            $days = (int)apiSettings($conn)['claims_sla_days'];
            $deadline = claimDue(gmdate('Y-m-d H:i:s'), $days);
            $conn->prepare('INSERT INTO coverage_checks (claim_id, due_at) VALUES (?, ?)')->execute([$claimId, $deadline]);
        }
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
