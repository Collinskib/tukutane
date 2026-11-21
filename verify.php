<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

// Resend link — logged-in, unverified users only
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    require_login('verify.php');
    if (empty(user()['email_verified'])) {
        send_verification(user()['email'], user()['name']);
        flash('Verification link sent — check your inbox (and spam).');
    }
    redirect('index.php');
}

$t = preg_replace('/[^a-f0-9]/', '', $_GET['t'] ?? '');
if ($t === '') {
    flash('That verification link is not valid.', 'err');
    redirect('index.php');
}
$st = db()->prepare('SELECT id FROM users WHERE verify_token = :t');
$st->execute([':t' => $t]);
$u = $st->fetch(PDO::FETCH_ASSOC);
if (!$u) {
    flash('That verification link has expired or was already used.', 'err');
    redirect('index.php');
}
db()->prepare('UPDATE users SET email_verified = 1, verify_token = NULL WHERE id = :id')
    ->execute([':id' => $u['id']]);
flash('Email verified — karibu to Nipange!');
redirect('index.php');
