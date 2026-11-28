<?php
// One-off migration: push_tokens table for Expo push notifications, plus a
// pushed_at column on notifications so cron/push.php can drain new rows.
// Admin-gated; safe to re-run. Delete from the docroot after it runs.
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
require_admin();

header('Content-Type: text/plain');
echo "migrating...\n";

db()->exec('CREATE TABLE IF NOT EXISTS push_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(120) NOT NULL UNIQUE,
    platform VARCHAR(16) NOT NULL DEFAULT "",
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_used DATETIME NULL,
    INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
echo "push_tokens ok\n";

$col = db()->query("SHOW COLUMNS FROM notifications LIKE 'pushed_at'")->fetchColumn();
if (!$col) {
    db()->exec('ALTER TABLE notifications ADD COLUMN pushed_at DATETIME NULL');
    // Existing rows are already delivered in-app — never retro-push them.
    db()->exec('UPDATE notifications SET pushed_at = NOW() WHERE pushed_at IS NULL');
    echo "notifications.pushed_at added, existing rows marked pushed\n";
} else {
    echo "notifications.pushed_at already present\n";
}

echo "done\n";
