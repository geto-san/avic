<section class="card"><h2>Activities</h2>
<?php if (!$activities): ?><p class="empty">No activities.</p><?php else: ?>
<div class="table-wrap"><table class="data"><thead><tr><th>Activity</th><th>Priority</th><th>Assigned</th><th>Due</th><th></th></tr></thead><tbody>
<?php foreach ($activities as $a): $open = $a['status'] === 'open'; ?>
  <tr class="<?= $open ? '' : 'row-done' ?>">
    <td><strong><?= e($a['subject']) ?></strong><?= $a['mandatory'] ? ' <span class="muted small">(mandatory)</span>' : '' ?>
      <?php if ($a['party_name']): ?><span class="muted small"> · <?= e($a['party_name']) ?></span><?php endif; ?>
      <?php if ($a['description']): ?><br><span class="muted small"><?= e($a['description']) ?></span><?php endif; ?></td>
    <td><?= badge($a['priority']) ?></td>
    <td><?= e($a['assignee_name'] ?: '—') ?></td>
    <td class="nowrap<?= $open && $a['due_date'] < date('Y-m-d') ? ' overdue' : '' ?>"><?= e(fmt_date($a['due_date'])) ?></td>
    <td class="actions"><?php if ($open): ?>
      <form method="post" action="/activities/<?= (int)$a['id'] ?>/complete" class="inline"><?= $csrf ?><button class="btn btn-sm btn-primary">Complete</button></form>
      <?php if (!$a['mandatory']): ?><form method="post" action="/activities/<?= (int)$a['id'] ?>/skip" class="inline"><?= $csrf ?><button class="btn btn-sm">Skip</button></form><?php endif; ?>
    <?php else: ?><?= badge($a['status']) ?><?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php if ($claim['status'] !== 'closed'): ?>
<section class="card form-card"><h2>Add an activity</h2>
  <form method="post" action="/claims/<?= (int)$claim['id'] ?>/activities">
    <?= $csrf ?>
    <div class="field"><label for="a_subject">Subject</label><input id="a_subject" name="subject" required maxlength="150"></div>
    <div class="cols">
      <div class="field"><label for="a_priority">Priority</label><select id="a_priority" name="priority">
        <?php foreach (Avic\Domain\Catalog::PRIORITIES as $k => $t): ?><option value="<?= e($k) ?>"<?= selected('normal', $k) ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="a_due">Due date</label><input id="a_due" type="date" name="due_date" value="<?= e(date('Y-m-d', strtotime('+2 days'))) ?>" required></div>
    </div>
    <div class="field"><label for="a_desc">Notes</label><input id="a_desc" name="description" maxlength="500"></div>
    <button class="btn btn-primary">Add activity</button>
  </form></section>
<?php endif; ?>
