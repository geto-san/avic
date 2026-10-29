<ol class="steps">
  <?php foreach ([1 => 'Find policy', 2 => 'Loss details', 3 => 'Review & submit'] as $n => $t): ?>
    <li class="<?= $n === $step ? 'on' : ($n < $step ? 'done' : '') ?>"><?= $n ?>. <?= e($t) ?></li>
  <?php endforeach; ?>
</ol>
