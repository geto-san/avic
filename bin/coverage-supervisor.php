<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if ($argc !== 3 || !in_array($argv[1], ['grant', 'revoke'], true)) {
    fwrite(STDERR, "Usage: php bin/coverage-supervisor.php grant|revoke ADJUSTER_EMAIL\n");
    exit(2);
}
require_once __DIR__ . '/../config/api/_helpers.php';
$conn->beginTransaction();
$account = $conn->prepare('SELECT id, status FROM users WHERE email = ? AND role = "adjuster" FOR UPDATE');
$account->execute([$argv[2]]);
$staff = $account->fetch();
if (!$staff || ($argv[1] === 'grant' && $staff['status'] !== 'active')) {
    $conn->rollBack();
    fwrite(STDERR, "Choose an existing adjuster. Grants require an active account.\n");
    exit(1);
}
if ($argv[1] === 'grant') {
    $change = $conn->prepare("INSERT INTO staff_permissions (user_id, permission) VALUES (?, 'coverage.view_all')
        ON DUPLICATE KEY UPDATE permission = 'coverage.view_all'");
} else {
    $change = $conn->prepare("DELETE FROM staff_permissions WHERE user_id = ? AND permission = 'coverage.view_all'");
}
$change->execute([$staff['id']]);
if ($change->rowCount() > 0) {
    audit($conn, [], 'permission.' . $argv[1], 'user', (int)$staff['id'], null,
        ['permission' => 'coverage.view_all', 'source' => 'deployment CLI']);
}
$conn->commit();
echo "Coverage supervisor permission updated.\n";
