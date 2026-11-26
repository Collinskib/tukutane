<?php
// Data layer — PDO helpers for every feature.

require_once __DIR__ . '/../config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        // Keep MySQL NOW()/CURRENT_TIMESTAMP in step with PHP's Africa/Nairobi tz —
        // the server clock runs ~8h behind EAT, which made fresh rows read "8h ago".
        $pdo->exec("SET time_zone = '+03:00'");
    }
    return $pdo;
}

// ---------- vocabulary (admin-managed, constant fallback) ----------

const CATEGORIES_FALLBACK = [
    'Football'  => ['⚽', '#dce9d3'],
    'Nightlife' => ['🍹', '#f4d8d0'],
    'Business'  => ['☕', '#f0e0bb'],
    'Campus'    => ['🎓', '#e3def0'],
    'Faith'     => ['📖', '#d9e8f0'],
    'Wellness'  => ['🌿', '#dce9d3'],
    'Dance'     => ['🎵', '#f5d9cb'],
    'Hangouts'  => ['🎲', '#f1e4ca'],
];
const AREAS_FALLBACK = ['Westlands', 'Kilimani', 'South B', 'Nairobi CBD', 'Ngong Road', 'Karen'];

function categories(): array {
    static $cats = null;
    if ($cats === null) {
        try {
            $cats = db()->query('SELECT name, emoji, color FROM categories ORDER BY name')
                        ->fetchAll(PDO::FETCH_KEY_PAIR ? PDO::FETCH_ASSOC : PDO::FETCH_ASSOC);
            $out = [];
            foreach ($cats as $c) $out[$c['name']] = [$c['emoji'], $c['color']];
            $cats = $out ?: CATEGORIES_FALLBACK;
        } catch (Throwable) {
            $cats = CATEGORIES_FALLBACK;
        }
    }
    return $cats;
}

// county => [ sub-county => [estate names] ] — estates are user-added, custom=1
function areas_map(): array {
    static $m = null;
    if ($m === null) {
        $m = [];
        try {
            foreach (db()->query('SELECT county, sub, name FROM areas ORDER BY sort, name') as $r) {
                if ($r['sub'] === '') $m[$r['county']][$r['name']] ??= [];
                else $m[$r['county']][$r['sub']][] = $r['name'];
            }
            ksort($m);
        } catch (Throwable) {
            $m = [];
        }
        if (!$m) $m = ['Nairobi' => array_fill_keys(AREAS_FALLBACK, [])];
    }
    return $m;
}

function counties(): array {
    return array_keys(areas_map());
}

// sub-county names, for one county or flat
function areas(?string $county = null): array {
    $m = areas_map();
    if ($county !== null) return array_keys($m[$county] ?? []);
    return array_merge(...array_map('array_keys', array_values($m)));
}

function estates(string $county, string $area): array {
    return areas_map()[$county][$area] ?? [];
}

function area_valid(string $county, string $area): bool {
    return $county !== '' && in_array($area, areas($county), true);
}

function estate_valid(string $county, string $area, string $estate): bool {
    return $estate !== '' && in_array($estate, estates($county, $area), true);
}

// "Nyayo Estate, Embakasi South" or "Kilimani, Nairobi" from a row
function place_label(array $r, string $fallback = ''): string {
    $e = trim((string) ($r['estate'] ?? ''));
    $a = trim((string) ($r['area'] ?? ''));
    $c = trim((string) ($r['county'] ?? ''));
    if ($e !== '') return $a !== '' ? "$e, $a" : $e;
    if ($a !== '' && $c !== '' && $a !== $c) return "$a, $c";
    return $a !== '' ? $a : ($c !== '' ? $c : $fallback);
}

// ---------- gatherings ----------

// A recurring series shows as ONE card per list — the earliest upcoming
// (or latest past) active occurrence — not a row per date.
function series_next_sql(string $a = 'g', bool $upcoming = true): string {
    $agg = $upcoming ? 'MIN' : 'MAX';
    $cmp = $upcoming ? '>' : '<=';
    return "($a.series_id IS NULL OR $a.event_at = (SELECT $agg(x.event_at) FROM gatherings x
            WHERE x.series_id = $a.series_id AND x.status != 'cancelled' AND x.event_at $cmp NOW()))";
}

