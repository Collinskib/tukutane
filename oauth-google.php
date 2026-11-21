<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$fail = function (string $m): never {
    flash($m, 'err');
    redirect('auth.php');
};

if (!defined('GOOGLE_CLIENT_ID') || !defined('GOOGLE_CLIENT_SECRET')) {
    $fail('Google sign-in is not configured yet.');
}

$redirect = oauth_redirect_uri();

// ---- start: send the user to Google ----
if (isset($_GET['start'])) {
    $next = $_GET['next'] ?? 'index.php';
    if (!preg_match('~^[a-z0-9_./?=&#%-]+$~i', $next) || str_starts_with($next, '//')) $next = 'index.php';
    $_SESSION['oauth_next']  = $next;
    $_SESSION['oauth_state'] = bin2hex(random_bytes(16));
    redirect('https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => $redirect,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $_SESSION['oauth_state'],
    ]));
}

// ---- callback from Google ----
if (isset($_GET['error'])) $fail('Google sign-in was cancelled.');
if (empty($_GET['code']) || empty($_GET['state'])) $fail('Invalid response from Google.');
if (!hash_equals($_SESSION['oauth_state'] ?? '', (string) $_GET['state'])) {
    $fail('Sign-in check failed — please try again.');
}
unset($_SESSION['oauth_state']);
$next = $_SESSION['oauth_next'] ?? 'index.php';
unset($_SESSION['oauth_next']);

$tok = http_request('https://oauth2.googleapis.com/token', [
    'code'          => $_GET['code'],
    'client_id'     => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri'  => $redirect,
    'grant_type'    => 'authorization_code',
]);
if (empty($tok['access_token'])) $fail('Could not reach Google — try again.');

$info = http_request('https://openidconnect.googleapis.com/v1/userinfo', null,
    ['Authorization: Bearer ' . $tok['access_token']]);
if (empty($info['email']) || empty($info['email_verified'])) {
    $fail('Google did not share a verified email address.');
}

$email = mb_strtolower(trim($info['email']));
$u = find_user_by_email($email);
if (!$u) {
    $name = trim(mb_substr((string) ($info['name'] ?? ''), 0, 60));
    if ($name === '') $name = strstr($email, '@', true) ?: 'Nipange member';
    db()->prepare('INSERT INTO users (email, pass_hash, name, photo, email_verified) VALUES (:e, :p, :n, :ph, 1)')
        ->execute([
            ':e'  => $email,
            ':p'  => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
            ':n'  => $name,
            ':ph' => oauth_avatar((string) ($info['picture'] ?? '')),
        ]);
    $u = find_user_by_email($email);
}
if (!$u || $u['status'] !== 'active') $fail('This account is suspended.');

session_regenerate_id(true);
$_SESSION['uid'] = $u['id'];
$_SESSION['key'] = SESSION_KEY;
unset($_SESSION['tries'], $_SESSION['tries_at'], $_SESSION['claimed']);
auth_refresh();
flash('Karibu, ' . $u['name'] . '!');
redirect($next);
