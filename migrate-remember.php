<?php
// One-off migration: remember_tokens table for "keep me logged in" cookies.
// Admin-gated; safe to re-run.
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
require_admin();

header('Content-Type: text/plain');
echo "migrating...\n";

db()->exec('CREATE TABLE IF NOT EXISTS remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    selector CHAR(16) NOT NULL UNIQUE,
    token_hash CHAR(64) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_used DATETIME NULL,
    expires_at DATETIME NOT NULL,
    INDEX (user_id),
    INDEX (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
echo "remember_tokens ok\n";

echo "done\n";
