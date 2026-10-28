<?php
$describe = function (array $h): string {
    $d = $h['detail'];
    return match ($h['action']) {
        'claim.created' => 'Claim reported',
        'claim.status_changed' => 'Status: ' . label($d['from'] ?? '') . ' → ' . label($d['to'] ?? '') . (!empty($d['outcome']) ? ' (' . label($d['outcome']) . ')' : ''),
        'claim.assigned' => !empty($d['by']) ? 'Assigned automatically by the assignment rules' : 'Assignment changed',
        'claim.flagged' => 'Flagged: ' . ($d['reason'] ?? ''),
        'claim.flags_cleared' => 'Flagged items reviewed',
        'exposure.added' => 'Exposure added: ' . label($d['type'] ?? '') . ' · ' . ($d['party'] ?? ''),
        'activity.created' => 'Activity added: ' . ($d['subject'] ?? ''),
        'activity.completed' => 'Activity completed: ' . ($d['subject'] ?? ''),
        'activity.skipped' => 'Activity skipped: ' . ($d['subject'] ?? ''),
        'financial.reserve_set' => 'Reserve for ' . ($d['party'] ?? $d['exposure'] ?? '') . ': ' . money($d['from'] ?? 0) . ' → ' . money($d['to'] ?? 0),
        'financial.payment_issued' => 'Payment of ' . money($d['amount'] ?? 0) . ' to ' . ($d['payee'] ?? ''),
        'financial.recovery_recorded' => 'Recovery of ' . money($d['amount'] ?? 0) . ' from ' . ($d['source'] ?? ''),
        'document.uploaded' => 'Document uploaded: ' . ($d['name'] ?? ''),
        default => $h['action'],
    };
};
?>
<section class="card"><h2><?= $adjuster ? 'Audit history' : 'Status history' ?></h2>
<ul class="timeline">
<?php foreach ($history as $h): ?>
  <li><?= e($describe($h)) ?><br><span class="muted small"><?= e(fmt_datetime($h['created_at'])) ?> · <?= e($h['actor_name'] ?: 'System') ?></span></li>
<?php endforeach; ?></ul></section>
