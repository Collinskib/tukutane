<?php
// One-off migration: demographics + email verification on users. Admin-gated, idempotent.
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_admin();

echo '<pre>';
$cols = [
    'gender'        => "VARCHAR(16) NULL",
    'age_bracket'   => "VARCHAR(10) NULL",
    'email_verified'=> "TINYINT(1) NOT NULL DEFAULT 0",
    'verify_token'  => "VARCHAR(64) NULL",
];
foreach ($cols as $c => $def) {
    try {
        db()->exec("ALTER TABLE users ADD COLUMN $c $def");
        echo "OK   users.$c\n";
    } catch (Throwable $e) {
        echo (str_contains($e->getMessage(), 'Duplicate') ? "SKIP users.$c\n" : "FAIL users.$c: " . $e->getMessage() . "\n");
    }
}
// existing accounts were created before verification — mark them verified
db()->exec("UPDATE users SET email_verified = 1 WHERE email_verified = 0 AND status = 'active'");
echo "backfilled email_verified for existing users\ndone\n";
