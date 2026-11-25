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

// cPanel's Git deployment flag is unreliable on this host, so deploy.sh
// does the fetch + reset + copy itself (same steps as .cpanel.yml). It's
// queued in the background so GitHub gets a fast 200 — concurrent pushes
// serialize on the flock inside deploy.sh. Status is in ~/deploy-hook.log.
$repo = '/home/nipangec/repositories/tukutane';
$log  = $home . '/deploy-hook.log';
@file_put_contents(
    $log,
    date('c') . ' ' . ($payload['after'] ?? '?') . " queued\n",
    FILE_APPEND | LOCK_EX
);
exec(
    'cd ' . escapeshellarg($repo)
    . ' && nohup /bin/sh deploy.sh >> ' . escapeshellarg($log) . ' 2>&1 &'
);
echo json_encode(['ok' => true, 'queued' => true, 'sha' => $payload['after'] ?? null]);
