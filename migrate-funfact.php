<?php
// One-off migration: users.fun_fact — the "my fun fact / silly joke" line shown on people cards.
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_admin();

echo '<pre>';
try {
    db()->exec("ALTER TABLE users ADD COLUMN fun_fact VARCHAR(160) NULL");
    echo "OK   users.fun_fact\n";
} catch (Throwable $e) {
    echo (str_contains($e->getMessage(), 'Duplicate') ? "SKIP users.fun_fact\n" : "FAIL users.fun_fact: " . $e->getMessage() . "\n");
}
echo "done\n";
