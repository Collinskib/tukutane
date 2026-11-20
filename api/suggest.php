<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
if (!user()) { http_response_code(401); exit(json_encode(['error' => 'login'])); }

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) exit('[]');

// Reuse the feed query so visibility rules match what the user would see.
$rows = gatherings(['q' => $q, 'per' => 6, 'sort' => 'soon']);
$out = array_map(fn($g) => [
    'id'    => (int) $g['id'],
    'title' => $g['title'],
    'meta'  => when($g['event_at']) . ' · ' . place_label($g),
], $rows);
echo json_encode($out);
