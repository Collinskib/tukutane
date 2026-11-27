<?php
// One-off migration: drop the Campus category (institutions are becoming a
// separate app) and move its events to Hangouts. Runs from deploy.sh over CLI;
// also admin-gated if hit in the browser. Idempotent — delete after cleanup.
require_once __DIR__ . '/includes/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    require_login('migrate-cat.php');
    if (!is_admin()) exit('admin only');
}

db()->exec("UPDATE gatherings SET category='Hangouts' WHERE category='Campus'");
db()->exec("DELETE FROM categories WHERE name='Campus'");

echo 'campus category removed';
