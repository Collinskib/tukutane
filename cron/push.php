<?php
// Cron: drain queued notifications and deliver them as Expo push messages
// to devices registered via api/app.php?r=push/register.
// Schedule in cPanel > Cron Jobs alongside reminders.php, e.g. every minute:
//   php /home/nipangec/repositories/tukutane/cron/push.php
// (runs from the repo clone so it never sits in the docroot)

require_once dirname(__DIR__) . '/includes/bootstrap.php';
if (PHP_SAPI !== 'cli' && !is_admin()) exit('cli only');

$rows = db()->query(
    'SELECT n.id, n.user_id, n.type, n.body, n.link
     FROM notifications n
     WHERE n.pushed_at IS NULL
     ORDER BY n.id LIMIT 200'
)->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) { echo "nothing queued\n"; exit; }

$tokStmt  = db()->prepare('SELECT token FROM push_tokens WHERE user_id = :u');
$sentStmt = db()->prepare('UPDATE notifications SET pushed_at = NOW() WHERE id = :i');
$killStmt = db()->prepare('DELETE FROM push_tokens WHERE token = :t');
$useStmt  = db()->prepare('UPDATE push_tokens SET last_used = NOW() WHERE token = :t');

$messages = [];   // expo payload
$notifIds = [];   // notification ids we attempted
$tokenIdx = [];   // message index => token (to handle receipts)

foreach ($rows as $n) {
    $notifIds[] = (int) $n['id'];
    $tokStmt->execute([':u' => $n['user_id']]);
    foreach ($tokStmt->fetchAll(PDO::FETCH_COLUMN) as $t) {
        $tokenIdx[] = $t;
        $messages[] = [
            'to'    => $t,
            'title' => 'Nipange',
            'body'  => $n['body'],
            'sound' => 'default',
            'data'  => ['link' => $n['link'] ?: '', 'type' => $n['type']],
        ];
    }
    // Always mark pushed — in-app delivery already happened; a user with no
    // devices simply gets no push.
    $sentStmt->execute([':i' => $n['id']]);
}

$sent = 0;
foreach (array_chunk($messages, 100, true) as $chunk) {
    $payload = array_values($chunk);
    $ch = curl_init('https://exp.host/--/api/v2/push/send');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $res = $raw ? json_decode($raw, true) : null;
    if ($code !== 200 || !is_array($res) || !isset($res['data'])) {
        echo "expo push send failed (http $code)\n";
        continue;
    }
    foreach ((array) $res['data'] as $i => $r) {
        $t = $chunk[array_keys($chunk)[$i]]['to'] ?? null;
        if (!$t) continue;
        if (($r['status'] ?? '') === 'ok') { $useStmt->execute([':t' => $t]); $sent++; }
        elseif (($r['details']['error'] ?? '') === 'DeviceNotRegistered') $killStmt->execute([':t' => $t]);
    }
}

echo 'drained ' . count($notifIds) . " notification(s), pushed $sent message(s)\n";
