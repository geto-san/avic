<?php
$err = fn(string $f) => isset($errors[$f]) ? '<div class="err">' . e($errors[$f]) . '</div>' : '';
$inv = fn(string $f) => isset($errors[$f]) ? ' invalid' : '';
$val = fn(string $f) => e($old[$f] ?? '');
$rows = $old['exposures'] ?? [];
?>
<div class="page-head"><div><h1>Report a loss</h1></div></div>
<?= Avic\Core\View::partial('fnol/_steps', ['step' => 2]) ?>
<section class="card"><dl class="kv">
  <dt>Policy</dt><dd><?= e($policy['policy_number']) ?> · <?= e(label($policy['coverage_type'])) ?></dd>
  <dt>Vehicle</dt><dd><?= e($policy['vehicle_make'] . ' ' . $policy['vehicle_model']) ?> (<?= e($policy['vehicle_plate']) ?>)</dd>
</dl></section>
<form method="post" action="/fnol/details" class="card form-card" novalidate>
  <?= Avic\Core\Csrf::field() ?>
  <h2>What happened</h2>
  <div class="cols">
    <div class="field<?= $inv('loss_date') ?>"><label for="loss_date">Date of loss</label><input id="loss_date" type="date" name="loss_date" max="<?= e(date('Y-m-d')) ?>" value="<?= $val('loss_date') ?>"><?= $err('loss_date') ?></div>
    <div class="field<?= $inv('loss_type') ?>"><label for="loss_type">Type of loss</label>
      <select id="loss_type" name="loss_type"><option value="">Choose…</option>
        <?php foreach (Avic\Domain\Catalog::LOSS_TYPES as $k => $t): ?><option value="<?= e($k) ?>"<?= selected($old['loss_type'] ?? '', $k) ?>><?= e($t) ?></option><?php endforeach; ?></select><?= $err('loss_type') ?></div>
    <div class="field<?= $inv('loss_region') ?>"><label for="loss_region">Region</label>
      <select id="loss_region" name="loss_region"><option value="">Choose…</option>
        <?php foreach (Avic\Domain\Catalog::REGIONS as $k => $t): ?><option value="<?= e($k) ?>"<?= selected($old['loss_region'] ?? '', $k) ?>><?= e($t) ?></option><?php endforeach; ?></select><?= $err('loss_region') ?></div>
  </div>
  <div class="field<?= $inv('loss_location') ?>"><label for="loss_location">Where did it happen?</label><input id="loss_location" name="loss_location" value="<?= $val('loss_location') ?>" placeholder="Road, town or landmark"><?= $err('loss_location') ?></div>
  <div class="field<?= $inv('description') ?>"><label for="description">Describe what happened</label><textarea id="description" name="description"><?= $val('description') ?></textarea><?= $err('description') ?></div>
  <div class="field"><label for="police_ref">Police reference (if reported)</label><input id="police_ref" name="police_ref" value="<?= $val('police_ref') ?>"><div class="help">Required later for theft and vandalism claims.</div></div>

  <h2>Exposures</h2>
  <p class="muted small">An exposure is one thing that was damaged or one person who was hurt. Add at least one; leave the other rows empty if you do not need them.</p>
  <?php if (isset($errors['exposures'])): ?><div class="flash flash-error" role="alert"><?= e($errors['exposures']) ?></div><?php endif; ?>
  <?php for ($i = 0; $i < 3; $i++): $x = $rows[$i] ?? []; ?>
    <div class="exposure-row">
      <h3>Exposure <?= $i + 1 ?><?= $i ? ' (optional)' : '' ?></h3>
      <div class="cols">
        <div class="field"><label for="x<?= $i ?>t">What was affected</label>
          <select id="x<?= $i ?>t" name="exposures[<?= $i ?>][type]"><option value="">Choose…</option>
            <?php foreach (Avic\Domain\Catalog::EXPOSURE_TYPES as $k => $t): ?><option value="<?= e($k) ?>"<?= selected($x['type'] ?? '', $k) ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="x<?= $i ?>p">Owner or injured person</label><input id="x<?= $i ?>p" name="exposures[<?= $i ?>][party]" value="<?= e($x['party'] ?? '') ?>"></div>
      </div>
      <div class="field"><label for="x<?= $i ?>d">Damage or injury</label><input id="x<?= $i ?>d" name="exposures[<?= $i ?>][description]" value="<?= e($x['description'] ?? '') ?>"></div>
    </div>
  <?php endfor; ?>
  <div class="actions"><button class="btn btn-primary">Review</button><a class="btn" href="/fnol">Start over</a></div>
</form>
