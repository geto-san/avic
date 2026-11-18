<?php
declare(strict_types=1);

// One field validator for issued policies and claimant registrations.
function policyFields(array $input, bool $premiumRequired = true): array
{
    $errors = [];
    $fields = [];
    foreach (['vehicle_make' => 60, 'vehicle_model' => 60, 'vehicle_plate' => 20] as $field => $max) {
        $fields[$field] = trim((string)($input[$field] ?? ''));
        if ($fields[$field] === '' || mb_strlen($fields[$field]) > $max) $errors[$field] = 'Enter between 1 and ' . $max . ' characters.';
    }
    $fields['vehicle_plate'] = mb_strtoupper($fields['vehicle_plate']);
    $fields['user_id'] = (int)($input['user_id'] ?? 0);
    $fields['vehicle_year'] = filter_var($input['vehicle_year'] ?? '', FILTER_VALIDATE_INT);
    if (!$fields['vehicle_year'] || $fields['vehicle_year'] < 1901 || $fields['vehicle_year'] > (int)date('Y') + 1) $errors['vehicle_year'] = 'Enter a valid vehicle year.';
    $fields['coverage_type'] = (string)($input['coverage_type'] ?? '');
    if (!in_array($fields['coverage_type'], ['basic', 'comprehensive', 'third_party'], true)) $errors['coverage_type'] = 'Choose a coverage type.';
    foreach (['coverage_limit', 'premium'] as $field) {
        $amount = filter_var($input[$field] ?? '', FILTER_VALIDATE_FLOAT);
        if ($field === 'premium' && !$premiumRequired && (($input[$field] ?? '') === '')) { $fields[$field] = null; continue; }
        if ($amount === false || !is_finite($amount) || round($amount, 2) <= 0 || $amount > ($field === 'premium' ? 99999999.99 : 9999999999.99)) $errors[$field] = 'Enter a positive amount within the supported currency range.';
        $fields[$field] = $amount === false ? 0 : round($amount, 2);
    }
    foreach (['start_date', 'end_date'] as $field) {
        $fields[$field] = (string)($input[$field] ?? '');
        if (!validDate($fields[$field])) $errors[$field] = 'Enter a real date.';
    }
    if (!$errors && $fields['end_date'] < $fields['start_date']) $errors['end_date'] = 'End date must be on or after start date.';
    return [$fields, $errors];
}

// Caller holds the claimant's user lock, shared by both policy entry paths.
function createPolicy(PDO $conn, array $fields, string $number): int
{
    $existing = $conn->prepare('SELECT id FROM policies WHERE user_id = ? AND vehicle_plate = ? AND status = "active" AND start_date <= ? AND end_date >= ?');
    $existing->execute([$fields['user_id'], $fields['vehicle_plate'], $fields['end_date'], $fields['start_date']]);
    if ($existing->fetch()) throw new ApiAbort('This claimant already has overlapping cover for this vehicle.', 409);
    $conn->prepare('INSERT INTO policies (user_id, policy_number, vehicle_make, vehicle_model, vehicle_plate, vehicle_year, coverage_type, coverage_limit, premium, start_date, end_date, status) VALUES (:user_id, :policy_number, :vehicle_make, :vehicle_model, :vehicle_plate, :vehicle_year, :coverage_type, :coverage_limit, :premium, :start_date, :end_date, "active")')->execute($fields + ['policy_number'=>$number]);
    return (int)$conn->lastInsertId();
}
