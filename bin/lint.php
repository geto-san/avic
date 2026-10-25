<?php
declare(strict_types=1);

/**
 * Syntax-check every PHP file in the project (vendor/ and .git/ excluded).
 *
 *   php bin/lint.php        (or: composer lint)
 *
 * Silent on success; lists the broken files and exits 1 otherwise.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$files = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    static fn(SplFileInfo $f) => !in_array($f->getFilename(), ['vendor', '.git', 'node_modules'], true)
));

$checked = 0;
$broken = [];
foreach ($files as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $checked++;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()) . ' 2>&1', $output, $code);
    if ($code !== 0) {
        $broken[] = $file->getPathname() . "\n    " . implode("\n    ", $output);
    }
    $output = [];
}

if ($broken) {
    fwrite(STDERR, "Syntax errors:\n" . implode("\n", $broken) . "\n");
    exit(1);
}
echo "OK: $checked PHP files, no syntax errors.\n";
