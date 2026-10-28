<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use Avic\Core\Migrator;

if (in_array('--status', $argv, true)) {
    $s = Migrator::status();
    foreach ($s['applied'] as $f) {
        echo "applied  $f\n";
    }
    foreach ($s['pending'] as $f) {
        echo "pending  $f\n";
    }
    exit(0);
}
$done = Migrator::run();
echo $done ? 'Applied: ' . implode(', ', $done) . "\n" : "Nothing to apply.\n";
