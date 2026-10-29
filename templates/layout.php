<?php
/** @var string $title @var string $content @var ?array $user @var array $flashes */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$nav = [];
if ($user) {
    $nav = $user['role'] === 'adjuster'
        ? [['/dashboard', 'Dashboard'], ['/claims', 'Claims'], ['/activities', 'Activities'], ['/fnol', 'New claim']]
        : [['/dashboard', 'Dashboard'], ['/claims', 'My claims'], ['/policies', 'My policies'], ['/fnol', 'Report a loss']];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · AVIC Claims</title>
  <link rel="stylesheet" href="/assets/css/app.css">
  <script src="/assets/js/app.js" defer></script>
</head>
<body>
<header class="topbar"><div class="topbar-in">
  <a class="brand" href="/">AVIC <span>Claims</span></a>
  <nav class="nav" aria-label="Main">
    <?php foreach ($nav as [$href, $text]):
        $on = $href === '/dashboard' ? $path === '/dashboard' : str_starts_with($path, $href); ?>
      <a href="<?= e($href) ?>"<?= $on ? ' class="active" aria-current="page"' : '' ?>><?= e($text) ?></a>
    <?php endforeach; ?>
  </nav>
  <?php if ($user): ?>
    <div class="userbox">
      <span><?= e($user['full_name']) ?> <span class="muted small">· <?= e(ucfirst($user['role'])) ?></span></span>
      <form method="post" action="/logout" class="inline"><?= Avic\Core\Csrf::field() ?><button class="btn btn-sm">Sign out</button></form>
    </div>
  <?php endif; ?>
</div></header>
<main class="container">
  <?php foreach ($flashes as $f): ?>
    <div class="flash flash-<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div>
  <?php endforeach; ?>
  <?= $content ?>
</main>
</body>
</html>
