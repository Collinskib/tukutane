<?php
// Shared event-reminder runner: notifies attendees ~24h out and sweeps stale
// waitlist offers. Used by cron/reminders.php and by maybe_run_reminders(),
// which piggybacks on ordinary traffic because crond does not run user
// crontabs on this host.

require_once __DIR__ . '/db.php';

function run_event_reminders(): array {
    $rows = db()->query(
        'SELECT r.id AS rsvp_id, r.user_id, g.id AS gid, g.title, g.event_at
         FROM rsvps r JOIN gatherings g ON g.id = r.gathering_id
         WHERE r.reminded = 0 AND r.user_id IS NOT NULL
           AND g.status = "active"
           AND g.event_at BETWEEN NOW() + INTERVAL 20 HOUR AND NOW() + INTERVAL 26 HOUR'
    )->fetchAll(PDO::FETCH_ASSOC);

    $upd = db()->prepare('UPDATE rsvps SET reminded = 1 WHERE id = :i');
    foreach ($rows as $r) {
        notify((int) $r['user_id'], 'reminder',
            'Reminder: "' . $r['title'] . '" is ' . when($r['event_at']) . '.',
            'event.php?id=' . $r['gid']);
        $upd->execute([':i' => $r['rsvp_id']]);
    }

    // Expire stale waitlist offers and hand their seats to the next in line.
    return ['sent' => count($rows), 'waitlist_swept' => waitlist_sweep()];
}

// Piggyback on normal traffic: at most one run per $interval seconds,
// flock-guarded so concurrent requests don't double-notify. Never throws.
function maybe_run_reminders(int $interval = 3600): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $fh = @fopen(sys_get_temp_dir() . '/nipange-reminders.lock', 'c');
        if (!$fh || !flock($fh, LOCK_EX | LOCK_NB)) { if ($fh) fclose($fh); return; }
        $mark = sys_get_temp_dir() . '/nipange-reminders';
        if (is_file($mark) && time() - filemtime($mark) < $interval) { fclose($fh); return; }
        try { run_event_reminders(); } catch (Throwable $e) {}
        @touch($mark);
        fclose($fh);
    } catch (Throwable $e) {
        // Reminders are best-effort — never break a request over them.
    }
}
