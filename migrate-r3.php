<?php
// One-off migration: Release 3 schema (follows, venues). Admin-gated; delete after use.
require_once __DIR__ . '/includes/bootstrap.php';
require_login('migrate-r3.php');
if (!is_admin()) exit('admin only');

$steps = [
    ['follows',
     "CREATE TABLE IF NOT EXISTS follows (
        follower_id INT UNSIGNED NOT NULL,
        followed_id INT UNSIGNED NOT NULL,
        created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (follower_id, followed_id),
        INDEX idx_fol_target (followed_id),
        CONSTRAINT fk_fol_a FOREIGN KEY (follower_id) REFERENCES users (id) ON DELETE CASCADE,
        CONSTRAINT fk_fol_b FOREIGN KEY (followed_id) REFERENCES users (id) ON DELETE CASCADE
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"],
    ['venues',
     "CREATE TABLE IF NOT EXISTS venues (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(80) NOT NULL,
        area        VARCHAR(60) NOT NULL,
        description VARCHAR(600) NOT NULL DEFAULT '',
        photo       VARCHAR(255) NULL,
        creator_id  INT UNSIGNED NULL,
        created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_v_area (area),
        INDEX idx_v_name (name),
        CONSTRAINT fk_v_creator FOREIGN KEY (creator_id) REFERENCES users (id) ON DELETE SET NULL
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"],
];

foreach ($steps as [$name, $sql]) {
    try { db()->exec($sql); echo "OK   $name\n"; }
    catch (Throwable $e) { echo "FAIL $name: " . $e->getMessage() . "\n"; }
}

try {
    db()->exec('ALTER TABLE gatherings
        ADD COLUMN venue_id INT UNSIGNED NULL,
        ADD KEY idx_venue (venue_id),
        ADD CONSTRAINT fk_g_venue FOREIGN KEY (venue_id) REFERENCES venues (id) ON DELETE SET NULL');
    echo "OK   gatherings.venue_id\n";
} catch (Throwable $e) {
    echo (str_contains($e->getMessage(), 'Duplicate column') ? "SKIP gatherings.venue_id (already applied)\n" : "FAIL gatherings.venue_id: " . $e->getMessage() . "\n");
}

echo "done\n";
