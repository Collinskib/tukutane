<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
if (!user()) { http_response_code(401); exit(json_encode(['error' => 'login'])); }

$uid = user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try { csrf_verify(); } catch (Throwable) { http_response_code(419); exit(json_encode(['error' => 'refresh the page'])); }
    $conv = conversation_for((int) ($_POST['conv'] ?? 0), $uid);
    $body = trim($_POST['body'] ?? '');
    if (!$conv) { http_response_code(403); exit(json_encode(['error' => 'no conversation'])); }
    $otherId = (int) ($conv['user_one'] === $uid ? $conv['user_two'] : $conv['user_one']);
    $rel = friendship($uid, $otherId);
    if (!$rel || $rel['status'] !== 'accepted' || is_blocked($uid, $otherId)) {
        http_response_code(403); exit(json_encode(['error' => 'friends only']));
    }
    if ($body === '') exit(json_encode(['error' => 'empty']));
    $id = send_message((int) $conv['id'], $uid, $body);
    // notify the other party (once per unread burst, unless they muted the chat)
    $pending = db()->prepare('SELECT COUNT(*) FROM messages WHERE conversation_id = :c AND sender_id = :s AND read_at IS NULL');
    $pending->execute([':c' => $conv['id'], ':s' => $uid]);
    if ((int) $pending->fetchColumn() === 1 && !conversation_muted((int) $conv['id'], $otherId)) {
        notify($otherId, 'message', user()['name'] . ' sent you a message.', 'messages.php?c=' . $conv['id']);
    }
    echo json_encode(['ok' => true, 'message' => [
        'id' => $id, 'sender_id' => $uid, 'body' => mb_substr($body, 0, 1000),
        'time' => date('g:i A'),
    ]]);
    exit;
}

// GET: poll new messages
$conv = conversation_for((int) ($_GET['conv'] ?? 0), $uid);
if (!$conv) { http_response_code(403); exit(json_encode(['error' => 'no conversation'])); }
$rows = messages_since((int) $conv['id'], (int) ($_GET['after'] ?? 0), 50);
// mark incoming as read when fetched
db()->prepare('UPDATE messages SET read_at = NOW() WHERE conversation_id = :c AND sender_id != :u AND read_at IS NULL')
    ->execute([':c' => $conv['id'], ':u' => $uid]);
echo json_encode(['messages' => array_map(fn($m) => [
    'id' => (int) $m['id'], 'sender_id' => (int) $m['sender_id'],
    'body' => $m['body'], 'time' => date('g:i A', strtotime($m['created_at'])),
], $rows)]);
