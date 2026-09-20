<?php
declare(strict_types=1);

/**
 * GET config/api/claims-pdf.php?claim_id=N
 *
 * Renders a claim summary as a real PDF (no libraries — a hand-rolled,
 * minimal, valid single-page PDF). Claimants may download their own
 * claims; adjusters, the claims on their desk.
 */

require_once __DIR__ . '/_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    api_json(405, ['message' => 'Method not allowed']);
}

$user = api_user();
$claimId = (int)($_GET['claim_id'] ?? 0);
if (!$claimId) {
    api_json(400, ['message' => 'Missing claim id.']);
}

try {
    $claim = api_can_see_claim($conn, $user, $claimId);

    $claimant = $conn->prepare('SELECT full_name, email, phone FROM users WHERE id = :id');
    $claimant->execute(['id' => $claim['user_id']]);
    $claimant = $claimant->fetch();
    $pol = $conn->prepare('SELECT * FROM policies WHERE id = :id');
    $pol->execute(['id' => $claim['policy_id']]);
    $pol = $pol->fetch();
    $est = $conn->prepare('SELECT * FROM garage_estimates WHERE claim_id = :id ORDER BY id DESC LIMIT 1');
    $est->execute(['id' => $claimId]);
    $est = $est->fetch();

    $label = [
        'draft' => 'Draft', 'submitted' => 'Submitted', 'under_review' => 'Under review',
        'pending_docs' => 'Documents needed', 'approved' => 'Approved', 'rejected' => 'Rejected',
        'paid' => 'Paid', 'closed' => 'Closed',
        'collision' => 'Collision', 'theft' => 'Theft', 'vandalism' => 'Vandalism',
        'fire' => 'Fire', 'natural_disaster' => 'Natural disaster', 'other' => 'Other',
    ];
    $fmtUGX = static fn($n) => $n === null ? '—' : 'UGX ' . number_format((float)$n) . '';

    $lines = [];
    $lines[] = 'AVIC Insurance Co. — Claim summary';
    $lines[] = str_repeat('=', 62);
    $lines[] = '';
    $lines[] = 'Claim number: ' . $claim['claim_number'];
    $lines[] = 'Status:       ' . ($label[$claim['status']] ?? $claim['status']);
    $lines[] = 'Type:         ' . ($label[$claim['claim_type']] ?? $claim['claim_type']);
    $lines[] = 'Submitted:    ' . ($claim['submitted_at'] ? date('d M Y H:i', strtotime($claim['submitted_at'])) : 'not yet');
    $lines[] = '';
    $lines[] = 'Policy holder:';
    $lines[] = '  Name      ' . ($claimant['full_name'] ?? '—');
    $lines[] = '  Email     ' . ($claimant['email'] ?? '—');
    $lines[] = '  Phone     ' . ($claimant['phone'] ?? '—');
    $lines[] = '';
    $lines[] = 'Vehicle:';
    $lines[] = '  ' . trim(implode(' ', array_filter([$pol['vehicle_year'], $pol['vehicle_make'] ?? '', $pol['vehicle_model'] ?? '']))) . '  ·  ' . ($pol['vehicle_plate'] ?? '—');
    $lines[] = '  Policy    ' . ($pol['policy_number'] ?? '—');
    $lines[] = '';
    $lines[] = 'Incident:';
    $lines[] = '  Date      ' . ($claim['incident_date'] ? date('d M Y', strtotime($claim['incident_date'])) : '—');
    $lines[] = '  Location  ' . ($claim['incident_location'] ?: '—');
    $lines[] = '  Police ref ' . ($claim['police_report_ref'] ?: 'none');
    $lines[] = '  Description';
    $desc = $claim['incident_description'];
    while (strlen($desc) > 98) {
        $lines[] = '    ' . substr($desc, 0, 98);
        $desc = substr($desc, 98);
    }
    $lines[] = '    ' . $desc;
    $lines[] = '';
    $lines[] = 'Amount claimed:  ' . $fmtUGX($claim['estimated_damage'] ?? null);
    $lines[] = 'Amount approved: ' . $fmtUGX($claim['approved_amount'] ?? null);
    if ($est) {
        $lines[] = '';
        $lines[] = 'Garage estimate (' . ($est['garage_name'] ?? '—') . '): ' . $fmtUGX($est['total_estimate'] ?? null);
        $lines[] = '  Repair time  ' . ($est['repair_days'] ?? '—') . ' working days';
    }
    if ($claim['rejection_reason']) {
        $lines[] = '';
        $lines[] = 'Rejection reason:';
        $lines[] = '  ' . $claim['rejection_reason'];
    }
    $lines[] = '';
    $lines[] = str_repeat('_', 62);
    $lines[] = 'Generated ' . date('d M Y H:i') . ' by the AVIC portal.';

    $name = 'claim-' . $claim['claim_number'] . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    echo pdf_text_document($lines);
} catch (PDOException $e) {
    error_log($e->getMessage());
    api_json(500, ['message' => 'Could not build the PDF right now.']);
}

/** Minimal fixed-width text PDF (1 page, Courier). Returns the bytes. */
function pdf_text_document(array $lines): string
{
    $esc = static function (string $s): string {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    };
    $stream = "BT /F1 10 Tf 50 800 Td 14 TL 0.14 0.3 0.28 rg\n";
    foreach ($lines as $line) {
        $stream .= '(' . $esc($line) . ") Tj T*\n";
    }
    $stream .= 'ET';

    $objects = [];
    $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
    $objects[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
    $objects[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>";
    $objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>";
    $objects[5] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $num => $body) {
        $offsets[$num] = strlen($pdf);
        $pdf .= "$num 0 obj\n$body\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    foreach ($offsets as $off) {
        $pdf .= sprintf("%010d 00000 n \n", $off);
    }
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    return $pdf;
}