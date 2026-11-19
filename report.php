<?php
require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');
csrf_verify();
require_login('index.php');

$type = in_array($_POST['type'] ?? '', ['user', 'gathering', 'message'], true) ? $_POST['type'] : 'gathering';
add_report(user_id(), $type, (int) ($_POST['id'] ?? 0), trim($_POST['reason'] ?? ''));

$back = $_POST['back'] ?? 'index.php';
if (!preg_match('~^[a-z0-9_./?=&#%-]+$~i', $back)) $back = 'index.php';
flash('Report sent — our team will review it.');
redirect($back);
