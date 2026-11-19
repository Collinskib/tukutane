<?php
// Bootstrap: session, DB, helpers, CSRF, auth. Every page starts here.

require_once dirname(__DIR__) . '/config.php';

date_default_timezone_set('Africa/Nairobi');

session_name('NIPSESS');
session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 30,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

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

auth_refresh();
