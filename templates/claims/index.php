<?php $isAdj = $user['role'] === 'adjuster'; $base = '/claims?'; ?>
<div class="page-head"><div><h1><?= $isAdj ? 'Claims' : 'My claims' ?></h1></div>
  <a class="btn btn-primary" href="/fnol"><?= $isAdj ? 'New claim' : 'Report a loss' ?></a></div>
<form method="get" action="/claims" class="filters">
  <?php if ($isAdj): ?>
    <div class="seg" role="group" aria-label="Which claims">
      <?php foreach (['mine' => 'Mine', 'unassigned' => 'Unassigned', 'all' => 'All'] as $k => $t): ?>
        <a href="/claims?<?= e(http_build_query(['view' => $k, 'status' => $f['status'], 'q' => $f['q']])) ?>" class="<?= $f['view'] === $k ? 'on' : '' ?>"><?= e($t) ?></a>
      <?php endforeach; ?>
    </div>
    <input type="hidden" name="view" value="<?= e($f['view']) ?>">
  <?php endif; ?>
  <div class="field"><label class="small muted" for="status">Status</label>
    <select id="status" name="status"><option value="">Any</option>
      <?php foreach (Avic\Domain\Catalog::STATUSES as $k => $t): ?><option value="<?= e($k) ?>"<?= selected($f['status'], $k) ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label class="small muted" for="q">Search</label><input id="q" name="q" value="<?= e($f['q']) ?>" placeholder="Claim no., name, plate"></div>
  <button class="btn">Filter</button>
</form>
<section class="card">
<?php if (!$result['rows']): ?><p class="empty">No claims match.</p><?php else: ?>
<div class="table-wrap"><table class="data"><thead><tr><th>Claim</th><th>Insured</th><th>Loss</th><th>Date of loss</th><th>Status</th><?php if ($isAdj): ?><th>Handler</th><?php endif; ?></tr></thead><tbody>
<?php foreach ($result['rows'] as $c): ?>
  <tr><td><a href="/claims/<?= (int)$c['id'] ?>"><?= e($c['claim_number']) ?></a><?= $c['flags'] && $isAdj ? ' ' . badge('flag', 'Flagged') : '' ?></td>
    <td><?= e($c['insured_name']) ?><br><span class="muted small"><?= e($c['vehicle_plate']) ?></span></td>
    <td><?= e(label($c['loss_type'])) ?></td><td class="nowrap"><?= e(fmt_date($c['loss_date'])) ?></td>
    <td><?= badge($c['status']) ?><?= $c['close_outcome'] ? ' ' . badge($c['close_outcome']) : '' ?></td>
    <?php if ($isAdj): ?><td><?= $c['assignee_name'] ? e($c['assignee_name']) : '<span class="muted">Unassigned</span>' ?></td><?php endif; ?></tr>
<?php endforeach; ?></tbody></table></div>
<?php if ($result['pages'] > 1): ?>
  <div class="pagination"><span class="muted small">Page <?= (int)$result['page'] ?> of <?= (int)$result['pages'] ?> · <?= (int)$result['total'] ?> claims</span>
    <?php if ($result['page'] > 1): ?><a class="btn btn-sm" href="/claims?<?= e(http_build_query($f, '', '&', PHP_QUERY_RFC3986) . '&page=' . ($result['page'] - 1)) ?>">Previous</a><?php endif; ?>
    <?php if ($result['page'] < $result['pages']): ?><a class="btn btn-sm" href="/claims?<?= e(http_build_query($f, '', '&', PHP_QUERY_RFC3986) . '&page=' . ($result['page'] + 1)) ?>">Next</a><?php endif; ?></div>
<?php endif; endif; ?>
</section>
