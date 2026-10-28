<section class="card"><h2>Exposures</h2>
<?php if (!$exposures): ?><p class="empty">No exposures.</p><?php else: ?>
<div class="table-wrap"><table class="data"><thead><tr><th>Type</th><th>Party</th><th>Details</th><th>Status</th></tr></thead><tbody>
<?php foreach ($exposures as $x): ?><tr><td><?= e(label($x['exposure_type'])) ?></td><td><?= e($x['party_name']) ?></td><td><?= e($x['description'] ?: '—') ?></td><td><?= badge($x['status']) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></section>
<?php if ($adjuster && $claim['status'] !== 'closed'): ?>
<section class="card form-card"><h2>Add an exposure</h2>
  <form method="post" action="/claims/<?= (int)$claim['id'] ?>/exposures">
    <?= $csrf ?>
    <div class="cols">
      <div class="field"><label for="x_type">What was affected</label><select id="x_type" name="type" required>
        <?php foreach (Avic\Domain\Catalog::EXPOSURE_TYPES as $k => $t): ?><option value="<?= e($k) ?>"><?= e($t) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="x_party">Owner or injured person</label><input id="x_party" name="party" required></div>
    </div>
    <div class="field"><label for="x_desc">Damage or injury</label><input id="x_desc" name="description"></div>
    <button class="btn btn-primary">Add exposure</button>
  </form></section>
<?php endif; ?>
