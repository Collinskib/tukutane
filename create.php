<?php
// Create a plan — mirrors the sample's client-side validation.

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$back = $_POST['back'] ?? 'index.php';
$fail = function (string $msg) use ($back) {
    flash($msg);
    header('Location: ' . $back);
    exit;
};

$title       = trim($_POST['title'] ?? '');
$category    = $_POST['category'] ?? '';
$area        = $_POST['area'] ?? '';
$venue       = trim($_POST['venue'] ?? '');
$date        = trim($_POST['date'] ?? '');
$cost        = (int) ($_POST['cost'] ?? 0);
$audience    = ($_POST['audience'] ?? 'public') === 'friends' ? 'friends' : 'public';
$description = trim($_POST['description'] ?? '');

if ($title === '' || $venue === '' || $description === '') {
    $fail('Enter a name, venue, and details.');
}
if (!isset(CATEGORIES[$category])) $fail('Pick a valid interest.');
if (!in_array($area, AREAS, true)) $fail('Pick a valid neighbourhood.');
if ($cost < 0 || $cost > 1000000) $fail('Cost must be between 0 and 1,000,000 KSh.');

$eventAt = DateTimeImmutable::createFromFormat(
    'Y-m-d\TH:i', $date, new DateTimeZone('Africa/Nairobi')
);
if (!$eventAt || $eventAt <= new DateTimeImmutable('now', new DateTimeZone('Africa/Nairobi'))) {
    $fail('Choose a future date and time in Kenya.');
}

db()->prepare(
    'INSERT INTO gatherings
     (title, category, area, venue, event_at, cost, audience, description, creator)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
)->execute([
    mb_substr($title, 0, 80), $category, $area, mb_substr($venue, 0, 80),
    $eventAt->format('Y-m-d H:i:s'), $cost, $audience,
    mb_substr($description, 0, 300), SESSION_KEY,
]);

flash('Your plan was saved.');
// new plans land in "My plans", same as the sample
header('Location: index.php?view=mine');
