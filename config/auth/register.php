<?php
declare(strict_types=1);

require_once __DIR__ . '/../db/db_connection.php';
require_once __DIR__ . '/_helpers.php';

$input = authRequirePostJson();

$errors = [];

$role = $input['role'] ?? '';
if (!in_array($role, ['claimant', 'garage'], true)) {
    $errors['role'] = 'Choose whether you are a claimant or a garage.';
}

$fullName = trim((string)($input['full_name'] ?? ''));
if ($fullName === '' || mb_strlen($fullName) > 150) {
    $errors['full_name'] = 'Enter a name between 1 and 150 characters.';
}

$email = trim((string)($input['email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Enter a valid email address.';
}

$phone = trim((string)($input['phone'] ?? ''));
if (!preg_match('/^\+?[0-9\s\-]{7,20}$/', $phone)) {
    $errors['phone'] = 'Enter a valid phone number.';
}

$password  = (string)($input['password'] ?? '');
$password2 = (string)($input['password2'] ?? '');
if (strlen($password) < 8) {
    $errors['password'] = 'Password must be at least 8 characters.';
} elseif ($password !== $password2) {
    $errors['password2'] = 'Passwords do not match.';
}

$garageAddress = null;
$tradingLicence = null;
if ($role === 'garage') {
    $garageAddress  = trim((string)($input['garage_address'] ?? ''));
    $tradingLicence = trim((string)($input['trading_licence'] ?? ''));
    if ($garageAddress === '')  { $errors['garage_address']  = 'Workshop address is required.'; }
    if ($tradingLicence === '') { $errors['trading_licence'] = 'Trading licence number is required.'; }
}

if (!empty($errors)) {
    authJson(400, ['message' => 'Validation failed', 'errors' => $errors]);
}

try {
    $check = $conn->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $check->execute(['email' => $email]);
    if ($check->fetch()) {
        authJson(409, ['message' => 'Validation failed', 'errors' => ['email' => 'That email is already registered.']]);
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $status = 'active';
    $uuid = uuidv4();

    $insert = $conn->prepare(
        'INSERT INTO users (uuid, role, full_name, email, phone, password_hash, garage_address, trading_licence, status)
         VALUES (:uuid, :role, :full_name, :email, :phone, :password_hash, :garage_address, :trading_licence, :status)'
    );
    $insert->execute([
        'uuid'            => $uuid,
        'role'            => $role,
        'full_name'       => $fullName,
        'email'           => $email,
        'phone'           => $phone,
        'password_hash'   => $passwordHash,
        'garage_address'  => $garageAddress,
        'trading_licence' => $tradingLicence,
        'status'          => $status,
    ]);

    authJson(201, ['message' => 'Account created']);
} catch (PDOException $e) {
    error_log($e->getMessage());
    authJson(500, ['message' => 'Something went wrong. Please try again later.']);
}
