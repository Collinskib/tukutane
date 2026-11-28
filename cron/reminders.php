<?php
// Cron: send a reminder notification to attendees ~24h before each gathering.
// Schedule in cPanel > Cron Jobs, e.g. hourly:
//   php /home/nipangec/repositories/tukutane/cron/reminders.php
// (runs from the repo clone so it never sits in the docroot)

require_once dirname(__DIR__) . '/includes/bootstrap.php';
if (PHP_SAPI !== 'cli' && !defined('NIP_CRON_WEB') && !is_admin()) exit('cli only');

$rows = db()->query(
    'SELECT r.id AS rsvp_id, r.user_id, g.id AS gid, g.title, g.event_at
     FROM rsvps r JOIN gatherings g ON g.id = r.gathering_id
     WHERE r.reminded = 0 AND r.user_id IS NOT NULL
       AND g.status = "active"
       AND g.event_at BETWEEN NOW() + INTERVAL 20 HOUR AND NOW() + INTERVAL 26 HOUR'
)->fetchAll(PDO::FETCH_ASSOC);

$n = 0;
$upd = db()->prepare('UPDATE rsvps SET reminded = 1 WHERE id = :i');
foreach ($rows as $r) {
    notify((int) $r['user_id'], 'reminder',
        'Reminder: "' . $r['title'] . '" is ' . when($r['event_at']) . '.',
        'event.php?id=' . $r['gid']);
    $upd->execute([':i' => $r['rsvp_id']]);
    $n++;
}
echo "sent $n reminders\n";

// Expire stale waitlist offers and hand their seats to the next in line.
$expired = waitlist_sweep();
if ($expired) echo "waitlist: expired offers swept in $expired gathering(s)\n";
