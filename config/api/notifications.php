<?php
declare(strict_types=1);

/* POST config/api/notifications.php */

require_once __DIR__ . '/_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    apiJson(405, ['message' => 'Method not allowed']);
}

$user = apiUser();
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    apiJson(400, ['message' => 'Invalid request body']);
}

$action = (string)($input['action'] ?? '');

try {
    if ($action === 'read') {
        $id = (int)($input['id'] ?? 0);
        $check = $conn->prepare('SELECT id FROM notifications WHERE id = :id AND user_id = :uid LIMIT 1');
        $check->execute(['id' => $id, 'uid' => (int)$user['id']]);
        if (!$check->fetch()) {
            apiJson(404, ['message' => 'Notification not found.']);
        }
        $conn->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id')->execute(['id' => $id]);
        apiJson(200, ['ok' => true]);
    }

    if ($action === 'read_all') {
        $conn->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :uid')->execute(['uid' => (int)$user['id']]);
        apiJson(200, ['ok' => true]);
    }

    apiJson(422, ['message' => 'Unknown action.']);
} catch (PDOException $e) {
    error_log($e->getMessage());
    apiJson(500, ['message' => 'Could not update notifications right now.']);
}
