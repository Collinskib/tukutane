<?php
// Migration: institution directory (institutions + campuses) and optional
// institution/campus association columns on users, gatherings and venues.
// Admin-gated; re-runnable — every step is idempotent.
require_once __DIR__ . '/includes/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    require_login('migrate-institutions.php');
    if (!is_admin()) exit('admin only');
}

$steps = [
    ['institutions',
     "CREATE TABLE IF NOT EXISTS institutions (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(160) NOT NULL,
        type       ENUM('university','university_college','national_polytechnic','tvet_college','vocational_centre') NOT NULL,
        county     VARCHAR(40) NOT NULL DEFAULT '',
        subcounty  VARCHAR(60) NULL,
        town       VARCHAR(60) NULL,
        ref        VARCHAR(80) NULL COMMENT 'official registration / charter reference',
        source     ENUM('cue','tveta','manual') NOT NULL DEFAULT 'manual',
        status     ENUM('active','inactive') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_inst_name (name),
        KEY idx_inst_type (type),
        KEY idx_inst_county (county),
        KEY idx_inst_status (status)
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"],
    ['campuses',
     "CREATE TABLE IF NOT EXISTS campuses (
        id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        institution_id INT UNSIGNED NOT NULL,
        name           VARCHAR(160) NOT NULL,
        county         VARCHAR(40) NOT NULL DEFAULT '',
        subcounty      VARCHAR(60) NULL,
        town           VARCHAR(60) NULL,
        lat            DECIMAL(10,7) NULL,
        lng            DECIMAL(10,7) NULL,
        UNIQUE KEY uq_camp (institution_id, name),
        KEY idx_camp_inst (institution_id),
        CONSTRAINT fk_camp_inst FOREIGN KEY (institution_id) REFERENCES institutions (id) ON DELETE CASCADE
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"],
];

foreach ($steps as [$name, $sql]) {
    try { db()->exec($sql); echo "OK   $name\n"; }
    catch (Throwable $e) { echo "FAIL $name: " . $e->getMessage() . "\n"; }
}

// Association columns — NULL = not associated. Institution association is
// always optional and independent of the row's own geography.
$alters = [
    ['users', 'institution_id', 'institutions'],
    ['users', 'campus_id',      'campuses'],
    ['gatherings', 'institution_id', 'institutions'],
    ['gatherings', 'campus_id',      'campuses'],
    ['venues', 'institution_id', 'institutions'],
    ['venues', 'campus_id',      'campuses'],
];
foreach ($alters as [$table, $col, $ref]) {
    try {
        db()->exec("ALTER TABLE $table
            ADD COLUMN $col INT UNSIGNED NULL,
            ADD KEY idx_{$col} ($col),
            ADD CONSTRAINT fk_{$table}_{$col} FOREIGN KEY ($col) REFERENCES $ref (id) ON DELETE SET NULL");
        echo "OK   $table.$col\n";
    } catch (Throwable $e) {
        echo (str_contains($e->getMessage(), 'Duplicate column')
            ? "SKIP $table.$col (already applied)\n"
            : "FAIL $table.$col: " . $e->getMessage() . "\n");
    }
}

echo "done\n";
