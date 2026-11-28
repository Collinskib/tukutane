<?php
// Drain queued notifications and deliver them as Expo push messages to
// devices registered via api/app.php?r=push/register. Runs from the docroot
// via the key-gated api cron/run route (this host's crond won't run cli
// jobs); also still usable from real cron/cli or an admin session.

$statusFile = dirname(__DIR__) . '/push-status.txt';
@file_put_contents($statusFile, date('Y-m-d H:i:s') . ' start sapi=' . PHP_SAPI . "\n");
ini_set('display_errors', '1');
error_reporting(E_ALL);

define('NIP_PUSH_DRAIN_MANUAL', 1); // this script drains explicitly below
require_once dirname(__DIR__) . '/includes/bootstrap.php';
// Allowed from: real cron (cli), admin session, or the key-gated api cron/run route.
if (PHP_SAPI !== 'cli' && !defined('NIP_CRON_WEB') && !is_admin()) exit('cli only');
@file_put_contents($statusFile, date('Y-m-d H:i:s') . " bootstrapped\n");

$writeStatus = function (string $line) use ($statusFile) {
    @file_put_contents($statusFile, date('Y-m-d H:i:s') . " $line\n");
};

$s = drain_push_notifications();
if (isset($s['expo_fail'])) {
    $writeStatus("expo-fail http={$s['expo_fail']} tokens={$s['tokens']} users={$s['users']}");
    echo "expo push send failed (http {$s['expo_fail']})\n";
} elseif (!$s['drained']) {
    $writeStatus("idle tokens={$s['tokens']} users={$s['users']}");
    echo "nothing queued\n";
} else {
    $writeStatus("drained {$s['drained']} queued={$s['pushed']} tokens={$s['tokens']} users={$s['users']}");
    echo "drained {$s['drained']} notification(s), pushed {$s['pushed']} message(s)\n";
}
