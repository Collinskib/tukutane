<?php
// One-off migration: group posts, comments and votes (Reddit-style group feeds).
// Admin-gated; safe to re-run.
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
require_admin();

header('Content-Type: text/plain');
echo "migrating...\n";

db()->exec('CREATE TABLE IF NOT EXISTS grp_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    user_id INT NOT NULL,
    title VARCHAR(160) NOT NULL,
    body TEXT NULL,
    score INT NOT NULL DEFAULT 0,
    comment_count INT NOT NULL DEFAULT 0,
    pinned TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (group_id), INDEX (score), INDEX (pinned)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
echo "grp_posts ok\n";

db()->exec('CREATE TABLE IF NOT EXISTS grp_post_votes (
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    vote TINYINT NOT NULL,
    PRIMARY KEY (post_id, user_id),
    INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
echo "grp_post_votes ok\n";

db()->exec('CREATE TABLE IF NOT EXISTS grp_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    group_id INT NOT NULL,
    parent_id INT NULL,
    user_id INT NOT NULL,
    body VARCHAR(1000) NOT NULL,
    score INT NOT NULL DEFAULT 0,
    deleted TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (post_id), INDEX (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
echo "grp_comments ok\n";

db()->exec('CREATE TABLE IF NOT EXISTS grp_comment_votes (
    comment_id INT NOT NULL,
    user_id INT NOT NULL,
    vote TINYINT NOT NULL,
    PRIMARY KEY (comment_id, user_id),
    INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
echo "grp_comment_votes ok\n";

db()->exec("ALTER TABLE reports MODIFY target_type ENUM('user','gathering','message','event_message','gpost','gcomment') NOT NULL");
echo "reports enum ok\n";

echo "done\n";
