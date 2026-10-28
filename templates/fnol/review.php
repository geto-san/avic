<div class="page-head"><div><h1>Review and submit</h1></div></div>
<?= Avic\Core\View::partial('fnol/_steps', ['step' => 3]) ?>
<section class="card form-card">
  <h2>Loss</h2>
  <dl class="kv">
    <dt>Policy</dt><dd><?= e($policy['policy_number']) ?> · <?= e($policy['vehicle_make'] . ' ' . $policy['vehicle_model']) ?> (<?= e($policy['vehicle_plate']) ?>)</dd>
    <dt>Date of loss</dt><dd><?= e(fmt_date($data['loss_date'])) ?></dd>
    <dt>Type</dt><dd><?= e(label($data['loss_type'])) ?> · <?= e(label($data['loss_region'])) ?> region</dd>
    <dt>Location</dt><dd><?= e($data['loss_location']) ?></dd>
    <dt>Description</dt><dd><?= nl2br(e($data['description'])) ?></dd>
    <dt>Police reference</dt><dd><?= e($data['police_ref'] ?: '—') ?></dd>
  </dl>
</section>
<section class="card form-card">
  <h2>Exposures</h2>
  <table class="data"><thead><tr><th>Type</th><th>Party</th><th>Details</th></tr></thead><tbody>
    <?php foreach ($data['exposures'] as $x): ?><tr><td><?= e(label($x['type'])) ?></td><td><?= e($x['party']) ?></td><td><?= e($x['description'] ?: '—') ?></td></tr><?php endforeach; ?>
  </tbody></table>
</section>
<div class="actions">
  <form method="post" action="/fnol/submit" class="inline"><?= Avic\Core\Csrf::field() ?><button class="btn btn-primary">Submit claim</button></form>
  <a class="btn" href="/fnol/details">Back and edit</a>
</div>
