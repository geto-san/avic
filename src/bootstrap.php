<?php
declare(strict_types=1);

define('AVIC_ROOT', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'Avic\\', 5) !== 0) {
        return;
    }
    $file = AVIC_ROOT . '/src/' . str_replace('\\', '/', substr($class, 5)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require AVIC_ROOT . '/src/helpers.php';

date_default_timezone_set('Africa/Kampala');
Avic\Core\Env::load(AVIC_ROOT . '/.env');
