<?php
declare(strict_types=1);

/**
 * PHPUnit bootstrap.
 *
 * - loads Composer's autoloader if present, plus a tiny one for tests/ (Avic\Tests\*);
 * - points every in-process include AND the throwaway test server at a dedicated
 *   TEST database, never the dev or production one.
 *
 * Override the connection with AVIC_TEST_DB_HOST / _PORT / _NAME / _USER / _PASS.
 * The database name must end in "_test": the feature tests TRUNCATE every table.
 */

$root = dirname(__DIR__);

if (is_file($root . '/vendor/autoload.php')) {
    require $root . '/vendor/autoload.php';
}

spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'Avic\\Tests\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = $root . '/tests/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$testDb = [
    'AVIC_DB_HOST' => getenv('AVIC_TEST_DB_HOST') ?: 'localhost',
    'AVIC_DB_PORT' => getenv('AVIC_TEST_DB_PORT') ?: '',
    'AVIC_DB_NAME' => getenv('AVIC_TEST_DB_NAME') ?: 'avic_test',
    'AVIC_DB_USER' => getenv('AVIC_TEST_DB_USER') ?: 'avic',
    'AVIC_DB_PASS' => getenv('AVIC_TEST_DB_PASS') ?: '12345678',
];

if (!preg_match('/_test$/', $testDb['AVIC_DB_NAME'])) {
    fwrite(STDERR, "Refusing to run: the test database name must end in \"_test\" (got \"{$testDb['AVIC_DB_NAME']}\"). The feature tests wipe every table.\n");
    exit(1);
}

foreach ($testDb as $key => $value) {
    putenv($key . '=' . $value);
}
putenv('AVIC_ENV=testing');
putenv('AVIC_DEMO_SECRET=Demo2026!');   // the seed's sign-in password, which the tests use
putenv('AVIC_UPLOAD_DIR');               // the test server gets its own (see TestServer)
