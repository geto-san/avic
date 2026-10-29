<div class="card empty">
  <h1><?= e($code) ?></h1>
  <p><?= e($message) ?></p>
  <?php if (!empty($detail)): ?><p class="small muted"><?= e($detail) ?></p><?php endif; ?>
  <p><a class="btn" href="/">Back to the start</a></p>
</div>
