<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Business owner declines a plan that references their venue. The plan
// keeps running but loses the venue link — the host is notified to pick
// somewhere else so the business is no longer publicly attached to it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');
csrf_verify();
require_login('index.php');

$id   = (int) ($_POST['g'] ?? 0);
$back = $_POST['back'] ?? 'index.php';
if (!preg_match('~^[a-z0-9_./?=&#%-]+$~i', $back) || str_starts_with($back, '//')) $back = 'index.php';

$ev = find_gathering($id);
$v  = $ev && $ev['venue_id'] ? venue_get((int) $ev['venue_id']) : null;

if (!$ev || !$v || (int) $v['creator_id'] !== user_id() || (int) $ev['creator_id'] === user_id()
    || $ev['status'] !== 'active') {
    flash('Nothing to decline.', 'err');
    redirect($back);
}

$upd = "UPDATE gatherings SET venue_id = NULL, venue = 'Venue to be announced' WHERE venue_id = :v AND status = 'active'";
if ($ev['series_id']) {
    $upd .= ' AND series_id = :s';
    db()->prepare($upd)->execute([':v' => $v['id'], ':s' => $ev['series_id']]);
} else {
    $upd .= ' AND id = :g';
    db()->prepare($upd)->execute([':v' => $v['id'], ':g' => $id]);
}

notify((int) $ev['creator_id'], 'venue_reject',
    'The owner of ' . $v['name'] . ' declined "' . $ev['title']
        . '" at their business — please pick another venue so people know where to go.',
    'edit.php?id=' . $id);
flash('Plan declined — the host has been asked to choose a different venue.');
redirect($back);
