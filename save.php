<?php
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !user()) {
    echo json_encode(['ok' => false, 'redirect' => 'auth.php?next=' . urlencode($_SERVER['HTTP_REFERER'] ?? 'index.php')]);
    exit;
}
csrf_verify();
$saved = toggle_save(user_id(), (int) ($_POST['id'] ?? 0));
echo json_encode(['ok' => true, 'saved' => $saved, 'label' => $saved ? '★ Saved' : '☆ Save']);
