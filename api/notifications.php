<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
if (!user()) { http_response_code(401); exit(json_encode(['error' => 'login'])); }

$uid = user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (($_POST['do'] ?? '') === 'mark_read') {
        db()->prepare('UPDATE notifications SET read_at = NOW() WHERE user_id = :u AND read_at IS NULL')
            ->execute([':u' => $uid]);
    }
    exit(json_encode(['ok' => true]));
}

// GET — ?count=1 returns badges only; otherwise the latest list too
$out = [
    'unread'    => unread_notifications($uid),
    'unreadMsg' => unread_messages($uid),
];
if (empty($_GET['count'])) {
    $out['items'] = array_map(fn($n) => [
        'id' => (int) $n['id'], 'type' => $n['type'], 'body' => $n['body'],
        'link' => $n['link'], 'unread' => $n['read_at'] === null,
        'ago' => time_ago($n['created_at']),
    ], notifications_for($uid, 20));
}
echo json_encode($out);
