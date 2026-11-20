<?php
// One-off migration: message attachments. Admin-gated; delete after use.
require_once __DIR__ . '/includes/bootstrap.php';
require_login('migrate-attach.php');
if (!is_admin()) exit('admin only');
try {
    db()->exec('ALTER TABLE messages
        ADD COLUMN attachment      VARCHAR(255) NULL,
        ADD COLUMN attachment_name VARCHAR(200) NULL,
        ADD COLUMN attachment_kind VARCHAR(10)  NULL');
    echo "OK   messages attachment columns\n";
} catch (Throwable $e) {
    echo (str_contains($e->getMessage(), 'Duplicate column') ? "SKIP (already applied)\n" : "FAIL: " . $e->getMessage() . "\n");
}
echo "done\n";
