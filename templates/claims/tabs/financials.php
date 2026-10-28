<?php $closed = $claim['status'] === 'closed'; $id = (int)$claim['id']; ?>
<section class="card"><h2>Reserves and payments by exposure</h2>
  <p class="muted small">Remaining reserve = reserved − paid. A payment is refused when it would exceed the remaining reserve. Policy limit: <?= e(money($claim['coverage_limit'])) ?>.</p>
<?php if (!$exposureTotals): ?><p class="empty">Add an exposure first.</p><?php else: ?>
<div class="table-wrap"><table class="data"><thead><tr><th>Exposure</th><th class="num">Reserved</th><th class="num">Paid</th><th class="num">Remaining</th><th class="num">Recovered</th></tr></thead><tbody>
<?php foreach ($exposureTotals as $x): ?><tr><td><?= e(label($x['exposure_type'])) ?><br><span class="muted small"><?= e($x['party_name']) ?></span></td>
  <td class="num"><?= e(money($x['reserve'])) ?></td><td class="num"><?= e(money($x['paid'])) ?></td><td class="num"><strong><?= e(money($x['available'])) ?></strong></td><td class="num"><?= e(money($x['recovered'])) ?></td></tr><?php endforeach; ?>
<tr><td><strong>Total</strong></td><td class="num"><strong><?= e(money($totals['reserve'])) ?></strong></td><td class="num"><strong><?= e(money($totals['paid'])) ?></strong></td><td class="num"><strong><?= e(money($totals['available'])) ?></strong></td><td class="num"><strong><?= e(money($totals['recovered'])) ?></strong></td></tr>
</tbody></table></div><?php endif; ?></section>

<?php if ($exposureTotals && !$closed): $open = array_filter($exposureTotals, fn($x) => $x['status'] === 'open'); ?>
<div class="grid grid-2">
  <section class="card"><h2>Set reserve</h2>
    <form method="post" action="/claims/<?= $id ?>/reserve"><?= $csrf ?>
      <div class="field"><label for="r_x">Exposure</label><select id="r_x" name="exposure_id"><?php foreach ($open as $x): ?><option value="<?= (int)$x['id'] ?>"><?= e(label($x['exposure_type'])) ?> · <?= e($x['party_name']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="r_a">Set total reserve to (UGX)</label><input id="r_a" class="money-input" name="amount" inputmode="numeric" required><div class="help">The ledger records the difference from the current reserve.</div></div>
      <div class="field"><label for="r_m">Reason</label><input id="r_m" name="memo" maxlength="255"></div>
      <button class="btn btn-primary">Set reserve</button></form></section>
  <section class="card"><h2>Issue payment</h2>
    <form method="post" action="/claims/<?= $id ?>/payments" data-confirm="Issue this payment? It cannot be undone."><?= $csrf ?>
      <div class="field"><label for="p_x">From exposure</label><select id="p_x" name="exposure_id"><?php foreach ($open as $x): ?><option value="<?= (int)$x['id'] ?>"><?= e(label($x['exposure_type'])) ?> · <?= e($x['party_name']) ?> (<?= e(money($x['available'])) ?> left)</option><?php endforeach; ?></select></div>
      <div class="cols">
        <div class="field"><label for="p_a">Amount (UGX)</label><input id="p_a" class="money-input" name="amount" inputmode="numeric" required></div>
        <div class="field"><label for="p_m">Method</label><select id="p_m" name="method"><?php foreach (Avic\Domain\Catalog::PAYMENT_METHODS as $k => $t): ?><option value="<?= e($k) ?>"><?= e($t) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="field"><label for="p_p">Payee</label><input id="p_p" name="payee" required maxlength="120"></div>
      <div class="field"><label for="p_r">Reference (optional)</label><input id="p_r" name="reference" maxlength="80"></div>
      <button class="btn btn-primary">Issue payment</button></form></section>
</div>
<?php endif; ?>

<?php if ($exposureTotals && $claim['status'] !== 'open'): ?>
<details class="more card"><summary>Record a recovery (subrogation or salvage)</summary>
  <form method="post" action="/claims/<?= $id ?>/recoveries"><?= $csrf ?>
    <div class="cols">
      <div class="field"><label for="v_x">Exposure</label><select id="v_x" name="exposure_id"><?php foreach ($exposureTotals as $x): ?><option value="<?= (int)$x['id'] ?>"><?= e(label($x['exposure_type'])) ?> · <?= e($x['party_name']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="v_a">Amount (UGX)</label><input id="v_a" class="money-input" name="amount" inputmode="numeric" required></div>
      <div class="field"><label for="v_s">Recovered from</label><input id="v_s" name="source" required maxlength="120"></div>
    </div>
    <button class="btn">Record recovery</button></form></details>
<?php endif; ?>

<section class="card"><h2>Ledger</h2><p class="muted small">Append-only: entries are never edited or removed.</p>
<?php if (!$ledger): ?><p class="empty">No transactions yet.</p><?php else: ?>
<div class="table-wrap"><table class="data"><thead><tr><th>When</th><th>Type</th><th>Exposure</th><th>Detail</th><th>By</th><th class="num">Amount</th></tr></thead><tbody>
<?php foreach ($ledger as $t): ?><tr><td class="nowrap"><?= e(fmt_datetime($t['created_at'])) ?></td><td><?= e(ucfirst($t['type'])) ?></td><td><?= e($t['party_name']) ?></td>
  <td><?php if ($t['type'] === 'payment'): ?><?= e($t['payee']) ?> · <?= e(label($t['payment_method'])) ?> · <?= e($t['reference']) ?><?php elseif ($t['type'] === 'recovery'): ?><?= e($t['payee']) ?><?php else: ?><?= e($t['memo'] ?: '—') ?><?php endif; ?></td>
  <td><?= e($t['actor_name'] ?: '—') ?></td><td class="num"><?= $t['amount'] < 0 ? '−' : '' ?><?= e(money(abs((int)$t['amount']))) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></section>
