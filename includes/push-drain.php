<?php
// Shared push-drain: delivers queued notifications as Expo push messages to
// devices registered via api/app.php?r=push/register. Used by cron/push.php
// and by maybe_drain_push(), which piggybacks on ordinary traffic because
// crond does not run user crontabs on this host.

require_once __DIR__ . '/db.php';

function drain_push_notifications(): array {
    $tokens = (int) db()->query('SELECT COUNT(*) FROM push_tokens')->fetchColumn();
    $users  = (int) db()->query('SELECT COUNT(DISTINCT user_id) FROM push_tokens')->fetchColumn();

    $rows = db()->query(
        'SELECT n.id, n.user_id, n.type, n.body, n.link
         FROM notifications n
         WHERE n.pushed_at IS NULL
         ORDER BY n.id LIMIT 200'
    )->fetchAll(PDO::FETCH_ASSOC);

    $stats = ['drained' => 0, 'pushed' => 0, 'tokens' => $tokens, 'users' => $users];
    if (!$rows) return $stats;

    $tokStmt  = db()->prepare('SELECT token FROM push_tokens WHERE user_id = :u');
    $sentStmt = db()->prepare('UPDATE notifications SET pushed_at = NOW() WHERE id = :i');
    $killStmt = db()->prepare('DELETE FROM push_tokens WHERE token = :t');
    $useStmt  = db()->prepare('UPDATE push_tokens SET last_used = NOW() WHERE token = :t');

    $messages = [];
    $notifIds = [];

    foreach ($rows as $n) {
        $notifIds[] = (int) $n['id'];
        $tokStmt->execute([':u' => $n['user_id']]);
        foreach ($tokStmt->fetchAll(PDO::FETCH_COLUMN) as $t) {
            $messages[] = [
                'to'    => $t,
                'title' => 'Nipange',
                'body'  => $n['body'],
                'sound' => 'default',
                'data'  => ['link' => $n['link'] ?: '', 'type' => $n['type']],
            ];
        }
        // Always mark pushed — in-app delivery already happened; a user with
        // no devices simply gets no push.
        $sentStmt->execute([':i' => $n['id']]);
    }
    $stats['drained'] = count($notifIds);

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
            $stats['expo_fail'] = $code;
            continue;
        }
        foreach ((array) $res['data'] as $i => $r) {
            $t = $chunk[array_keys($chunk)[$i]]['to'] ?? null;
            if (!$t) continue;
            if (($r['status'] ?? '') === 'ok') { $useStmt->execute([':t' => $t]); $stats['pushed']++; }
            elseif (($r['details']['error'] ?? '') === 'DeviceNotRegistered') $killStmt->execute([':t' => $t]);
        }
    }
    return $stats;
}

// Piggyback drain on normal traffic: at most one drain per $interval seconds,
// flock-guarded so concurrent requests don't double-send. Never throws.
function maybe_drain_push(int $interval = 45): void {
    if (defined('NIP_PUSH_DRAIN_MANUAL')) return;
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $fh = @fopen(sys_get_temp_dir() . '/nipange-push-drain.lock', 'c');
        if (!$fh || !flock($fh, LOCK_EX | LOCK_NB)) { if ($fh) fclose($fh); return; }
        $mark = sys_get_temp_dir() . '/nipange-push-drain';
        if (is_file($mark) && time() - filemtime($mark) < $interval) { fclose($fh); return; }
        $s = null;
        try { $s = drain_push_notifications(); } catch (Throwable $e) {}
        if (is_array($s)) {
            @file_put_contents(dirname(__DIR__) . '/push-status.txt', date('Y-m-d H:i:s')
                . " lazy drained={$s['drained']} pushed={$s['pushed']} tokens={$s['tokens']} users={$s['users']}\n");
        }
        @touch($mark);
        fclose($fh);
    } catch (Throwable $e) {
        // Push is best-effort — never break a request over it.
    }
}
