<?php
// Join / cancel an RSVP.

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id     = (int) ($_POST['id'] ?? 0);
$action = $_POST['action'] === 'cancel' ? 'cancel' : 'join';
$back   = $_POST['back'] ?? 'index.php';

$event = find_gathering($id);
if (!$event) {
    flash('That gathering no longer exists.');
    header('Location: ' . $back);
    exit;
}

if ($action === 'join') {
    db()->prepare('INSERT IGNORE INTO rsvps (gathering_id, session_key) VALUES (?, ?)')
        ->execute([$id, SESSION_KEY]);
    flash("You're going to " . $event['title'] . '.');
} else {
    db()->prepare('DELETE FROM rsvps WHERE gathering_id = ? AND session_key = ?')
        ->execute([$id, SESSION_KEY]);
    flash('RSVP cancelled.');
}

header('Location: ' . $back);
