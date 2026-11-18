<?php
declare(strict_types=1);

/** Short shared rules; callers hold the claim lock so all changes are atomic. */
function workflowText($value, string $label, int $max = 2000): string
{
    if (!is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > $max) {
        throw new ApiAbort("Enter $label (up to $max characters).", 422);
    }
    return trim($value);
}

function assertNoOpenComplaint(PDO $conn, int $claimId): void
{
    $query = $conn->prepare('SELECT id FROM claim_complaints WHERE claim_id = ? AND status <> "resolved"');
    $query->execute([$claimId]);
    if ($query->fetchColumn()) throw new ApiAbort('Resolve the open complaint before paying or closing this claim.', 409);
}

function openClaimComplaint(PDO $conn, array $user, array $claim, string $details): int
{
    if ($claim['status'] === 'draft') throw new ApiAbort('Submit the claim before raising a complaint.', 409);
    assertNoOpenComplaint($conn, (int)$claim['id']);
    // A different adjuster reviews the complaint; the original decision stays intact.
    $reviewer = $conn->prepare('SELECT id FROM users WHERE role = "adjuster" AND status = "active" AND id <> ? ORDER BY id LIMIT 1');
    $reviewer->execute([(int)$claim['adjuster_id']]);
    $reviewerId = (int)$reviewer->fetchColumn();
    if (!$reviewerId) throw new ApiAbort('An independent active adjuster is needed to handle this complaint.', 409);
    $conn->prepare('INSERT INTO claim_complaints (claim_id, opened_by, reviewer_id, details) VALUES (?, ?, ?, ?)')
        ->execute([$claim['id'], $user['id'], $reviewerId, $details]);
    $id = (int)$conn->lastInsertId();
    audit($conn, $user, 'complaint.opened', 'complaint', $id, null, ['claim_id' => $claim['id'], 'reviewer_id' => $reviewerId]);
    notify($conn, (int)$user['id'], (int)$claim['id'], 'status_changed', 'Complaint received', "Complaint #$id for " . $claim['claim_number'] . ' has been assigned for independent review.');
    notify($conn, $reviewerId, (int)$claim['id'], 'status_changed', 'Complaint assigned', "Review complaint #$id in Complaints.");
    return $id;
}

/** The browser offers these categories, while this whitelist remains authoritative. */
function requestedDocumentChoices(array $input, ?string $legacyNotes): array
{
    $names = ['police_report'=>'Police report','accident_photo'=>'Accident photograph','vehicle_photo'=>'Vehicle photograph',
        'repair_estimate'=>'Repair estimate','medical_report'=>'Medical report','other'=>'Other document'];
    if (!$input) return [['document_type'=>'other','document_name'=>mb_substr(workflowText($legacyNotes, 'the required document name or instructions'), 0, 150)]];
    if (count($input) > 8) throw new ApiAbort('Request up to eight documents at a time.', 422);
    $out = [];
    foreach ($input as $item) {
        if (!is_array($item) || !is_string($item['document_type'] ?? null) || !isset($names[$item['document_type']])) throw new ApiAbort('Choose a valid document category.', 422);
        $type = $item['document_type'];
        $name = $type === 'other' ? workflowText($item['document_name'] ?? '', 'the document name', 150) : $names[$type];
        $out[] = ['document_type'=>$type,'document_name'=>$name];
    }
    return $out;
}
