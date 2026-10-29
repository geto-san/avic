<div class="page-head"><div><h1>My policies</h1></div></div>
<?php if ($policies): ?><section class="card"><?= Avic\Core\View::partial('policies/_list', ['policies' => $policies]) ?></section><?php endif; ?>
<section class="card form-narrow">
  <h2>Link a policy</h2>
  <p class="muted small">Enter the policy number from your certificate and the number plate of the insured vehicle.</p>
  <form method="post" action="/policies/link">
    <?= Avic\Core\Csrf::field() ?>
    <div class="field"><label for="policy_number">Policy number</label><input id="policy_number" name="policy_number" placeholder="POL-2026-0001" required></div>
    <div class="field"><label for="plate">Vehicle number plate</label><input id="plate" name="plate" placeholder="UBA 123X" required></div>
    <button class="btn btn-primary">Link policy</button>
  </form>
</section>
