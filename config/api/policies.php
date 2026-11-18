<?php
declare(strict_types=1);

/** Adjusters issue cover to registered claimants; claimants cannot insure themselves. */
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/policy-fields.php';
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET', 'POST'], true)) apiJson(405, ['message' => 'Method not allowed']);
$user = apiUser(['adjuster']);

try {
    if ($method === 'GET') {
        apiJson(200, [
            'claimants' => $conn->query('SELECT id, full_name, email FROM users WHERE role = "claimant" AND status = "active" ORDER BY full_name')->fetchAll(),
            'policies' => $conn->query('SELECT p.*, u.full_name FROM policies p JOIN users u ON u.id = p.user_id ORDER BY p.id DESC')->fetchAll(),
        ]);
    }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) apiJson(400, ['message' => 'Invalid request body']);
    [$fields, $errors] = policyFields($input);
    if ($errors) apiJson(422, ['message' => 'Correct the highlighted fields.', 'errors' => $errors]);

    $result = inTransaction($conn, static function () use ($conn, $user, $fields): array {
        // Lock the owner so simultaneous issuance cannot create overlapping cover.
        $owner = $conn->prepare('SELECT id FROM users WHERE id = ? AND role = "claimant" AND status = "active" FOR UPDATE');
        $owner->execute([$fields['user_id']]);
        if (!$owner->fetch()) throw new ApiAbort('Choose an active claimant.', 422);
        $number = 'POL-' . strtoupper(bin2hex(random_bytes(8)));
        $id = createPolicy($conn, $fields, $number);
        audit($conn, $user, 'policy.created', 'policy', $id, null, $fields + ['policy_number' => $number]);
        notify($conn, $fields['user_id'], null, 'policy', 'Policy issued', $number . ' covers ' . $fields['vehicle_plate'] . '. View it under Policies.');
        return ['id' => $id, 'policy_number' => $number];
    });
    apiJson(201, ['ok' => true] + $result);
} catch (PDOException $error) {
    error_log($error->getMessage());
    apiJson(500, ['message' => 'Could not save the policy.']);
}
