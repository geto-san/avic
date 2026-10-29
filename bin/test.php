<?php
declare(strict_types=1);

// Usage: php bin/test.php [name-filter]    (DB tests need the *_test database, see README)
require dirname(__DIR__) . '/tests/bootstrap.php';

$filter = $argv[1] ?? '';
foreach (glob(dirname(__DIR__) . '/tests/*Test.php') ?: [] as $file) {
    echo basename($file, '.php') . "\n";
    require $file;
    T::run($filter);
}
echo "\n" . T::$passed . ' passed, ' . count(T::$failed) . " failed\n";
exit(T::$failed ? 1 : 0);
