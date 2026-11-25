<?php
// GitHub webhook endpoint: a push to master pulls + deploys the repo through
// cPanel Git Version Control (see .cpanel.yml for the deploy tasks).
// The HMAC secret lives in ~/.deploy-hook-secret — outside the docroot and
// never committed. Configure the webhook as application/json.
header('Content-Type: application/json');

$home   = '/home/nipangec';
$secret = @trim((string) @file_get_contents($home . '/.deploy-hook-secret'));
if ($secret === '') {
    http_response_code(500);
    echo '{"ok":false,"error":"secret not configured"}';
    exit;
}

$body = file_get_contents('php://input');
$sig  = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$want = 'sha256=' . hash_hmac('sha256', $body, $secret);
if (!hash_equals($want, $sig)) {
    http_response_code(403);
    echo '{"ok":false,"error":"bad signature"}';
    exit;
}

$event   = $_SERVER['HTTP_X_GITHUB_EVENT'] ?? '';
$payload = json_decode($body, true) ?: [];

if ($event === 'ping') { echo '{"ok":true,"msg":"pong"}'; exit; }
if ($event !== 'push' || ($payload['ref'] ?? '') !== 'refs/heads/master') {
    echo '{"ok":true,"msg":"skipped"}';
    exit;
}

$out = [];
$rc  = 0;
exec('/usr/local/cpanel/bin/uapi VersionControl update repository_root=/home/nipangec/repositories/tukutane 2>&1', $out, $rc);
@file_put_contents(
    $home . '/deploy-hook.log',
    date('c') . ' ' . ($payload['after'] ?? '?') . " rc=$rc\n" . implode("\n", $out) . "\n\n",
    FILE_APPEND | LOCK_EX
);

http_response_code($rc === 0 ? 200 : 500);
echo json_encode(['ok' => $rc === 0, 'sha' => $payload['after'] ?? null]);