// Filtered, paginated event feed. $f keys:
// view(discover|mine|saved|hosted), interest, area, q, when(today|tomorrow|weekend),
// cost(free|paid), sort(soon|popular|featured), page, per
function gatherings(array $f): array {
    $uid  = user_id();
    $sql  = 'SELECT g.*, i.name AS institution_name, gs.freq AS series_freq,
                    (g.creator_id = :um OR (g.creator_id IS NULL AND g.creator = :sm)) AS mine,
                    EXISTS(SELECT 1 FROM rsvps r WHERE r.gathering_id = g.id
                           AND (r.user_id = :uj OR r.session_key = :sj)) AS joined,
                    EXISTS(SELECT 1 FROM saves sv WHERE sv.gathering_id = g.id
                           AND sv.user_id = :usv) AS saved,
                    (SELECT COUNT(*) FROM rsvps r2 WHERE r2.gathering_id = g.id) AS real_rsvps
             FROM gatherings g
             LEFT JOIN institutions i ON i.id = g.institution_id
             LEFT JOIN gathering_series gs ON gs.id = g.series_id
             WHERE g.status != "cancelled" AND g.event_at > NOW()';
    $p = [':um' => $uid, ':sm' => SESSION_KEY, ':uj' => $uid, ':sj' => SESSION_KEY, ':usv' => $uid];

    $meClause = '(g.creator_id = :um2 OR (g.creator_id IS NULL AND g.creator = :sm2))';
    $myJoin   = 'EXISTS(SELECT 1 FROM rsvps rx WHERE rx.gathering_id = g.id
                       AND (rx.user_id = :um3 OR rx.session_key = :sm3))';

    switch ($f['view'] ?? 'discover') {
        case 'mine':
            $sql .= " AND ($meClause OR $myJoin)";
            // my own series dedupes; dates I merely joined always show
            $sql .= ' AND (NOT ' . $meClause . ' OR ' . series_next_sql() . ')';
            $p += [':um2' => $uid, ':sm2' => SESSION_KEY, ':um3' => $uid, ':sm3' => SESSION_KEY];
            break;
        case 'hosted':
            $sql .= " AND $meClause AND " . series_next_sql();
            $p += [':um2' => $uid, ':sm2' => SESSION_KEY];
            break;
        case 'saved':
            $sql .= ' AND EXISTS(SELECT 1 FROM saves s2 WHERE s2.gathering_id = g.id AND s2.user_id = :um4)';
            $p[':um4'] = $uid;
            break;
        default:
            // public feed: not invite-only unless I created or was invited
            $sql .= ' AND ' . series_next_sql() . ' AND g.audience = "public"
                      AND (g.invite_only = 0
                           OR g.creator_id = :um5
                           OR EXISTS(SELECT 1 FROM invites i WHERE i.gathering_id = g.id
                                     AND i.user_id = :um6 AND i.status != "declined"))';
            $p += [':um5' => $uid, ':um6' => $uid];
    }

    if (($f['interest'] ?? '') && $f['interest'] !== 'All interests') {
        $sql .= ' AND g.category = :interest';
        $p[':interest'] = $f['interest'];
    }
    if (!empty($f['interests']) && is_array($f['interests'])) {
        $ph = [];
        foreach (array_values($f['interests']) as $k => $cat) {
            $ph[] = ":int$k";
            $p[":int$k"] = $cat;
        }
        $sql .= ' AND g.category IN (' . implode(',', $ph) . ')';
    }
    if (!empty($f['county'])) {
        $sql .= ' AND g.county = :county';
        $p[':county'] = $f['county'];
    }
    if (!empty($f['area'])) {
        $sql .= ' AND g.area = :area';
        $p[':area'] = $f['area'];
    }
    if (!empty($f['institution'])) {
        $sql .= ' AND g.institution_id = :inst';
        $p[':inst'] = (int) $f['institution'];
    }
    if (!empty($f['q'])) {
        $sql .= " AND CONCAT_WS(' ', g.title, g.area, g.county, g.venue, g.category, g.description) LIKE :q";
        $p[':q'] = '%' . $f['q'] . '%';
    }
    switch ($f['when'] ?? '') {
        case 'today':    $sql .= ' AND DATE(g.event_at) = CURDATE()'; break;
        case 'tomorrow': $sql .= ' AND DATE(g.event_at) = CURDATE() + INTERVAL 1 DAY'; break;
        case 'weekend':  $sql .= ' AND g.event_at < CURDATE() + INTERVAL 7 DAY AND WEEKDAY(g.event_at) >= 5'; break;
    }
    switch ($f['cost'] ?? '') {
        case 'free': $sql .= ' AND g.cost = 0'; break;
        case 'paid': $sql .= ' AND g.cost > 0'; break;
    }

    $sql .= match ($f['sort'] ?? 'soon') {
        'popular'  => ' ORDER BY (g.going_base + real_rsvps) DESC, g.event_at',
        'featured' => ' ORDER BY g.featured DESC, g.event_at',
        default    => ' ORDER BY g.featured DESC, g.event_at',
    };

    $per  = max(1, min(48, (int) ($f['per'] ?? 12)));
    $page = max(1, (int) ($f['page'] ?? 1));
    $sql .= " LIMIT $per OFFSET " . (($page - 1) * $per);

    $stmt = db()->prepare($sql);
    $stmt->execute($p);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function find_gathering(int $id): ?array {
    $stmt = db()->prepare(
        'SELECT g.*, gs.freq AS series_freq,
                (g.creator_id = :um OR (g.creator_id IS NULL AND g.creator = :sm)) AS mine,
                EXISTS(SELECT 1 FROM rsvps r WHERE r.gathering_id = g.id
                       AND (r.user_id = :uj OR r.session_key = :sj)) AS joined,
                EXISTS(SELECT 1 FROM saves sv WHERE sv.gathering_id = g.id
                       AND sv.user_id = :usv) AS saved,
                (SELECT COUNT(*) FROM rsvps r2 WHERE r2.gathering_id = g.id) AS real_rsvps
         FROM gatherings g LEFT JOIN gathering_series gs ON gs.id = g.series_id
         WHERE g.id = :id'
    );
    $stmt->execute([':id' => $id, ':um' => user_id(), ':sm' => SESSION_KEY,
                    ':uj' => user_id(), ':sj' => SESSION_KEY, ':usv' => user_id()]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function going_count(array $event): int {
    return (int) $event['going_base'] + (int) $event['real_rsvps'];
}

// RSVP inside a transaction so capacity can't be overbooked.
// Returns 'joined' | 'cancelled' | 'full' | 'closed'
function rsvp(int $gatheringId, string $action): string {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $g = $pdo->prepare('SELECT capacity, status, event_at, creator_id FROM gatherings WHERE id = :id FOR UPDATE');
        $g->execute([':id' => $gatheringId]);
        $ev = $g->fetch(PDO::FETCH_ASSOC);
        if (!$ev || $ev['status'] === 'cancelled' || strtotime($ev['event_at']) < time()) {
            $pdo->rollBack();
            return 'closed';
        }
        $uid = user_id();
        $key = SESSION_KEY;
        $check = $pdo->prepare('SELECT id FROM rsvps WHERE gathering_id = :g
                                AND (user_id = :u OR session_key = :k)');
        $check->execute([':g' => $gatheringId, ':u' => $uid, ':k' => $key]);
        $existing = $check->fetchColumn();

        if ($action === 'cancel') {
            if ($existing) {
                $pdo->prepare('DELETE FROM rsvps WHERE id = :i')->execute([':i' => $existing]);
            }
            $pdo->commit();
            waitlist_promote($gatheringId);
            return 'cancelled';
        }

        if (!$existing) {
            if ($ev['capacity'] !== null) {
                $n = $pdo->prepare('SELECT COUNT(*) FROM rsvps WHERE gathering_id = :g FOR UPDATE');
                $n->execute([':g' => $gatheringId]);
                if ((int) $n->fetchColumn() >= (int) $ev['capacity']) {
                    $pdo->rollBack();
                    return 'full';
                }
            }
            $pdo->prepare('INSERT INTO rsvps (gathering_id, session_key, user_id) VALUES (:g, :k, :u)')
                ->execute([':g' => $gatheringId, ':k' => $key, ':u' => $uid]);
        }
        $pdo->commit();
        return 'joined';
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function attendees_of(int $gatheringId): array {
    $stmt = db()->prepare(
        'SELECT r.id, r.user_id, r.created_at, u.name, u.photo
         FROM rsvps r LEFT JOIN users u ON u.id = r.user_id
         WHERE r.gathering_id = :g ORDER BY r.created_at'
    );
    $stmt->execute([':g' => $gatheringId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ---------- waitlists ----------

const WAITLIST_TTL = '2 HOUR';

function waitlist_status(int $gatheringId, int $uid): ?array {
    $stmt = db()->prepare('SELECT * FROM waitlists WHERE gathering_id = :g AND user_id = :u');
    $stmt->execute([':g' => $gatheringId, ':u' => $uid]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function waitlist_join(int $gatheringId, int $uid): string {
    $cur = waitlist_status($gatheringId, $uid);
    if ($cur && in_array($cur['status'], ['waiting', 'offered'], true)) return 'already';
    db()->prepare('INSERT INTO waitlists (gathering_id, user_id) VALUES (:g, :u)
                   ON DUPLICATE KEY UPDATE status = "waiting", offered_at = NULL, created_at = NOW()')
        ->execute([':g' => $gatheringId, ':u' => $uid]);
    return 'waiting';
}

function waitlist_leave(int $gatheringId, int $uid): void {
    db()->prepare('DELETE FROM waitlists WHERE gathering_id = :g AND user_id = :u')
        ->execute([':g' => $gatheringId, ':u' => $uid]);
}

// Offer open seats to the next people in line. Returns how many were offered.
function waitlist_promote(int $gatheringId): int {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $g = $pdo->prepare('SELECT capacity, going_base, title FROM gatherings WHERE id = :g AND status = "active" FOR UPDATE');
        $g->execute([':g' => $gatheringId]);
        $ev = $g->fetch(PDO::FETCH_ASSOC);
        if (!$ev || $ev['capacity'] === null) { $pdo->commit(); return 0; }

        $taken = (int) $pdo->query('SELECT COUNT(*) FROM rsvps WHERE gathering_id = ' . (int) $gatheringId)->fetchColumn()
               + (int) $ev['going_base'];
        $offered = (int) $pdo->query('SELECT COUNT(*) FROM waitlists WHERE gathering_id = ' . (int) $gatheringId . ' AND status = "offered"')->fetchColumn();
        $free = (int) $ev['capacity'] - $taken - $offered;
        if ($free <= 0) { $pdo->commit(); return 0; }

        $next = $pdo->prepare('SELECT id, user_id FROM waitlists
                               WHERE gathering_id = :g AND status = "waiting"
                               ORDER BY created_at, id LIMIT ' . $free . ' FOR UPDATE');
        $next->execute([':g' => $gatheringId]);
        $rows = $next->fetchAll(PDO::FETCH_ASSOC);
        $mark = $pdo->prepare('UPDATE waitlists SET status = "offered", offered_at = NOW() WHERE id = :i');
        foreach ($rows as $w) $mark->execute([':i' => $w['id']]);
        $pdo->commit();
        foreach ($rows as $w) {
            notify((int) $w['user_id'], 'waitlist',
                'A spot opened in "' . $ev['title'] . '" — claim it within 2 hours.',
                'event.php?id=' . $gatheringId);
        }
        return count($rows);
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// Claim an offered spot. Returns 'joined' | 'expired' | 'full' | 'none'
function waitlist_claim(int $gatheringId, int $uid): string {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $w = $pdo->prepare('SELECT id, status, offered_at,
                            offered_at > NOW() - INTERVAL ' . WAITLIST_TTL . ' AS fresh
                            FROM waitlists
                            WHERE gathering_id = :g AND user_id = :u FOR UPDATE');
        $w->execute([':g' => $gatheringId, ':u' => $uid]);
        $offer = $w->fetch(PDO::FETCH_ASSOC);
        if (!$offer || $offer['status'] !== 'offered') { $pdo->rollBack(); return 'none'; }
        if (!$offer['fresh']) {
            $pdo->prepare('UPDATE waitlists SET status = "expired" WHERE id = :i')->execute([':i' => $offer['id']]);
            $pdo->commit();
            waitlist_promote($gatheringId);
            return 'expired';
        }
        $g = $pdo->prepare('SELECT capacity, going_base FROM gatherings WHERE id = :g FOR UPDATE');
        $g->execute([':g' => $gatheringId]);
        $ev = $g->fetch(PDO::FETCH_ASSOC);
        $dup = $pdo->prepare('SELECT id FROM rsvps WHERE gathering_id = :g AND user_id = :u');
        $dup->execute([':g' => $gatheringId, ':u' => $uid]);
        if (!$dup->fetchColumn()) {
            if ($ev['capacity'] !== null) {
                $n = $pdo->prepare('SELECT COUNT(*) FROM rsvps WHERE gathering_id = :g FOR UPDATE');
                $n->execute([':g' => $gatheringId]);
                if ((int) $n->fetchColumn() + (int) $ev['going_base'] >= (int) $ev['capacity']) {
                    $pdo->rollBack();
                    return 'full';
                }
            }
            $pdo->prepare('INSERT INTO rsvps (gathering_id, session_key, user_id) VALUES (:g, :k, :u)')
                ->execute([':g' => $gatheringId, ':k' => SESSION_KEY, ':u' => $uid]);
        }
        $pdo->prepare('UPDATE waitlists SET status = "joined" WHERE id = :i')->execute([':i' => $offer['id']]);
        $pdo->commit();
        return 'joined';
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// Decline an offer — passes the seat to the next person.
function waitlist_decline(int $gatheringId, int $uid): void {
    db()->prepare('UPDATE waitlists SET status = "declined"
                   WHERE gathering_id = :g AND user_id = :u AND status = "offered"')
        ->execute([':g' => $gatheringId, ':u' => $uid]);
    waitlist_promote($gatheringId);
}

// Cron sweep: expire stale offers and re-offer the freed seats.
function waitlist_sweep(): int {
    $stale = db()->query('SELECT DISTINCT gathering_id FROM waitlists
                          WHERE status = "offered" AND offered_at < NOW() - INTERVAL ' . WAITLIST_TTL)
                 ->fetchAll(PDO::FETCH_COLUMN);
    if (!$stale) return 0;
    db()->exec('UPDATE waitlists SET status = "expired"
                WHERE status = "offered" AND offered_at < NOW() - INTERVAL ' . WAITLIST_TTL);
    foreach ($stale as $gid) waitlist_promote((int) $gid);
    return count($stale);
}

// ---------- users ----------

function find_user(int $id): ?array {
    $stmt = db()->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function find_user_by_email(string $email): ?array {
    $stmt = db()->prepare('SELECT * FROM users WHERE email = :e');
    $stmt->execute([':e' => mb_strtolower($email)]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// People nearby: discoverable active users, shared interests ranked first.
function people(array $f, int $viewerId): array {
    $sql = 'SELECT u.id, u.name, u.photo, u.bio, u.fun_fact, u.area, u.county, u.estate, u.interests, u.gender, u.age_bracket,
                   (SELECT COUNT(*) FROM friendships fr
                    WHERE fr.status = "accepted"
                      AND ((fr.requester_id = u.id AND fr.addressee_id = :vf1)
                        OR (fr.addressee_id = u.id AND fr.requester_id = :vf2))) AS is_friend,
                   (SELECT fr2.status FROM friendships fr2
                    WHERE (fr2.requester_id = :vf3 AND fr2.addressee_id = u.id)
                       OR (fr2.requester_id = u.id AND fr2.addressee_id = :vf4)) AS rel_status,
                   (SELECT fr3.requester_id FROM friendships fr3
                    WHERE (fr3.requester_id = :vf5 AND fr3.addressee_id = u.id)
                       OR (fr3.requester_id = u.id AND fr3.addressee_id = :vf6)) AS rel_requester
            FROM users u
            WHERE u.status = "active" AND u.discoverable = 1 AND u.id != :self
              AND NOT EXISTS(SELECT 1 FROM blocks b
                    WHERE (b.blocker_id = :vb1 AND b.blocked_id = u.id)
                       OR (b.blocker_id = u.id AND b.blocked_id = :vb2))';
    $p = [':self' => $viewerId];
    foreach (['vf1','vf2','vf3','vf4','vf5','vf6','vb1','vb2'] as $k) $p[":$k"] = $viewerId;

    if (!empty($f['county'])) {
        $sql .= ' AND u.county = :county';
        $p[':county'] = $f['county'];
    }
    if (!empty($f['area'])) {
        $sql .= ' AND u.area = :area';
        $p[':area'] = $f['area'];
    }
    if (!empty($f['interest'])) {
        $sql .= ' AND u.interests LIKE :int';
        $p[':int'] = '%' . $f['interest'] . '%';
    }
    if (!empty($f['q'])) {
        $sql .= ' AND (u.name LIKE :q OR u.bio LIKE :q2)';
        $p[':q'] = '%' . $f['q'] . '%';
        $p[':q2'] = '%' . $f['q'] . '%';
    }

    // shared-interest score for ordering
    $shared = [];
    if (!empty($f['my_interests'])) {
        $cols = [];
        $i = 0;
        foreach ($f['my_interests'] as $int) {
            $cols[] = "(u.interests LIKE :sh$i)";
            $p[":sh$i"] = '%' . $int . '%';
            $i++;
        }
        if ($cols) $shared = $cols;
    }

    // proximity score — same estate > same area > same county — ahead of
    // the interest score so "People nearby" actually starts nearby.
    $prox = [];
    if (!empty($f['my_estate'])) {
        $prox[] = 'IF(u.estate != "" AND u.estate = :mest, 4, 0)';
        $p[':mest'] = $f['my_estate'];
    }
    if (!empty($f['my_area'])) {
        $prox[] = 'IF(u.area != "" AND u.area = :mar, 2, 0)';
        $p[':mar'] = $f['my_area'];
    }
    if (!empty($f['my_county'])) {
        $prox[] = 'IF(u.county != "" AND u.county = :mco, 1, 0)';
        $p[':mco'] = $f['my_county'];
    }
    $sql .= ' ORDER BY ';
    if ($prox)   $sql .= '(' . implode(' + ', $prox) . ') DESC, ';
    if ($shared) $sql .= '(' . implode(' + ', $shared) . ') DESC, ';
    $sql .= 'u.created_at DESC LIMIT 60';

    $stmt = db()->prepare($sql);
    foreach ($p as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // boolean LIKE expressions sum to a score — recompute shared list client-side for display
    foreach ($rows as &$r) $r['shared'] = shared_interests($f['my_interests'] ?? [], $r['interests']);
    return $rows;
}

function shared_interests(array $mine, string $theirs): array {
    $t = array_filter(array_map('trim', explode(',', mb_strtolower($theirs))));
    return array_values(array_filter($mine, fn($i) => in_array(mb_strtolower($i), $t, true)));
}

// ---------- friendships ----------

function friendship(int $a, int $b): ?array {
    $stmt = db()->prepare(
        'SELECT * FROM friendships WHERE
         (requester_id = :a1 AND addressee_id = :b1) OR (requester_id = :b2 AND addressee_id = :a2)'
    );
    $stmt->execute([':a1' => $a, ':b1' => $b, ':a2' => $a, ':b2' => $b]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function friend_action(string $action, int $me, int $other): string {
    $pdo = db();
    $rel = friendship($me, $other);
    switch ($action) {
        case 'send':
            if ($rel) return 'exists';
            $pdo->prepare('INSERT INTO friendships (requester_id, addressee_id) VALUES (:m, :o)')
                ->execute([':m' => $me, ':o' => $other]);
            notify($other, 'friend_request', user()['name'] . ' sent you a friend request.', 'friends.php');
            return 'sent';
        case 'accept':
            if (!$rel || $rel['status'] !== 'pending' || (int) $rel['addressee_id'] !== $me) return 'invalid';
            $pdo->prepare('UPDATE friendships SET status = "accepted" WHERE id = :i')->execute([':i' => $rel['id']]);
            notify((int) $rel['requester_id'], 'friend_accepted', user()['name'] . ' accepted your friend request.', 'people.php');
            return 'accepted';
        case 'decline':
            if (!$rel || $rel['status'] !== 'pending' || (int) $rel['addressee_id'] !== $me) return 'invalid';
            $pdo->prepare('UPDATE friendships SET status = "declined" WHERE id = :i')->execute([':i' => $rel['id']]);
            return 'declined';
        case 'cancel':   // withdraw own pending request
            if (!$rel || $rel['status'] !== 'pending' || (int) $rel['requester_id'] !== $me) return 'invalid';
            $pdo->prepare('DELETE FROM friendships WHERE id = :i')->execute([':i' => $rel['id']]);
            return 'cancelled';
        case 'remove':   // unfriend
            if (!$rel || $rel['status'] !== 'accepted') return 'invalid';
            $pdo->prepare('DELETE FROM friendships WHERE id = :i')->execute([':i' => $rel['id']]);
            return 'removed';
    }
    return 'invalid';
}

function my_friends(int $uid): array {
    $stmt = db()->prepare(
        'SELECT u.id, u.name, u.photo, u.area, u.county FROM friendships f
         JOIN users u ON u.id = IF(f.requester_id = :m1, f.addressee_id, f.requester_id)
         WHERE f.status = "accepted" AND (f.requester_id = :m2 OR f.addressee_id = :m3)
         ORDER BY u.name'
    );
    $stmt->execute([':m1' => $uid, ':m2' => $uid, ':m3' => $uid]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function pending_requests(int $uid): array {
    $stmt = db()->prepare(
        'SELECT f.id AS req_id, f.requester_id, u.name, u.photo, u.area, u.county, u.bio
         FROM friendships f JOIN users u ON u.id = f.requester_id
         WHERE f.addressee_id = :m AND f.status = "pending" ORDER BY f.created_at DESC'
    );
    $stmt->execute([':m' => $uid]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function friend_request_count(int $uid): int {
    $stmt = db()->prepare('SELECT COUNT(*) FROM friendships WHERE addressee_id = :m AND status = "pending"');
    $stmt->execute([':m' => $uid]);
    return (int) $stmt->fetchColumn();
}

function outgoing_requests(int $uid): array {
    $stmt = db()->prepare(
        'SELECT f.id AS req_id, f.addressee_id, u.name, u.photo, u.area, u.county
         FROM friendships f JOIN users u ON u.id = f.addressee_id
         WHERE f.requester_id = :m AND f.status = "pending" ORDER BY f.created_at DESC'
    );
    $stmt->execute([':m' => $uid]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function is_blocked(int $a, int $b): bool {
    $stmt = db()->prepare(
        'SELECT 1 FROM blocks WHERE (blocker_id = :a1 AND blocked_id = :b1)
         OR (blocker_id = :b2 AND blocked_id = :a2)'
    );
    $stmt->execute([':a1' => $a, ':b1' => $b, ':a2' => $a, ':b2' => $b]);
    return (bool) $stmt->fetchColumn();
}

function block_user(int $me, int $other): void {
    db()->prepare('INSERT IGNORE INTO blocks (blocker_id, blocked_id) VALUES (:m, :o)')
        ->execute([':m' => $me, ':o' => $other]);
    db()->prepare('DELETE FROM friendships WHERE
                   (requester_id = :m1 AND addressee_id = :o1)
                OR (requester_id = :o2 AND addressee_id = :m2)')
        ->execute([':m1' => $me, ':o1' => $other, ':m2' => $me, ':o2' => $other]);
}

function unblock_user(int $me, int $other): void {
    db()->prepare('DELETE FROM blocks WHERE blocker_id = :m AND blocked_id = :o')
        ->execute([':m' => $me, ':o' => $other]);
}

// people I have blocked
function blocked_list(int $me): array {
    $stmt = db()->prepare('SELECT u.id, u.name, u.photo, u.area, u.county FROM blocks b
                           JOIN users u ON u.id = b.blocked_id WHERE b.blocker_id = :m');
    $stmt->execute([':m' => $me]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ---------- messaging ----------

function get_conversation(int $me, int $other): ?array {
    $lo = min($me, $other); $hi = max($me, $other);
    $stmt = db()->prepare('SELECT * FROM conversations WHERE user_one = :lo AND user_two = :hi');
    $stmt->execute([':lo' => $lo, ':hi' => $hi]);
    $c = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($c) return $c;
    db()->prepare('INSERT INTO conversations (user_one, user_two) VALUES (:lo, :hi)')
        ->execute([':lo' => $lo, ':hi' => $hi]);
    $c = db()->query('SELECT * FROM conversations WHERE id = ' . (int) db()->lastInsertId())
             ->fetch(PDO::FETCH_ASSOC);
    return $c ?: null;
}

function my_conversations(int $me): array {
    $stmt = db()->prepare(
        'SELECT c.id, c.last_message_at,
                u.id AS other_id, u.name AS other_name, u.photo AS other_photo,
                (SELECT m.body FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_msg,
                (SELECT COUNT(*) FROM messages m2 WHERE m2.conversation_id = c.id
                  AND m2.sender_id != :me1 AND m2.read_at IS NULL) AS unread,
                COALESCE(cs.muted, 0) AS muted
         FROM conversations c
         JOIN users u ON u.id = IF(c.user_one = :me2, c.user_two, c.user_one)
         LEFT JOIN conversation_state cs ON cs.conversation_id = c.id AND cs.user_id = :me3
         WHERE c.user_one = :me4 OR c.user_two = :me5
         ORDER BY c.last_message_at DESC'
    );
    $stmt->execute([':me1' => $me, ':me2' => $me, ':me3' => $me, ':me4' => $me, ':me5' => $me]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function messages_since(int $convId, int $afterId = 0, int $limit = 60): array {
    $stmt = db()->prepare(
        'SELECT m.*, u.name, u.photo FROM messages m
         JOIN users u ON u.id = m.sender_id
         WHERE m.conversation_id = :c AND m.id > :a ORDER BY m.id DESC LIMIT ' . (int) $limit
    );
    $stmt->execute([':c' => $convId, ':a' => $afterId]);
    return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function conversation_for(int $convId, int $me): ?array {
    $stmt = db()->prepare('SELECT * FROM conversations WHERE id = :c AND (user_one = :m1 OR user_two = :m2)');
    $stmt->execute([':c' => $convId, ':m1' => $me, ':m2' => $me]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function send_message(int $convId, int $me, string $body, ?array $att = null): int {
    db()->prepare('INSERT INTO messages (conversation_id, sender_id, body, attachment, attachment_name, attachment_kind)
                   VALUES (:c, :s, :b, :a, :an, :ak)')
        ->execute([':c' => $convId, ':s' => $me, ':b' => mb_substr($body, 0, 1000),
                   ':a' => $att[0] ?? null, ':an' => $att[1] ?? null, ':ak' => $att[2] ?? null]);
    $id = (int) db()->lastInsertId();
    db()->prepare('UPDATE conversations SET last_message_at = NOW() WHERE id = :c')
        ->execute([':c' => $convId]);
    return $id;
}

function mark_conversation_read(int $convId, int $me): void {
    db()->prepare('UPDATE messages SET read_at = NOW()
                   WHERE conversation_id = :c AND sender_id != :m AND read_at IS NULL')
        ->execute([':c' => $convId, ':m' => $me]);
}

function conversation_muted(int $convId, int $me): bool {
    $stmt = db()->prepare('SELECT muted FROM conversation_state WHERE conversation_id = :c AND user_id = :u');
    $stmt->execute([':c' => $convId, ':u' => $me]);
    return (bool) $stmt->fetchColumn();
}

function set_conversation_muted(int $convId, int $me, bool $muted): void {
    db()->prepare('INSERT INTO conversation_state (conversation_id, user_id, muted) VALUES (:c, :u, :m)
                   ON DUPLICATE KEY UPDATE muted = VALUES(muted)')
        ->execute([':c' => $convId, ':u' => $me, ':m' => $muted ? 1 : 0]);
}

function set_typing(int $convId, int $me, bool $typing = true): void {
    db()->prepare('INSERT INTO conversation_state (conversation_id, user_id, typing_at)
                   VALUES (:c, :u, NOW())
                   ON DUPLICATE KEY UPDATE typing_at = ' . ($typing ? 'NOW()' : 'NULL'))
        ->execute([':c' => $convId, ':u' => $me]);
}

function peer_typing(int $convId, int $other): bool {
    $stmt = db()->prepare('SELECT typing_at > NOW() - INTERVAL 6 SECOND
                           FROM conversation_state WHERE conversation_id = :c AND user_id = :u');
    $stmt->execute([':c' => $convId, ':u' => $other]);
    return (bool) $stmt->fetchColumn();
}

function unread_messages(int $me): int {
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM messages m JOIN conversations c ON c.id = m.conversation_id
         LEFT JOIN conversation_state cs ON cs.conversation_id = c.id AND cs.user_id = :m4
         WHERE (c.user_one = :m1 OR c.user_two = :m2) AND m.sender_id != :m3 AND m.read_at IS NULL
           AND COALESCE(cs.muted, 0) = 0'
    );
    $stmt->execute([':m1' => $me, ':m2' => $me, ':m3' => $me, ':m4' => $me]);
    return (int) $stmt->fetchColumn();
}

// ---------- notifications / saves / invites / reports ----------

function notify(int $uid, string $type, string $body, string $link = ''): void {
    db()->prepare('INSERT INTO notifications (user_id, type, body, link) VALUES (:u, :t, :b, :l)')
        ->execute([':u' => $uid, ':t' => $type, ':b' => $body, ':l' => $link]);
}

function unread_notifications(int $uid): int {
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :u AND read_at IS NULL');
    $stmt->execute([':u' => $uid]);
    return (int) $stmt->fetchColumn();
}

function notifications_for(int $uid, int $limit = 30): array {
    $stmt = db()->prepare(
        'SELECT * FROM notifications WHERE user_id = :u ORDER BY id DESC LIMIT ' . (int) $limit
    );
    $stmt->execute([':u' => $uid]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function toggle_save(int $uid, int $gatheringId): bool {
    $pdo = db();
    $ex = $pdo->prepare('SELECT 1 FROM saves WHERE user_id = :u AND gathering_id = :g');
    $ex->execute([':u' => $uid, ':g' => $gatheringId]);
    if ($ex->fetchColumn()) {
        $pdo->prepare('DELETE FROM saves WHERE user_id = :u AND gathering_id = :g')
            ->execute([':u' => $uid, ':g' => $gatheringId]);
        return false;
    }
    $pdo->prepare('INSERT INTO saves (user_id, gathering_id) VALUES (:u, :g)')
        ->execute([':u' => $uid, ':g' => $gatheringId]);
    return true;
}

function invite_user(int $gatheringId, int $invitee, int $inviter): string {
    try {
        db()->prepare('INSERT INTO invites (gathering_id, user_id, inviter_id) VALUES (:g, :u, :i)')
            ->execute([':g' => $gatheringId, ':u' => $invitee, ':i' => $inviter]);
        $g = find_gathering($gatheringId);
        notify($invitee, 'invite', user()['name'] . ' invited you to "' . $g['title'] . '".', 'event.php?id=' . $gatheringId);
        return 'invited';
    } catch (PDOException) {
        return 'exists';
    }
}

function respond_invite(int $gatheringId, int $uid, string $response): string {
    $stmt = db()->prepare('SELECT id FROM invites WHERE gathering_id = :g AND user_id = :u AND status = "pending"');
    $stmt->execute([':g' => $gatheringId, ':u' => $uid]);
    $id = $stmt->fetchColumn();
    if (!$id) return 'invalid';
    db()->prepare('UPDATE invites SET status = :s WHERE id = :i')
        ->execute([':s' => $response === 'accept' ? 'accepted' : 'declined', ':i' => $id]);
    if ($response === 'accept') {
        return rsvp($gatheringId, 'join');
    }
    return 'declined';
}

function my_invites(int $uid): array {
    $stmt = db()->prepare(
        'SELECT i.id AS invite_id, i.status, g.*, u.name AS inviter_name
         FROM invites i JOIN gatherings g ON g.id = i.gathering_id
         JOIN users u ON u.id = i.inviter_id
         WHERE i.user_id = :u AND g.event_at > NOW() ORDER BY i.created_at DESC'
    );
    $stmt->execute([':u' => $uid]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function add_report(int $reporter, string $type, int $targetId, string $reason): void {
    db()->prepare('INSERT INTO reports (reporter_id, target_type, target_id, reason) VALUES (:r, :t, :i, :w)')
        ->execute([':r' => $reporter, ':t' => $type, ':i' => $targetId, ':w' => mb_substr($reason, 0, 300)]);
}

// ---------- password resets ----------

function create_reset_token(int $uid): string {
    $token = bin2hex(random_bytes(24));
    db()->prepare('DELETE FROM password_resets WHERE user_id = :u')->execute([':u' => $uid]);
    db()->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at)
                   VALUES (:u, :h, DATE_ADD(NOW(), INTERVAL 1 HOUR))')
        ->execute([':u' => $uid, ':h' => hash('sha256', $token)]);
    return $token;
}

function consume_reset_token(string $token): ?int {
    $h = hash('sha256', $token);
    $stmt = db()->prepare('SELECT user_id FROM password_resets WHERE token_hash = :h AND expires_at > NOW()');
    $stmt->execute([':h' => $h]);
    $uid = $stmt->fetchColumn();
    if ($uid) {
        db()->prepare('DELETE FROM password_resets WHERE user_id = :u')->execute([':u' => $uid]);
        return (int) $uid;
    }
    return null;
}

// ---------- local interest groups ----------

function groups_list(array $f): array {
    $uid = user_id();
    $sql = 'SELECT g.*, u.name AS creator_name,
                   (SELECT COUNT(*) FROM grp_members m WHERE m.group_id = g.id AND m.status = "active") AS members,
                   (SELECT m2.status FROM grp_members m2 WHERE m2.group_id = g.id AND m2.user_id = :um) AS my_status,
                   (SELECT m3.role FROM grp_members m3 WHERE m3.group_id = g.id AND m3.user_id = :um2 AND m3.status = "active") AS my_role
            FROM grps g JOIN users u ON u.id = g.creator_id WHERE 1=1';
    $p = [':um' => $uid, ':um2' => $uid];
    if (($f['view'] ?? '') === 'mine') {
        $sql .= ' AND EXISTS(SELECT 1 FROM grp_members mx WHERE mx.group_id = g.id AND mx.user_id = :um3 AND mx.status IN ("active","pending"))';
        $p[':um3'] = $uid;
    }
    if (($f['category'] ?? '') && $f['category'] !== 'All interests') {
        $sql .= ' AND g.category = :cat'; $p[':cat'] = $f['category'];
    }
    if (!empty($f['county'])) {
        $sql .= ' AND g.county = :county'; $p[':county'] = $f['county'];
    }
    if (!empty($f['area'])) {
        $sql .= ' AND g.area = :area'; $p[':area'] = $f['area'];
    }
    if (!empty($f['q'])) {
        $sql .= " AND CONCAT_WS(' ', g.name, g.description, g.area, g.county, g.category) LIKE :q";
        $p[':q'] = '%' . $f['q'] . '%';
    }
    $sql .= ' ORDER BY members DESC, g.created_at DESC LIMIT 60';
    $stmt = db()->prepare($sql);
    $stmt->execute($p);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function group_get(int $id): ?array {
    $stmt = db()->prepare('SELECT g.*, u.name AS creator_name, u.photo AS creator_photo,
                           (SELECT COUNT(*) FROM grp_members m WHERE m.group_id = g.id AND m.status = "active") AS members
                           FROM grps g JOIN users u ON u.id = g.creator_id WHERE g.id = :id');
    $stmt->execute([':id' => $id]);
    $g = $stmt->fetch(PDO::FETCH_ASSOC);
    return $g ?: null;
}

function group_membership(int $gid, int $uid): ?array {
    $stmt = db()->prepare('SELECT * FROM grp_members WHERE group_id = :g AND user_id = :u');
    $stmt->execute([':g' => $gid, ':u' => $uid]);
    $m = $stmt->fetch(PDO::FETCH_ASSOC);
    return $m ?: null;
}

function group_members(int $gid, string $status = 'active'): array {
    $stmt = db()->prepare('SELECT m.*, u.name, u.photo, u.area, u.county FROM grp_members m
                           JOIN users u ON u.id = m.user_id
                           WHERE m.group_id = :g AND m.status = :s ORDER BY m.role = "admin" DESC, m.created_at');
    $stmt->execute([':g' => $gid, ':s' => $status]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function group_admins(int $gid): array {
    $stmt = db()->prepare('SELECT user_id FROM grp_members WHERE group_id = :g AND role = "admin" AND status = "active"');
    $stmt->execute([':g' => $gid]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function group_create(int $uid, array $d): int {
    db()->prepare('INSERT INTO grps (name, description, category, area, county, estate, creator_id, join_policy)
                   VALUES (:n, :d, :c, :a, :cy, :es, :u, :p)')
        ->execute([':n' => $d['name'], ':d' => $d['description'], ':c' => $d['category'],
                   ':a' => $d['area'], ':cy' => $d['county'] ?? '', ':es' => $d['estate'] ?? '', ':u' => $uid, ':p' => $d['join_policy']]);
    $gid = (int) db()->lastInsertId();
    db()->prepare('INSERT INTO grp_members (group_id, user_id, role, status) VALUES (:g, :u, "admin", "active")')
        ->execute([':g' => $gid, ':u' => $uid]);
    return $gid;
}

function group_update(int $gid, array $d): void {
    db()->prepare('UPDATE grps SET name = :n, description = :d, category = :c, area = :a, county = :cy, estate = :es, join_policy = :p WHERE id = :id')
        ->execute([':n' => $d['name'], ':d' => $d['description'], ':c' => $d['category'],
                   ':a' => $d['area'], ':cy' => $d['county'] ?? '', ':es' => $d['estate'] ?? '', ':p' => $d['join_policy'], ':id' => $gid]);
}

// returns 'joined' | 'requested' | 'rejoined' | 'rerequested' | null (already active/pending)
function group_join(int $gid, int $uid, string $policy): ?string {
    $m = group_membership($gid, $uid);
    $want = $policy === 'open' ? 'active' : 'pending';
    if (!$m) {
        db()->prepare('INSERT INTO grp_members (group_id, user_id, status) VALUES (:g, :u, :s)')
            ->execute([':g' => $gid, ':u' => $uid, ':s' => $want]);
        return $want === 'active' ? 'joined' : 'requested';
    }
    if ($m['status'] === 'declined') {
        db()->prepare('UPDATE grp_members SET status = :s, role = "member" WHERE group_id = :g AND user_id = :u')
            ->execute([':s' => $want, ':g' => $gid, ':u' => $uid]);
        return $want === 'active' ? 'rejoined' : 'rerequested';
    }
    return null;
}

function group_leave(int $gid, int $uid): bool {
    $m = group_membership($gid, $uid);
    if (!$m || $m['status'] !== 'active') return false;
    if ($m['role'] === 'admin') {
        $admins = group_admins($gid);
        if (count($admins) === 1) return false; // sole admin can't leave; delete instead
    }
    db()->prepare('DELETE FROM grp_members WHERE group_id = :g AND user_id = :u')
        ->execute([':g' => $gid, ':u' => $uid]);
    return true;
}

function group_delete(int $gid): void {
    db()->prepare('DELETE FROM grps WHERE id = :g')->execute([':g' => $gid]);
}

// admin approves/declines a pending request; returns the target uid or null
function group_review(int $gid, int $target, bool $approve): ?int {
    $m = group_membership($gid, $target);
    if (!$m || $m['status'] !== 'pending') return null;
    if ($approve) {
        db()->prepare('UPDATE grp_members SET status = "active" WHERE group_id = :g AND user_id = :u')
            ->execute([':g' => $gid, ':u' => $target]);
    } else {
        db()->prepare('UPDATE grp_members SET status = "declined" WHERE group_id = :g AND user_id = :u')
            ->execute([':g' => $gid, ':u' => $target]);
    }
    return $target;
}

function group_remove_member(int $gid, int $target): bool {
    $m = group_membership($gid, $target);
    if (!$m) return false;
    if ($m['role'] === 'admin' && count(group_admins($gid)) === 1) return false;
    db()->prepare('DELETE FROM grp_members WHERE group_id = :g AND user_id = :u')
        ->execute([':g' => $gid, ':u' => $target]);
    return true;
}

// ---------- group posts (reddit-style feed) ----------

function gposts_list(int $gid, int $uid, string $sort = 'hot'): array {
    $order = $sort === 'new'
        ? 'p.pinned DESC, p.created_at DESC'
        : 'p.pinned DESC, p.score DESC, p.comment_count DESC, p.created_at DESC';
    $stmt = db()->prepare('SELECT p.*, u.name AS author_name, u.photo AS author_photo,
                           (SELECT v.vote FROM grp_post_votes v WHERE v.post_id = p.id AND v.user_id = :u) AS my_vote
                           FROM grp_posts p JOIN users u ON u.id = p.user_id
                           WHERE p.group_id = :g ORDER BY ' . $order . ' LIMIT 100');
    $stmt->execute([':g' => $gid, ':u' => $uid]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function gpost_get(int $pid): ?array {
    $stmt = db()->prepare('SELECT * FROM grp_posts WHERE id = :i');
    $stmt->execute([':i' => $pid]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    return $p ?: null;
}

function gpost_create(int $gid, int $uid, string $title, string $body): int {
    db()->prepare('INSERT INTO grp_posts (group_id, user_id, title, body) VALUES (:g, :u, :t, :b)')
        ->execute([':g' => $gid, ':u' => $uid, ':t' => $title, ':b' => $body !== '' ? $body : null]);
    return (int) db()->lastInsertId();
}

function gpost_delete(int $pid): void {
    db()->prepare('DELETE FROM grp_post_votes WHERE post_id = :p')->execute([':p' => $pid]);
    $cids = db()->prepare('SELECT id FROM grp_comments WHERE post_id = :p');
    $cids->execute([':p' => $pid]);
    $ids = $cids->fetchAll(PDO::FETCH_COLUMN);
    if ($ids) {
        $in = implode(',', array_map('intval', $ids));
        db()->exec("DELETE FROM grp_comment_votes WHERE comment_id IN ($in)");
        db()->exec("DELETE FROM grp_comments WHERE id IN ($in)");
    }
    db()->prepare('DELETE FROM grp_posts WHERE id = :p')->execute([':p' => $pid]);
}

function gcomments_list(int $pid, int $uid): array {
    $stmt = db()->prepare('SELECT c.*, u.name AS author_name, u.photo AS author_photo,
                           (SELECT v.vote FROM grp_comment_votes v WHERE v.comment_id = c.id AND v.user_id = :u) AS my_vote
                           FROM grp_comments c JOIN users u ON u.id = c.user_id
                           WHERE c.post_id = :p ORDER BY c.parent_id IS NOT NULL, c.score DESC, c.created_at');
    $stmt->execute([':p' => $pid, ':u' => $uid]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function gcomment_get(int $cid): ?array {
    $stmt = db()->prepare('SELECT * FROM grp_comments WHERE id = :i');
    $stmt->execute([':i' => $cid]);
    $c = $stmt->fetch(PDO::FETCH_ASSOC);
    return $c ?: null;
}

function gcomment_add(int $pid, int $gid, int $uid, ?int $parent, string $body): int {
    db()->prepare('INSERT INTO grp_comments (post_id, group_id, parent_id, user_id, body) VALUES (:p, :g, :pa, :u, :b)')
        ->execute([':p' => $pid, ':g' => $gid, ':pa' => $parent, ':u' => $uid, ':b' => $body]);
    db()->prepare('UPDATE grp_posts SET comment_count = comment_count + 1 WHERE id = :p')->execute([':p' => $pid]);
    return (int) db()->lastInsertId();
}

function gcomment_delete(int $cid): void {
    db()->prepare('UPDATE grp_comments SET deleted = 1, body = "" WHERE id = :i')->execute([':i' => $cid]);
}

// up/down vote on a post or comment; returns [new score, my vote (-1|0|1)]
function gvote(string $kind, int $id, int $uid, int $dir): array {
    [$table, $col] = $kind === 'post'
        ? ['grp_posts', 'post_id'] : ['grp_comments', 'comment_id'];
    $vtable = $kind === 'post' ? 'grp_post_votes' : 'grp_comment_votes';
    $dir = $dir > 0 ? 1 : ($dir < 0 ? -1 : 0);

    $cur = db()->prepare("SELECT vote FROM $vtable WHERE $col = :i AND user_id = :u");
    $cur->execute([':i' => $id, ':u' => $uid]);
    $old = $cur->fetchColumn();
    $old = $old === false ? 0 : (int) $old;

    $new = $old === $dir ? 0 : $dir; // same vote toggles off
    if ($new === 0) {
        db()->prepare("DELETE FROM $vtable WHERE $col = :i AND user_id = :u")
            ->execute([':i' => $id, ':u' => $uid]);
    } else {
        db()->prepare("INSERT INTO $vtable ($col, user_id, vote) VALUES (:i, :u, :v)
                       ON DUPLICATE KEY UPDATE vote = :v2")
            ->execute([':i' => $id, ':u' => $uid, ':v' => $new, ':v2' => $new]);
    }
    db()->prepare("UPDATE $table SET score = COALESCE((SELECT SUM(vote) FROM $vtable WHERE $col = :i2), 0) WHERE id = :i3")
        ->execute([':i2' => $id, ':i3' => $id]);
    $score = db()->prepare("SELECT score FROM $table WHERE id = :i");
    $score->execute([':i' => $id]);
    return [(int) $score->fetchColumn(), $new];
}

// ---------- event group chat ----------

// host + confirmed (logged-in) attendees may use the chat;
// a block between the user and the host locks them out
function can_event_chat(array $ev, int $uid): bool {
    if (!$uid) return false;
    if (!empty($ev['creator_id']) && (int) $ev['creator_id'] === $uid) return true;
    if (!empty($ev['creator_id']) && is_blocked($uid, (int) $ev['creator_id'])) return false;
    if (!empty($ev['mine'])) return true;
    $stmt = db()->prepare('SELECT 1 FROM rsvps WHERE gathering_id = :g AND user_id = :u LIMIT 1');
    $stmt->execute([':g' => $ev['id'], ':u' => $uid]);
    return (bool) $stmt->fetchColumn();
}

function event_messages(int $gid, int $after = 0, int $limit = 60): array {
    $stmt = db()->prepare('SELECT m.*, u.name AS sender_name, u.photo AS sender_photo
                           FROM event_messages m JOIN users u ON u.id = m.sender_id
                           WHERE m.gathering_id = :g AND m.id > :a ORDER BY m.id LIMIT ' . (int) $limit);
    $stmt->execute([':g' => $gid, ':a' => $after]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function event_message_get(int $mid): ?array {
    $stmt = db()->prepare('SELECT m.*, u.name AS sender_name FROM event_messages m
                           JOIN users u ON u.id = m.sender_id WHERE m.id = :i');
    $stmt->execute([':i' => $mid]);
    $m = $stmt->fetch(PDO::FETCH_ASSOC);
    return $m ?: null;
}

function event_pinned(int $gid): ?array {
    $stmt = db()->prepare('SELECT m.*, u.name AS sender_name FROM event_messages m
                           JOIN users u ON u.id = m.sender_id
                           WHERE m.gathering_id = :g AND m.pinned = 1 ORDER BY m.id DESC LIMIT 1');
    $stmt->execute([':g' => $gid]);
    $m = $stmt->fetch(PDO::FETCH_ASSOC);
    return $m ?: null;
}

function event_chat_send(int $gid, int $uid, string $body): int {
    db()->prepare('INSERT INTO event_messages (gathering_id, sender_id, body) VALUES (:g, :u, :b)')
        ->execute([':g' => $gid, ':u' => $uid, ':b' => mb_substr($body, 0, 1000)]);
    return (int) db()->lastInsertId();
}

function event_chat_muted(int $gid, int $uid): bool {
    $stmt = db()->prepare('SELECT muted FROM event_chat_state WHERE gathering_id = :g AND user_id = :u');
    $stmt->execute([':g' => $gid, ':u' => $uid]);
    return (bool) $stmt->fetchColumn();
}

function event_chat_set_muted(int $gid, int $uid, bool $muted): void {
    db()->prepare('INSERT INTO event_chat_state (gathering_id, user_id, muted) VALUES (:g, :u, :m)
                   ON DUPLICATE KEY UPDATE muted = VALUES(muted)')
        ->execute([':g' => $gid, ':u' => $uid, ':m' => $muted ? 1 : 0]);
}

function event_chat_mark_read(int $gid, int $uid): void {
    db()->prepare('INSERT INTO event_chat_state (gathering_id, user_id, last_read_id)
                   VALUES (:g, :u, (SELECT COALESCE(MAX(id),0) FROM event_messages WHERE gathering_id = :g2))
                   ON DUPLICATE KEY UPDATE last_read_id = VALUES(last_read_id)')
        ->execute([':g' => $gid, ':u' => $uid, ':g2' => $gid]);
}

// all logged-in participants (host + attendees) except $except, minus chat-muted users
function event_chat_audience(int $gid, int $except): array {
    $stmt = db()->prepare(
        'SELECT DISTINCT r.user_id FROM rsvps r WHERE r.gathering_id = :g AND r.user_id IS NOT NULL
         UNION SELECT creator_id FROM gatherings WHERE id = :g2 AND creator_id IS NOT NULL');
    $stmt->execute([':g' => $gid, ':g2' => $gid]);
    $uids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    return array_values(array_filter($uids, fn($u) => $u !== $except && !event_chat_muted($gid, $u)));
}

// event group chats the user belongs to (host or RSVP'd attendee, not blocked by host)
function my_event_chats(int $uid): array {
    $stmt = db()->prepare(
        'SELECT g.id, g.title, g.event_at, g.cover, g.creator_id,
                (SELECT m.body FROM event_messages m WHERE m.gathering_id = g.id ORDER BY m.id DESC LIMIT 1) AS last_msg,
                (SELECT MAX(m.id) FROM event_messages m WHERE m.gathering_id = g.id) AS last_id,
                (SELECT COUNT(*) FROM event_messages m
                  WHERE m.gathering_id = g.id AND m.sender_id != :us
                    AND m.id > COALESCE(s.last_read_id, 0)) AS unread,
                COALESCE(s.muted, 0) AS muted
         FROM gatherings g
         LEFT JOIN event_chat_state s ON s.gathering_id = g.id AND s.user_id = :u
         WHERE (g.creator_id = :u2 OR EXISTS(SELECT 1 FROM rsvps r WHERE r.gathering_id = g.id AND r.user_id = :u3))
           AND NOT EXISTS(SELECT 1 FROM blocks b WHERE b.blocker_id = g.creator_id AND b.blocked_id = :u4)
         ORDER BY (last_id IS NULL), last_id DESC');
    $stmt->execute([':u' => $uid, ':u2' => $uid, ':u3' => $uid, ':u4' => $uid, ':us' => $uid]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ---------- organizer follows ----------

function is_following(int $me, int $them): bool {
    $stmt = db()->prepare('SELECT 1 FROM follows WHERE follower_id = :a AND followed_id = :b');
    $stmt->execute([':a' => $me, ':b' => $them]);
    return (bool) $stmt->fetchColumn();
}

function follow_set(int $me, int $them, bool $on): void {
    if ($on) {
        db()->prepare('INSERT IGNORE INTO follows (follower_id, followed_id) VALUES (:a, :b)')
            ->execute([':a' => $me, ':b' => $them]);
    } else {
        db()->prepare('DELETE FROM follows WHERE follower_id = :a AND followed_id = :b')
            ->execute([':a' => $me, ':b' => $them]);
    }
}

function follower_count(int $uid): int {
    $stmt = db()->prepare('SELECT COUNT(*) FROM follows WHERE followed_id = :u');
    $stmt->execute([':u' => $uid]);
    return (int) $stmt->fetchColumn();
}

function follower_ids(int $uid): array {
    $stmt = db()->prepare('SELECT follower_id FROM follows WHERE followed_id = :u');
    $stmt->execute([':u' => $uid]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

// a host's gatherings — upcoming (default) or past
function host_gatherings(int $uid, bool $upcoming = true, int $limit = 12): array {
    $cmp = $upcoming ? '>=' : '<';
    $ord = $upcoming ? 'ASC' : 'DESC';
    $stmt = db()->prepare(
        "SELECT g.*, gs.freq AS series_freq,
                (SELECT COUNT(*) FROM rsvps r WHERE r.gathering_id = g.id) AS real_rsvps
         FROM gatherings g LEFT JOIN gathering_series gs ON gs.id = g.series_id
         WHERE g.creator_id = :u AND g.status != 'cancelled'
         AND g.event_at $cmp NOW() AND " . series_next_sql('g', $upcoming) .
        " ORDER BY g.event_at $ord LIMIT " . (int) $limit);
    $stmt->execute([':u' => $uid]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ---------- venue profiles ----------

function venues_list(array $f): array {
    $sql = 'SELECT v.*, u.name AS creator_name, i.name AS institution_name,
                   (SELECT COUNT(*) FROM gatherings g WHERE g.venue_id = v.id AND g.status = "active" AND g.event_at >= NOW()) AS upcoming
            FROM venues v LEFT JOIN users u ON u.id = v.creator_id
            LEFT JOIN institutions i ON i.id = v.institution_id WHERE 1=1';
    $p = [];
    if (!empty($f['county'])) { $sql .= ' AND v.county = :c'; $p[':c'] = $f['county']; }
    if (!empty($f['area'])) { $sql .= ' AND v.area = :a'; $p[':a'] = $f['area']; }
    if (!empty($f['institution'])) { $sql .= ' AND v.institution_id = :inst'; $p[':inst'] = (int) $f['institution']; }
    if (!empty($f['q'])) {
        $sql .= " AND CONCAT_WS(' ', v.name, v.description, v.area, v.county) LIKE :q";
        $p[':q'] = '%' . $f['q'] . '%';
    }
    // closest to the viewer first, then busiest
    $sql .= ' ORDER BY (v.estate <> "" AND v.estate = :mes) DESC,
                       (v.area <> "" AND v.area = :mea) DESC,
                       (v.county <> "" AND v.county = :mec) DESC,
                       upcoming DESC, v.name LIMIT 80';
    $p[':mes'] = $f['me_estate'] ?? '';
    $p[':mea'] = $f['me_area'] ?? '';
    $p[':mec'] = $f['me_county'] ?? '';
    $stmt = db()->prepare($sql);
    $stmt->execute($p);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// anyone who runs a business page can be messaged by any member
function is_business_owner(int $uid): bool {
    $stmt = db()->prepare('SELECT 1 FROM venues WHERE creator_id = :u LIMIT 1');
    $stmt->execute([':u' => $uid]);
    return (bool) $stmt->fetchColumn();
}

function venue_get(int $id): ?array {
    $stmt = db()->prepare('SELECT v.*, u.name AS creator_name FROM venues v
                           LEFT JOIN users u ON u.id = v.creator_id WHERE v.id = :i');
    $stmt->execute([':i' => $id]);
    $v = $stmt->fetch(PDO::FETCH_ASSOC);
    return $v ?: null;
}

function venue_events(int $id): array {
    $stmt = db()->prepare("SELECT g.*, gs.freq AS series_freq,
                                  (SELECT COUNT(*) FROM rsvps r WHERE r.gathering_id = g.id) AS real_rsvps
                           FROM gatherings g LEFT JOIN gathering_series gs ON gs.id = g.series_id
                           WHERE g.venue_id = :v AND g.status = 'active' AND g.event_at >= NOW()
                             AND " . series_next_sql() . "
                           ORDER BY g.event_at LIMIT 24");
    $stmt->execute([':v' => $id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function venue_by_name(string $name): ?int {
    $stmt = db()->prepare('SELECT id FROM venues WHERE name = :n LIMIT 1');
    $stmt->execute([':n' => trim($name)]);
    $id = $stmt->fetchColumn();
    return $id ? (int) $id : null;
}

function venue_create(int $uid, array $d): int {
    db()->prepare('INSERT INTO venues (name, area, county, estate, description, photo, creator_id, institution_id, campus_id)
                   VALUES (:n, :a, :cy, :es, :d, :p, :u, :inst, :camp)')
        ->execute([':n' => $d['name'], ':a' => $d['area'], ':cy' => $d['county'] ?? '', ':es' => $d['estate'] ?? '',
                   ':d' => $d['description'], ':p' => $d['photo'] ?? null, ':u' => $uid,
                   ':inst' => $d['institution_id'] ?? null, ':camp' => $d['campus_id'] ?? null]);
    return (int) db()->lastInsertId();
}

function venue_update(int $id, array $d): void {
    db()->prepare('UPDATE venues SET name = :n, area = :a, county = :cy, estate = :es, description = :d,
                          institution_id = :inst, campus_id = :camp'
                  . (isset($d['photo']) ? ', photo = :p' : '') . ' WHERE id = :i')
        ->execute([':n' => $d['name'], ':a' => $d['area'], ':cy' => $d['county'] ?? '', ':es' => $d['estate'] ?? '',
                   ':d' => $d['description'], ':inst' => $d['institution_id'] ?? null, ':camp' => $d['campus_id'] ?? null]
                  + (isset($d['photo']) ? [':p' => $d['photo']] : []) + [':i' => $id]);
}

function venues_all(): array {
    return db()->query('SELECT id, name, area, county FROM venues ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
}

// ---------- institutions & campuses ----------

const INSTITUTION_TYPES = [
    'university'           => 'University',
    'university_college'   => 'University college',
    'national_polytechnic' => 'National polytechnic',
    'tvet_college'         => 'TVET college',
    'vocational_centre'    => 'Vocational training centre',
];

function institution_type_label(string $t): string {
    return INSTITUTION_TYPES[$t] ?? ucwords(str_replace('_', ' ', $t));
}

function institutions_list(array $f = []): array {
    $sql = 'SELECT i.*, (SELECT COUNT(*) FROM campuses c WHERE c.institution_id = i.id) AS campus_count
            FROM institutions i WHERE i.status = "active"';
    $p = [];
    if (!empty($f['type']))   { $sql .= ' AND i.type = :t';   $p[':t'] = $f['type']; }
    if (!empty($f['county'])) { $sql .= ' AND i.county = :c'; $p[':c'] = $f['county']; }
    if (!empty($f['q']))      { $sql .= ' AND i.name LIKE :q'; $p[':q'] = '%' . $f['q'] . '%'; }
    // universities first, then alphabetical within type rank
    $sql .= ' ORDER BY FIELD(i.type, "university","university_college","national_polytechnic","tvet_college","vocational_centre"), i.name LIMIT 600';
    $stmt = db()->prepare($sql);
    $stmt->execute($p);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// typeahead search: prefix matches rank above substring matches.
// $county restricts to local institutions; $area (sub-county) ranks
// town-matching campuses/institutions first. Empty query returns the
// local list for dropdown-on-focus.
function institutions_search(string $q, int $limit = 12, string $county = '', string $area = ''): array {
    $q = trim($q);
    $w = 'status = "active"';
    $p = [];
    if ($q !== '') { $w .= ' AND name LIKE :q'; $p[':q'] = '%' . $q . '%'; }
    // universities first nationally; within each tier the viewer's county
    // floats to the top, then sub-county town matches, then prefix matches.
    $order = 'type IN ("university","university_college") DESC, ';
    if ($county !== '') { $order .= '(county = :county) DESC, '; $p[':county'] = $county; }
    $order .= 'FIELD(type, "university","university_college","national_polytechnic","tvet_college","vocational_centre"), ';
    if ($area !== '') { $order .= '(:area LIKE CONCAT("%", town, "%")) DESC, '; $p[':area'] = $area; }
    if ($q !== '')    { $order .= '(name LIKE :pre) DESC, '; $p[':pre'] = $q . '%'; }
    $order .= 'name';
    $stmt = db()->prepare("SELECT id, name, type, county, town FROM institutions WHERE $w ORDER BY $order LIMIT :lim");
    foreach ($p as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function institution_get(int $id): ?array {
    $stmt = db()->prepare('SELECT * FROM institutions WHERE id = :i');
    $stmt->execute([':i' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function campuses_for(int $institutionId): array {
    $stmt = db()->prepare('SELECT * FROM campuses WHERE institution_id = :i ORDER BY name');
    $stmt->execute([':i' => $institutionId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function campus_get(int $id): ?array {
    $stmt = db()->prepare('SELECT * FROM campuses WHERE id = :i');
    $stmt->execute([':i' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// validates an institution/campus pair from a form; returns [institution_id, campus_id]
// or null when the pair is invalid (campus must belong to the institution)
function institution_pair(?int $instId, ?int $campId): ?array {
    if (!$instId || !institution_get($instId)) return null;
    if (!$campId) return [$instId, null];
    $c = campus_get($campId);
    return ($c && (int) $c['institution_id'] === $instId) ? [$instId, $campId] : [$instId, null];
}

// upcoming public events associated with an institution (any campus)
function institution_events(int $id, int $limit = 24): array {
    $stmt = db()->prepare(
        'SELECT g.*, gs.freq AS series_freq,
                (SELECT COUNT(*) FROM rsvps r WHERE r.gathering_id = g.id) AS real_rsvps
         FROM gatherings g LEFT JOIN gathering_series gs ON gs.id = g.series_id
         WHERE g.institution_id = :i AND g.status = "active"
           AND g.event_at >= NOW() AND g.audience = "public"
           AND ' . series_next_sql() . '
         ORDER BY g.event_at LIMIT :lim'
    );
    $stmt->bindValue(':i', $id, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function institution_venues(int $id): array {
    $stmt = db()->prepare(
        'SELECT v.*, u.name AS creator_name FROM venues v
         LEFT JOIN users u ON u.id = v.creator_id
         WHERE v.institution_id = :i ORDER BY v.name LIMIT 40'
    );
    $stmt->execute([':i' => $id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
