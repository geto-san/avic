<?php
declare(strict_types=1);

// php -l on every PHP file in the project (no dependencies needed).
$root = dirname(__DIR__);
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$bad = 0;
foreach ($it as $f) {
    $p = $f->getPathname();
    if (!str_ends_with($p, '.php') || str_contains($p, '/.git/') || str_contains($p, '/vendor/')) {
        continue;
    }
    exec('php -l ' . escapeshellarg($p) . ' 2>&1', $out, $code);
    if ($code !== 0) {
        echo implode("\n", $out) . "\n";
        $bad++;
    }
    $out = [];
}
echo $bad ? "$bad file(s) with syntax errors\n" : "All PHP files OK\n";
exit($bad ? 1 : 0);
