<?php
declare(strict_types=1);

require_once __DIR__ . '/../db/db_connection.php';

$password = $argv[1] ?? 'DemoPassword1!';
if ($password === 'DemoPassword1!') {
    echo 'Password for adjuster account: ';
    $password = trim(fgets(STDIN));
}
if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters.\n");
    exit(1);
}

$fullName = $argv[2] ?? 'Demo Adjuster';
$email    = $argv[3] ?? 'adjuster@example.com';
$phone    = $argv[4] ?? '+1 000 000 0000';
    echo 'Password for adjuster account: ';
    $password = trim(fgets(STDIN));

if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters.\n");
    exit(1);
}

try {
    $check = $conn->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $check->execute(['email' => $email]);
    if ($check->fetch()) {
        echo "User already exists: $email (skipped)\n";
        exit;
    }

    $stmt = $conn->prepare(
        'INSERT INTO users (uuid, full_name, email, phone, password_hash, role, status)
         VALUES (UUID(), :full_name, :email, :phone, :password_hash, :role, :status)'
    );
    $stmt->execute([
        'full_name'     => $fullName,
        'email'         => $email,
        'phone'         => $phone,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role'          => 'adjuster',
        'status'        => 'active',
    ]);

    echo "Created adjuster account: $email\n";
} catch (PDOException $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
