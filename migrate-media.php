<?php
// One-off migration: message media — audio/video kinds fit attachment_kind
// already; adds view-once columns. Admin-gated; safe to re-run.
require_once __DIR__ . '/includes/bootstrap.php';
$deployCli = PHP_SAPI === 'cli' || getenv('NIP_DEPLOY') === '1';
if (!$deployCli) {
    require_login('migrate-media.php');
    if (!is_admin()) exit('admin only');
}

header('Content-Type: text/plain');
echo "migrating media...\n";

function col_exists(string $table, string $col): bool {
    $s = db()->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE :c');
    $s->execute([':c' => $col]);
    return (bool) $s->fetch();
}

if (!col_exists('messages', 'view_once')) {
    db()->exec('ALTER TABLE messages
        ADD COLUMN view_once   TINYINT(1) NOT NULL DEFAULT 0,
        ADD COLUMN consumed_at DATETIME   NULL');
    echo "OK   messages view_once + consumed_at\n";
} else {
    echo "skip messages.view_once already present\n";
}

if (!col_exists('users', 'last_seen_at')) {
    db()->exec('ALTER TABLE users ADD COLUMN last_seen_at DATETIME NULL');
    echo "OK   users.last_seen_at\n";
} else {
    echo "skip users.last_seen_at already present\n";
}
echo "done\n";
