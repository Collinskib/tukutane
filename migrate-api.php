<?php
// One-off migration: api_tokens table for mobile-app Bearer auth.
// Admin-gated; safe to re-run.
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
require_admin();

header('Content-Type: text/plain');
echo "migrating...\n";

db()->exec('CREATE TABLE IF NOT EXISTS api_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_used DATETIME NULL,
    expires_at DATETIME NOT NULL,
    INDEX (user_id),
    INDEX (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
echo "api_tokens ok\n";

echo "done\n";
