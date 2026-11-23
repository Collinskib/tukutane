<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
if (!user()) { http_response_code(401); exit(json_encode(['error' => 'login'])); }

$uid = user_id();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
try { csrf_verify(); } catch (Throwable) { http_response_code(419); exit(json_encode(['error' => 'refresh the page'])); }

$kind = ($_POST['kind'] ?? '') === 'comment' ? 'comment' : 'post';
$id   = (int) ($_POST['id'] ?? 0);
$dir  = (int) ($_POST['dir'] ?? 0);
if (!in_array($dir, [-1, 0, 1], true) || !$id) { http_response_code(400); exit(json_encode(['error' => 'bad vote'])); }

// resolve group + membership for permission check
if ($kind === 'post') {
    $row = gpost_get($id);
} else {
    $row = gcomment_get($id);
}
if (!$row) { http_response_code(404); exit(json_encode(['error' => 'not found'])); }
$gid = (int) $row['group_id'];
$me = group_membership($gid, $uid);
if (!$me || $me['status'] !== 'active') { http_response_code(403); exit(json_encode(['error' => 'members only'])); }

[$score, $mine] = gvote($kind, $id, $uid, $dir);
echo json_encode(['ok' => true, 'score' => $score, 'mine' => $mine]);
