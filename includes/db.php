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

function areas(): array {
    static $areas = null;
    if ($areas === null) {
        try {
            $areas = db()->query('SELECT name FROM areas ORDER BY sort, name')
                         ->fetchAll(PDO::FETCH_COLUMN) ?: AREAS_FALLBACK;
        } catch (Throwable) {
            $areas = AREAS_FALLBACK;
        }
    }
    return $areas;
}

// ---------- gatherings ----------

// Filtered, paginated event feed. $f keys:
// view(discover|mine|saved|hosted), interest, area, q, when(today|tomorrow|weekend),
// cost(free|paid), sort(soon|popular|featured), page, per
function gatherings(array $f): array {
    $uid  = user_id();
    $sql  = 'SELECT g.*,
                    (g.creator_id = :um OR (g.creator_id IS NULL AND g.creator = :sm)) AS mine,
                    EXISTS(SELECT 1 FROM rsvps r WHERE r.gathering_id = g.id
                           AND (r.user_id = :uj OR r.session_key = :sj)) AS joined,
                    EXISTS(SELECT 1 FROM saves sv WHERE sv.gathering_id = g.id
                           AND sv.user_id = :usv) AS saved,
                    (SELECT COUNT(*) FROM rsvps r2 WHERE r2.gathering_id = g.id) AS real_rsvps
             FROM gatherings g WHERE g.status != "cancelled" AND g.event_at > NOW()';
    $p = [':um' => $uid, ':sm' => SESSION_KEY, ':uj' => $uid, ':sj' => SESSION_KEY, ':usv' => $uid];

    $meClause = '(g.creator_id = :um2 OR (g.creator_id IS NULL AND g.creator = :sm2))';
    $myJoin   = 'EXISTS(SELECT 1 FROM rsvps rx WHERE rx.gathering_id = g.id
                       AND (rx.user_id = :um3 OR rx.session_key = :sm3))';

    switch ($f['view'] ?? 'discover') {
        case 'mine':
            $sql .= " AND ($meClause OR $myJoin)";
            $p += [':um2' => $uid, ':sm2' => SESSION_KEY, ':um3' => $uid, ':sm3' => SESSION_KEY];
            break;
        case 'hosted':
            $sql .= " AND $meClause";
            $p += [':um2' => $uid, ':sm2' => SESSION_KEY];
            break;
        case 'saved':
            $sql .= ' AND EXISTS(SELECT 1 FROM saves s2 WHERE s2.gathering_id = g.id AND s2.user_id = :um4)';
            $p[':um4'] = $uid;
            break;
        default:
            // public feed: not invite-only unless I created or was invited
            $sql .= ' AND g.audience = "public"
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
    if (($f['area'] ?? '') && $f['area'] !== 'All Nairobi') {
        $sql .= ' AND g.area = :area';
        $p[':area'] = $f['area'];
    }
    if (!empty($f['q'])) {
        $sql .= " AND CONCAT_WS(' ', g.title, g.area, g.venue, g.category, g.description) LIKE :q";
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
        'SELECT g.*,
                (g.creator_id = :um OR (g.creator_id IS NULL AND g.creator = :sm)) AS mine,
                EXISTS(SELECT 1 FROM rsvps r WHERE r.gathering_id = g.id
                       AND (r.user_id = :uj OR r.session_key = :sj)) AS joined,
                EXISTS(SELECT 1 FROM saves sv WHERE sv.gathering_id = g.id
                       AND sv.user_id = :usv) AS saved,
                (SELECT COUNT(*) FROM rsvps r2 WHERE r2.gathering_id = g.id) AS real_rsvps
         FROM gatherings g WHERE g.id = :id'
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
    $sql = 'SELECT u.id, u.name, u.photo, u.bio, u.area, u.interests,
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
    $sql .= $shared
        ? ' ORDER BY (' . implode(' + ', array_map(fn($c) => "$c", $shared)) . ') DESC, u.created_at DESC'
        : ' ORDER BY u.created_at DESC';
    $sql .= ' LIMIT 60';

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
        'SELECT u.id, u.name, u.photo, u.area FROM friendships f
         JOIN users u ON u.id = IF(f.requester_id = :m1, f.addressee_id, f.requester_id)
         WHERE f.status = "accepted" AND (f.requester_id = :m2 OR f.addressee_id = :m3)
         ORDER BY u.name'
    );
    $stmt->execute([':m1' => $uid, ':m2' => $uid, ':m3' => $uid]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function pending_requests(int $uid): array {
    $stmt = db()->prepare(
        'SELECT f.id AS req_id, f.requester_id, u.name, u.photo, u.area, u.bio
         FROM friendships f JOIN users u ON u.id = f.requester_id
         WHERE f.addressee_id = :m AND f.status = "pending" ORDER BY f.created_at DESC'
    );
    $stmt->execute([':m' => $uid]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function outgoing_requests(int $uid): array {
    $stmt = db()->prepare(
        'SELECT f.id AS req_id, f.addressee_id, u.name, u.photo, u.area
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

function send_message(int $convId, int $me, string $body): int {
    db()->prepare('INSERT INTO messages (conversation_id, sender_id, body) VALUES (:c, :s, :b)')
        ->execute([':c' => $convId, ':s' => $me, ':b' => mb_substr($body, 0, 1000)]);
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
