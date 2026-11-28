<?php
// Bootstrap: session, DB, helpers, CSRF, auth. Every page starts here.

require_once dirname(__DIR__) . '/config.php';

date_default_timezone_set('Africa/Nairobi');

session_name('NIPSESS');
// Server-side session retention must outlive the cookie below — the default
// gc_maxlifetime (24 min) was silently logging everyone out.
ini_set('session.gc_maxlifetime', (string) (60 * 60 * 24 * 30));
session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 30,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

// gzip page/JSON output when the host isn't already compressing —
// meaningful savings on slow mobile networks.
if (!ini_get('zlib.output_compression')
    && str_contains($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip')) {
    ob_start('ob_gzhandler');
}

// Anonymous visitor key — kept so old session RSVPs still display and can be
// claimed into an account on first login.
if (empty($_SESSION['key'])) {
    $_SESSION['key'] = bin2hex(random_bytes(16));
}
define('SESSION_KEY', $_SESSION['key']);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/push-drain.php';
require_once __DIR__ . '/reminders.php';

auth_refresh();

// Crond doesn't run user crontabs on this host — piggyback the push queue
// drain and hourly event reminders on real traffic (flock-throttled,
// never throw).
if (PHP_SAPI !== 'cli') { maybe_drain_push(); maybe_run_reminders(); }
