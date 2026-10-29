<div class="page-head"><div><h1>Report a loss</h1></div></div>
<?= Avic\Core\View::partial('fnol/_steps', ['step' => 1]) ?>
<section class="card form-narrow">
  <h2>Find the policy</h2>
  <?php if ($error): ?><div class="flash flash-error" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="/fnol/search">
    <?= Avic\Core\Csrf::field() ?>
    <div class="field"><label for="policy_number">Policy number</label><input id="policy_number" name="policy_number" value="<?= e($number) ?>" placeholder="POL-2026-0001" required autofocus></div>
    <button class="btn btn-primary">Continue</button>
  </form>
  <?php if (($user['role'] ?? '') === 'claimant'): ?><p class="small muted">Policy not listed? <a href="/policies">Link it to your account</a> first.</p><?php endif; ?>
</section>
