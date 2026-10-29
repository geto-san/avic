<?php /** claim page: header, flags, workflow actions, then the selected tab */
$csrf = Avic\Core\Csrf::field();
$id = (int)$claim['id'];
$labels = ['summary' => 'Summary', 'exposures' => 'Exposures', 'activities' => 'Activities', 'financials' => 'Financials', 'documents' => 'Documents', 'history' => 'History'];
?>
<div class="page-head">
  <div>
    <h1><?= e($claim['claim_number']) ?> <?= badge($claim['status']) ?> <?= $claim['close_outcome'] ? badge($claim['close_outcome']) : '' ?></h1>
    <p class="muted"><?= e(label($claim['loss_type'])) ?> · <?= e($claim['insured_name']) ?> · <?= e($claim['vehicle_plate']) ?></p>
  </div>
  <?php if ($adjuster && $actions): ?>
  <div class="actions">
    <?php foreach ($actions as $to): ?>
      <?php if ($to === 'closed'): ?>
        <form method="post" action="/claims/<?= $id ?>/transition" class="actions" data-confirm="Close this claim? Open activities are skipped and unused reserves are released.">
          <?= $csrf ?><input type="hidden" name="to" value="closed">
          <select name="outcome" aria-label="Outcome">
            <?php foreach (Avic\Domain\ClaimLifecycle::outcomesFor($claim['status']) as $o): ?><option value="<?= e($o) ?>"><?= e(label($o)) ?></option><?php endforeach; ?>
          </select>
          <button class="btn btn-danger">Close claim</button>
        </form>
      <?php else: ?>
        <form method="post" action="/claims/<?= $id ?>/transition" class="inline"><?= $csrf ?><input type="hidden" name="to" value="<?= e($to) ?>">
          <button class="btn <?= $to !== 'open' ? 'btn-primary' : '' ?>"><?= e(Avic\Domain\ClaimLifecycle::actionLabel($claim['status'], $to)) ?></button></form>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php if ($adjuster && $flags): ?>
  <div class="alert"><strong>Flagged for review</strong> — no payment can be issued until "Review flagged items" is completed.
    <ul><?php foreach ($flags as $fl): ?><li><?= e($fl) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<nav class="tabs" aria-label="Claim sections">
  <?php foreach ($tabs as $t): ?>
    <a href="/claims/<?= $id ?>?tab=<?= e($t) ?>" class="<?= $tab === $t ? 'on' : '' ?>"><?= e($labels[$t]) ?><?= $t === 'activities' && $openActivities ? '<span class="count">' . (int)$openActivities . '</span>' : '' ?></a>
  <?php endforeach; ?>
</nav>

<?= Avic\Core\View::partial('claims/tabs/' . $tab, get_defined_vars()) ?>
