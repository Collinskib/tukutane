<?php
require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('people.php');
csrf_verify();
require_login('people.php');

$do  = $_POST['do'] ?? '';
$uid = (int) ($_POST['uid'] ?? 0);
$back = $_SERVER['HTTP_REFERER'] ?? 'people.php';
$parts = parse_url($back);
if (!empty($parts['host']) && $parts['host'] !== ($_SERVER['HTTP_HOST'] ?? '')) {
    $back = 'people.php';
} else {
    $back = ltrim(($parts['path'] ?? '') . (isset($parts['query']) ? '?' . $parts['query'] : ''), '/');
    if ($back === '') $back = 'people.php';
}

if (!$uid || $uid === user_id()) redirect('people.php');
if (!find_user($uid)) redirect('people.php');

if ($do === 'block') {
    block_user(user_id(), $uid);
    flash('User blocked.');
    redirect('people.php');
}

$res = friend_action($do, user_id(), $uid);
match ($res) {
    'sent'      => flash('Friend request sent.'),
    'accepted'  => flash('You\'re now friends!'),
    'declined'  => flash('Request declined.'),
    'cancelled' => flash('Request cancelled.'),
    'removed'   => flash('Friend removed.'),
    'exists'    => flash('A request already exists.', 'err'),
    default     => flash('That action isn\'t possible now.', 'err'),
};
redirect($back ?: 'people.php');
