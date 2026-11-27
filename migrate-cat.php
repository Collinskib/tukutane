<?php
// One-off migration: drop the Campus category (institutions are becoming a
// separate app) and move its events to Hangouts. Admin-gated; delete after use.
require_once __DIR__ . '/includes/bootstrap.php';
require_login('migrate-cat.php');
if (!is_admin()) exit('admin only');

db()->exec("UPDATE gatherings SET category='Hangouts' WHERE category='Campus'");
db()->exec("DELETE FROM categories WHERE name='Campus'");

echo 'campus category removed';
