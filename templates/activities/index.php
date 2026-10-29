<div class="page-head"><div><h1>My activities</h1><p class="muted">Open tasks across all your claims, soonest first.</p></div></div>
<section class="card">
<?php if (!$activities): ?><p class="empty">No open activities.</p><?php else: ?>
<div class="table-wrap"><table class="data"><thead><tr><th>Activity</th><th>Claim</th><th>Priority</th><th>Due</th><th></th></tr></thead><tbody>
<?php foreach ($activities as $a): $late = $a['due_date'] < date('Y-m-d'); ?>
  <tr><td><strong><?= e($a['subject']) ?></strong><?= $a['mandatory'] ? ' <span class="muted small">(mandatory)</span>' : '' ?>
        <?php if ($a['description']): ?><br><span class="muted small"><?= e($a['description']) ?></span><?php endif; ?></td>
    <td><a href="/claims/<?= (int)$a['claim_id'] ?>?tab=activities"><?= e($a['claim_number']) ?></a><br><span class="muted small"><?= e($a['insured_name']) ?></span></td>
    <td><?= badge($a['priority']) ?></td>
    <td class="nowrap<?= $late ? ' overdue' : '' ?>"><?= e(fmt_date($a['due_date'])) ?></td>
    <td class="actions">
      <form method="post" action="/activities/<?= (int)$a['id'] ?>/complete" class="inline"><?= Avic\Core\Csrf::field() ?><input type="hidden" name="back" value="list"><button class="btn btn-sm btn-primary">Complete</button></form>
      <?php if (!$a['mandatory']): ?><form method="post" action="/activities/<?= (int)$a['id'] ?>/skip" class="inline"><?= Avic\Core\Csrf::field() ?><input type="hidden" name="back" value="list"><button class="btn btn-sm">Skip</button></form><?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php endif; ?>
</section>
