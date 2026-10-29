<?php
declare(strict_types=1);

// Usage: php bin/create-adjuster.php "Full Name" email@avic.ug [region] [specialty] [password]
// Adjusters are staff: they cannot self-register. region = central|eastern|northern|western,
// specialty = collision|theft|fire|vandalism|natural_disaster|other (both optional, used by assignment).
require dirname(__DIR__) . '/src/bootstrap.php';

use Avic\Core\Db;
use Avic\Domain\Catalog;

[, $name, $email] = $argv + [null, null, null];
$region = ($argv[3] ?? '') ?: null;
$specialty = ($argv[4] ?? '') ?: null;
$password = $argv[5] ?? null;

if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php bin/create-adjuster.php \"Full Name\" email [region] [specialty] [password]\n");
    exit(1);
}
if (($region && !isset(Catalog::REGIONS[$region])) || ($specialty && !isset(Catalog::LOSS_TYPES[$specialty]))) {
    fwrite(STDERR, "Unknown region or specialty.\n");
    exit(1);
}
if ($password === null) {
    $password = rtrim(strtr(base64_encode(random_bytes(12)), '+/', 'Aa'), '=');
    echo "Generated password: $password\n";
}
if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters.\n");
    exit(1);
}
Db::insert(
    "INSERT INTO users (full_name, email, password_hash, role, region, specialty) VALUES (?, ?, ?, 'adjuster', ?, ?)",
    [$name, mb_strtolower($email), password_hash($password, PASSWORD_DEFAULT), $region, $specialty]
);
echo "Adjuster created: $email\n";
