<?php if (!empty($flash['success'])): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($flash['success']); ?></div>
<?php endif; ?>
<?php if (!empty($flash['error'])): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($flash['error']); ?></div>
<?php endif; ?>
<?php if (!empty($flash['warning'])): ?>
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($flash['warning']); ?></div>
<?php endif; ?>