<div class="page-head"><div><h1>Hello, <?= e(explode(' ', $user['full_name'])[0]) ?></h1><p class="muted">Track your claims or report a new loss.</p></div>
  <?php if ($policies): ?><a class="btn btn-primary" href="/fnol">Report a loss</a><?php endif; ?></div>
<?php if (!$policies): ?>
  <div class="card empty"><h2>Link your policy first</h2><p>Enter your policy number and number plate so we can verify your cover.</p><a class="btn btn-primary" href="/policies">Link a policy</a></div>
<?php else: ?>
  <section class="card"><h2>Your claims</h2>
    <?php if (!$claims): ?><p class="empty">You have not reported any losses.</p><?php else: ?>
    <div class="table-wrap"><table class="data"><thead><tr><th>Claim</th><th>Loss</th><th>Date of loss</th><th>Status</th></tr></thead><tbody>
      <?php foreach ($claims as $c): ?>
        <tr><td><a href="/claims/<?= (int)$c['id'] ?>"><?= e($c['claim_number']) ?></a></td><td><?= e(label($c['loss_type'])) ?></td><td><?= e(fmt_date($c['loss_date'])) ?></td>
          <td><?= badge($c['status']) ?><?= $c['close_outcome'] ? ' ' . badge($c['close_outcome']) : '' ?></td></tr>
      <?php endforeach; ?></tbody></table></div>
    <?php endif; ?></section>
  <section class="card"><h2>Your policies</h2>
    <?= Avic\Core\View::partial('policies/_list', ['policies' => $policies]) ?></section>
<?php endif; ?>
