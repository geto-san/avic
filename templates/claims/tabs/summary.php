<div class="grid grid-2">
  <section class="card"><h2>Loss</h2><dl class="kv">
    <dt>Date of loss</dt><dd><?= e(fmt_date($claim['loss_date'])) ?></dd>
    <dt>Type</dt><dd><?= e(label($claim['loss_type'])) ?></dd>
    <dt>Location</dt><dd><?= e($claim['loss_location']) ?> <span class="muted">(<?= e(label($claim['loss_region'])) ?> region)</span></dd>
    <dt>Description</dt><dd><?= nl2br(e($claim['description'])) ?></dd>
    <dt>Police reference</dt><dd><?= e($claim['police_ref'] ?: '—') ?></dd>
    <dt>Reported</dt><dd><?= e(fmt_datetime($claim['created_at'])) ?> by <?= e($claim['reporter_name']) ?></dd>
  </dl></section>
  <section class="card"><h2>Policy</h2><dl class="kv">
    <dt>Policy</dt><dd><?= e($claim['policy_number']) ?></dd>
    <dt>Insured</dt><dd><?= e($claim['insured_name']) ?></dd>
    <dt>Vehicle</dt><dd><?= e($claim['vehicle_make'] . ' ' . $claim['vehicle_model']) ?> <?= $claim['vehicle_year'] ? '(' . (int)$claim['vehicle_year'] . ')' : '' ?> · <?= e($claim['vehicle_plate']) ?></dd>
    <dt>Cover</dt><dd><?= e(label($claim['coverage_type'])) ?> · limit <?= e(money($claim['coverage_limit'])) ?></dd>
    <dt>Period</dt><dd><?= e(fmt_date($claim['start_date'])) ?> – <?= e(fmt_date($claim['end_date'])) ?></dd>
  </dl></section>
</div>

<?php if ($adjuster): ?>
<section class="card"><h2>Handler</h2>
  <p>Currently: <strong><?= $claim['assignee_name'] ? e($claim['assignee_name']) : 'Unassigned' ?></strong></p>
  <?php if ($claim['status'] !== 'closed'): ?>
  <form method="post" action="/claims/<?= (int)$claim['id'] ?>/assign" class="actions">
    <?= $csrf ?>
    <select name="assignee" aria-label="Assign to">
      <option value="">Unassigned queue</option>
      <?php foreach ($adjusters as $a): ?><option value="<?= (int)$a['id'] ?>"<?= selected($claim['assigned_to'], $a['id']) ?>><?= e($a['full_name']) ?><?= $a['region'] ? ' · ' . e(label($a['region'])) : '' ?></option><?php endforeach; ?>
    </select>
    <button class="btn">Reassign</button>
    <?php if ((int)$claim['assigned_to'] !== (int)$user['id']): ?><button class="btn btn-primary" name="assignee" value="me">Take this claim</button><?php endif; ?>
  </form>
  <?php endif; ?>
</section>
<section class="card"><h2>Money at a glance</h2>
  <div class="grid grid-4">
    <div><div class="muted small">Reserved</div><strong><?= e(money($totals['reserve'])) ?></strong></div>
    <div><div class="muted small">Paid</div><strong><?= e(money($totals['paid'])) ?></strong></div>
    <div><div class="muted small">Remaining reserve</div><strong><?= e(money($totals['available'])) ?></strong></div>
    <div><div class="muted small">Recovered</div><strong><?= e(money($totals['recovered'])) ?></strong></div>
  </div>
</section>
<?php else: ?>
<section class="card"><h2>Progress</h2>
  <p>Your claim is <strong><?= e(strtolower(label($claim['status']))) ?></strong><?= $claim['close_outcome'] ? ' (' . e(strtolower(label($claim['close_outcome']))) . ')' : '' ?>.
    <?= $claim['assignee_name'] ? 'It is being handled by ' . e($claim['assignee_name']) . '.' : 'It is waiting to be assigned to an adjuster.' ?></p>
  <h3>Payments to you</h3>
  <?php if (!$payments): ?><p class="muted">No payments have been made yet.</p><?php else: ?>
    <table class="data"><thead><tr><th>Date</th><th>Method</th><th class="num">Amount</th></tr></thead><tbody>
    <?php foreach ($payments as $pay): ?><tr><td><?= e(fmt_date($pay['created_at'])) ?></td><td><?= e(label($pay['payment_method'])) ?></td><td class="num"><?= e(money($pay['amount'])) ?></td></tr><?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>
</section>
<?php endif; ?>
