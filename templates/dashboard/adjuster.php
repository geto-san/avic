<div class="page-head"><div><h1>Dashboard</h1><p class="muted">Your work for <?= e(date('l, j F')) ?>.</p></div>
  <a class="btn btn-primary" href="/fnol">New claim</a></div>
<div class="grid grid-4">
  <a class="stat" href="/claims?view=mine"><div class="n"><?= (int)$stats['mine'] ?></div><div class="l">My open claims</div></a>
  <a class="stat" href="/claims?view=unassigned"><div class="n"><?= (int)$stats['unassigned'] ?></div><div class="l">Unassigned claims</div></a>
  <a class="stat" href="/activities"><div class="n"><?= (int)$stats['activities'] ?></div><div class="l">Open activities</div></a>
  <a class="stat<?= $stats['overdue'] ? ' alert' : '' ?>" href="/activities"><div class="n"><?= (int)$stats['overdue'] ?></div><div class="l">Overdue activities</div></a>
</div>
<div class="grid grid-2">
  <section class="card">
    <h2>Next activities</h2>
    <?php if (!$activities): ?><p class="empty">Nothing due. Nice work.</p><?php else: ?>
    <div class="table-wrap"><table class="data"><thead><tr><th>Activity</th><th>Claim</th><th>Due</th></tr></thead><tbody>
      <?php foreach ($activities as $a): ?>
        <tr><td><?= e($a['subject']) ?> <?= badge($a['priority']) ?></td>
          <td><a href="/claims/<?= (int)$a['claim_id'] ?>?tab=activities"><?= e($a['claim_number']) ?></a></td>
          <td class="nowrap<?= $a['due_date'] < date('Y-m-d') ? ' overdue' : '' ?>"><?= e(fmt_date($a['due_date'])) ?></td></tr>
      <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </section>
  <section class="card">
    <h2>Waiting for an adjuster</h2>
    <?php if (!$unassigned): ?><p class="empty">The unassigned queue is empty.</p><?php else: ?>
    <div class="table-wrap"><table class="data"><thead><tr><th>Claim</th><th>Insured</th><th>Loss</th></tr></thead><tbody>
      <?php foreach ($unassigned as $c): ?>
        <tr><td><a href="/claims/<?= (int)$c['id'] ?>"><?= e($c['claim_number']) ?></a></td><td><?= e($c['insured_name']) ?></td><td><?= e(label($c['loss_type'])) ?></td></tr>
      <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </section>
</div>
