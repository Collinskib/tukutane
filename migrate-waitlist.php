<?php
// One-off migration: create the waitlists table. Admin-gated; delete after use.
require_once __DIR__ . '/includes/bootstrap.php';
require_login('migrate-waitlist.php');
if (!is_admin()) exit('admin only');

db()->exec('CREATE TABLE IF NOT EXISTS waitlists (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gathering_id INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    status       ENUM(\'waiting\',\'offered\',\'joined\',\'expired\',\'declined\') NOT NULL DEFAULT \'waiting\',
    offered_at   TIMESTAMP NULL DEFAULT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_wl (gathering_id, user_id),
    INDEX idx_wl (gathering_id, status, created_at),
    CONSTRAINT fk_wl_gath FOREIGN KEY (gathering_id) REFERENCES gatherings (id) ON DELETE CASCADE,
    CONSTRAINT fk_wl_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

echo 'waitlists table ready';
