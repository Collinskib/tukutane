<?php
// Cron: send a reminder notification to attendees ~24h before each gathering.
// Schedule in cPanel > Cron Jobs, e.g. hourly:
//   php /home/nipangec/repositories/tukutane/cron/reminders.php
// (runs from the repo clone so it never sits in the docroot)
// Also reachable via api/app.php?r=cron/run&job=reminders&key=… and lazily
// via maybe_run_reminders() in bootstrap — crond does not fire on this host.

require_once dirname(__DIR__) . '/includes/bootstrap.php';
if (PHP_SAPI !== 'cli' && !defined('NIP_CRON_WEB') && !is_admin()) exit('cli only');

require_once dirname(__DIR__) . '/includes/reminders.php';
$s = run_event_reminders();
echo "sent {$s['sent']} reminders\n";
if ($s['waitlist_swept']) echo "waitlist: expired offers swept in {$s['waitlist_swept']} gathering(s)\n";
