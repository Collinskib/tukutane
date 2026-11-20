<?php
// One-off migration: Release 2 schema (groups, event chat, typing, recurring).
// Admin-gated; delete after use.
require_once __DIR__ . '/includes/bootstrap.php';
require_login('migrate-r2.php');
if (!is_admin()) exit('admin only');

$steps = [];

$steps[] = ['conversation_state.typing_at',
    'ALTER TABLE conversation_state ADD COLUMN typing_at DATETIME NULL DEFAULT NULL'];

$steps[] = ['grps',
    "CREATE TABLE IF NOT EXISTS grps (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(80) NOT NULL,
        description VARCHAR(600) NOT NULL DEFAULT '',
        category    VARCHAR(40) NOT NULL,
        area        VARCHAR(60) NOT NULL,
        creator_id  INT UNSIGNED NOT NULL,
        join_policy ENUM('open','approval') NOT NULL DEFAULT 'open',
        created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_grp_area (area),
        INDEX idx_grp_cat (category),
        CONSTRAINT fk_grp_creator FOREIGN KEY (creator_id) REFERENCES users (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"];

$steps[] = ['grp_members',
    "CREATE TABLE IF NOT EXISTS grp_members (
        group_id   INT UNSIGNED NOT NULL,
        user_id    INT UNSIGNED NOT NULL,
        role       ENUM('admin','member') NOT NULL DEFAULT 'member',
        status     ENUM('active','pending','declined') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (group_id, user_id),
        INDEX idx_gm_user (user_id, status),
        CONSTRAINT fk_gm_grp FOREIGN KEY (group_id) REFERENCES grps (id) ON DELETE CASCADE,
        CONSTRAINT fk_gm_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"];

$steps[] = ['event_messages',
    "CREATE TABLE IF NOT EXISTS event_messages (
        id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        gathering_id INT UNSIGNED NOT NULL,
        sender_id    INT UNSIGNED NOT NULL,
        body         VARCHAR(1000) NOT NULL,
        pinned       TINYINT(1) NOT NULL DEFAULT 0,
        created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_em (gathering_id, id),
        CONSTRAINT fk_em_gath FOREIGN KEY (gathering_id) REFERENCES gatherings (id) ON DELETE CASCADE,
        CONSTRAINT fk_em_user FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"];

$steps[] = ['event_chat_state',
    "CREATE TABLE IF NOT EXISTS event_chat_state (
        gathering_id INT UNSIGNED NOT NULL,
        user_id      INT UNSIGNED NOT NULL,
        muted        TINYINT(1) NOT NULL DEFAULT 0,
        last_read_id INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (gathering_id, user_id),
        CONSTRAINT fk_ecs_gath FOREIGN KEY (gathering_id) REFERENCES gatherings (id) ON DELETE CASCADE,
        CONSTRAINT fk_ecs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"];

$steps[] = ['reports.target_type enum',
    "ALTER TABLE reports MODIFY target_type ENUM('user','gathering','message','event_message') NOT NULL"];

$steps[] = ['gathering_series',
    "CREATE TABLE IF NOT EXISTS gathering_series (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        freq       ENUM('weekly','monthly') NOT NULL,
        creator_id INT UNSIGNED NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_gs_creator FOREIGN KEY (creator_id) REFERENCES users (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"];

foreach ($steps as [$name, $sql]) {
    try { db()->exec($sql); echo "OK   $name\n"; }
    catch (Throwable $e) {
        // duplicate column / already-exists is fine on re-runs
        echo (str_contains($e->getMessage(), 'Duplicate column') ? "SKIP $name (already applied)\n" : "FAIL $name: " . $e->getMessage() . "\n");
    }
}

// gatherings.series_id separately so we can skip cleanly if present
try {
    db()->exec('ALTER TABLE gatherings
        ADD COLUMN series_id INT UNSIGNED NULL,
        ADD KEY idx_series (series_id),
        ADD CONSTRAINT fk_g_series FOREIGN KEY (series_id) REFERENCES gathering_series (id) ON DELETE SET NULL');
    echo "OK   gatherings.series_id\n";
} catch (Throwable $e) {
    echo (str_contains($e->getMessage(), 'Duplicate column') ? "SKIP gatherings.series_id (already applied)\n" : "FAIL gatherings.series_id: " . $e->getMessage() . "\n");
}

echo "done\n";
