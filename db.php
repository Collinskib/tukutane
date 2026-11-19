<?php
// Shared data + helpers for Tukutane.

require_once __DIR__ . '/config.php';

const CATEGORIES = [
    'Football'  => ['⚽', '#dce9d3'],
    'Nightlife' => ['🍹', '#f4d8d0'],
    'Business'  => ['☕', '#f0e0bb'],
    'Campus'    => ['🎓', '#e3def0'],
    'Faith'     => ['📖', '#d9e8f0'],
    'Wellness'  => ['🌿', '#dce9d3'],
    'Dance'     => ['🎵', '#f5d9cb'],
    'Hangouts'  => ['🎲', '#f1e4ca'],
];

const AREAS = ['Westlands', 'Kilimani', 'South B', 'Nairobi CBD', 'Ngong Road', 'Karen'];

function e(?string $s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function when(string $eventAt): string {
    // "Sat, Nov 22, 3:00 PM EAT"
    return (new DateTimeImmutable($eventAt, new DateTimeZone('Africa/Nairobi')))
            ->format('D, M j, g:i A') . ' EAT';
}

function price(int $cost): string {
    return $cost > 0 ? 'KSh ' . number_format($cost) : 'Free entry';
}

function gatherings(string $view, string $interest, string $area, string $query): array {
    $sql = 'SELECT g.*,
                   g.creator = :me            AS mine,
                   EXISTS(SELECT 1 FROM rsvps r
                          WHERE r.gathering_id = g.id AND r.session_key = :me2) AS joined,
                   (SELECT COUNT(*) FROM rsvps r2 WHERE r2.gathering_id = g.id) AS real_rsvps
            FROM gatherings g
            WHERE g.event_at > NOW()';
    $params = [':me' => SESSION_KEY, ':me2' => SESSION_KEY];

    if ($view === 'mine') {
        // created by me OR joined by me
        $sql .= ' AND (g.creator = :me3 OR EXISTS(
                    SELECT 1 FROM rsvps r3
                    WHERE r3.gathering_id = g.id AND r3.session_key = :me4))';
        $params[':me3'] = SESSION_KEY;
        $params[':me4'] = SESSION_KEY;
    } else {
        $sql .= " AND g.audience = 'public'";
    }
    if ($interest !== 'All interests') {
        $sql .= ' AND g.category = :interest';
        $params[':interest'] = $interest;
    }
    if ($area !== 'All Nairobi') {
        $sql .= ' AND g.area = :area';
        $params[':area'] = $area;
    }
    if ($query !== '') {
        $sql .= " AND CONCAT_WS(' ', g.title, g.area, g.venue, g.category, g.description) LIKE :q";
        $params[':q'] = '%' . $query . '%';
    }
    $sql .= ' ORDER BY g.event_at';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function find_gathering(int $id): ?array {
    $stmt = db()->prepare(
        'SELECT g.*,
                g.creator = :me AS mine,
                EXISTS(SELECT 1 FROM rsvps r
                       WHERE r.gathering_id = g.id AND r.session_key = :me2) AS joined,
                (SELECT COUNT(*) FROM rsvps r2 WHERE r2.gathering_id = g.id) AS real_rsvps
         FROM gatherings g WHERE g.id = :id'
    );
    $stmt->execute([':id' => $id, ':me' => SESSION_KEY, ':me2' => SESSION_KEY]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function going_count(array $event): int {
    return (int) $event['going_base'] + (int) $event['real_rsvps'];
}

function flash(?string $msg = null): ?string {
    if ($msg !== null) {
        $_SESSION['flash'] = $msg;
        return null;
    }
    $msg = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $msg;
}
