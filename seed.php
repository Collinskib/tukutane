<?php
// Seed the sample gatherings George's mockup showed.
// Usage: php seed.php   (safe to re-run — skips if samples exist)

require_once __DIR__ . '/includes/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit('Run from the command line: php seed.php' . PHP_EOL);
}

$exists = db()->query('SELECT COUNT(*) FROM gatherings WHERE sample = 1')->fetchColumn();
if ($exists > 0) {
    exit("Sample gatherings already seeded ($exists). Nothing to do.\n");
}

// [title, category, area, venue, days, hour, cost, count, description]
$sample = [
    ['Matchday in Westlands', 'Football', 'Westlands', 'Sports café', 3, 18, 0, 12,
        'Catch the match on a big screen with other football fans. Food and drinks are available for purchase.'],
    ['Afrobeats dance social', 'Dance', 'Kilimani', 'Dance studio', 4, 17, 600, 8,
        'Learn a few steps, then stay for a social dance session. Beginners welcome.'],
    ['Coffee and business ideas', 'Business', 'Nairobi CBD', 'Coffee shop', 4, 10, 0, 6,
        'Bring an idea or just come to listen and meet other aspiring founders.'],
    ['Saturday Bible study', 'Faith', 'South B', 'Community hall', 4, 9, 0, 5,
        'Read a short passage together and discuss it over tea.'],
    ['Campus board game afternoon', 'Campus', 'Nairobi CBD', 'Campus common room', 5, 14, 0, 7,
        'Meet other students over a few rounds of board games.'],
    ['Cocktails and conversation', 'Nightlife', 'Kilimani', 'Rooftop lounge', 5, 19, 0, 9,
        'An adults-only evening at a public lounge. Order and pay for your own drinks.'],
    ['Morning walk and goal setting', 'Wellness', 'Karen', 'Garden entrance', 6, 8, 300, 4,
        'Take a relaxed walk and share a personal goal for the week.'],
    ['Board games with friends', 'Hangouts', 'Ngong Road', 'Game café', 6, 15, 500, 6,
        'Bring a friend or join a table when you arrive.'],
];

// Same date math as the sample's JS: spread events around the coming weekend.
$kenya = new DateTimeImmutable('now', new DateTimeZone('Africa/Nairobi'));
$weekday = (int) $kenya->format('w');              // 0=Sun … 6=Sat
$untilSaturday = ((6 - $weekday) + 7) % 7 ?: 7;

$stmt = db()->prepare(
    'INSERT INTO gatherings
     (title, category, area, venue, event_at, cost, audience, description, sample, going_base)
     VALUES (?, ?, ?, ?, ?, ?, "public", ?, 1, ?)'
);

foreach ($sample as [$title, $category, $area, $venue, $days, $hour, $cost, $count, $description]) {
    $eventAt = $kenya->modify('+' . ($untilSaturday + $days - 4) . ' days')
                     ->setTime($hour, 0)
                     ->format('Y-m-d H:i:s');
    $stmt->execute([$title, $category, $area, $venue, $eventAt, $cost, $description, $count]);
    echo "seeded: $title ($eventAt)\n";
}
echo "Done.\n";
