<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
if (!user()) { http_response_code(401); exit(json_encode(['error' => 'login'])); }

$uid = user_id();
$gid = (int) ($_REQUEST['g'] ?? 0);
$ev = find_gathering($gid);
if (!$ev) { http_response_code(404); exit(json_encode(['error' => 'no gathering'])); }
if (!can_event_chat($ev, $uid)) { http_response_code(403); exit(json_encode(['error' => 'host and attendees only'])); }
$isHost = (int) ($ev['creator_id'] ?? 0) === $uid;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try { csrf_verify(); } catch (Throwable) { http_response_code(419); exit(json_encode(['error' => 'refresh the page'])); }
    switch ($_POST['do'] ?? '') {
        case 'send':
            if ($ev['status'] === 'cancelled') { http_response_code(409); exit(json_encode(['error' => 'gathering cancelled'])); }
            $body = trim($_POST['body'] ?? '');
            if ($body === '') exit(json_encode(['error' => 'empty']));
            $id = event_chat_send($gid, $uid, $body);
            // host messages notify the whole channel (respecting mutes);
            // attendee messages only notify the host
            $targets = $isHost ? event_chat_audience($gid, $uid)
                             : array_filter([(int) ($ev['creator_id'] ?? 0)], fn($h) => $h !== $uid && !event_chat_muted($gid, $h));
            foreach ($targets as $t) {
                notify($t, 'event_chat', user()['name'] . ' in "' . $ev['title'] . '": ' . mb_strimwidth($body, 0, 60, '…'), 'event.php?id=' . $gid . '#chat');
            }
            exit(json_encode(['ok' => true, 'message' => [
                'id' => $id, 'sender_id' => $uid, 'sender_name' => user()['name'],
                'body' => mb_substr($body, 0, 1000), 'pinned' => 0, 'time' => date('g:i A'),
            ]]));

        case 'pin': case 'unpin':
            if (!$isHost) { http_response_code(403); exit(json_encode(['error' => 'host only'])); }
            $mid = (int) ($_POST['mid'] ?? 0);
            $m = event_message_get($mid);
            if (!$m || (int) $m['gathering_id'] !== $gid) { http_response_code(404); exit(json_encode(['error' => 'no message'])); }
            $on = $_POST['do'] === 'pin';
            if ($on) db()->prepare('UPDATE event_messages SET pinned = 0 WHERE gathering_id = :g')->execute([':g' => $gid]);
            db()->prepare('UPDATE event_messages SET pinned = :p WHERE id = :i')->execute([':p' => $on ? 1 : 0, ':i' => $mid]);
            if ($on) {
                foreach (event_chat_audience($gid, $uid) as $t) {
                    notify($t, 'event_pinned', 'Pinned in "' . $ev['title'] . '": ' . mb_strimwidth($m['body'], 0, 70, '…'), 'event.php?id=' . $gid . '#chat');
                }
            }
            exit(json_encode(['ok' => true]));

        case 'mute': case 'unmute':
            event_chat_set_muted($gid, $uid, $_POST['do'] === 'mute');
            exit(json_encode(['ok' => true]));
    }
    http_response_code(400); exit(json_encode(['error' => 'bad action']));
}

// GET: poll messages + channel state
$rows = event_messages($gid, (int) ($_GET['after'] ?? 0));
event_chat_mark_read($gid, $uid);
$pin = event_pinned($gid);
echo json_encode([
    'muted'  => event_chat_muted($gid, $uid),
    'host'   => $isHost,
    'pinned' => $pin ? ['id' => (int) $pin['id'], 'body' => $pin['body'], 'sender_name' => $pin['sender_name']] : null,
    'messages' => array_map(fn($m) => [
        'id' => (int) $m['id'], 'sender_id' => (int) $m['sender_id'],
        'sender_name' => $m['sender_name'], 'body' => $m['body'],
        'pinned' => (int) $m['pinned'], 'host' => (int) $m['sender_id'] === (int) ($ev['creator_id'] ?? 0),
        'time' => date('g:i A', strtotime($m['created_at'])),
    ], $rows),
]);
