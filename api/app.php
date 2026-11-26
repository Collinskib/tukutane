<?php
// Nipange mobile-app API. Bearer-token auth; JSON in/out.
// Usage: /api/app/?r=<route>  (POST body is JSON, merged into $_POST)
require_once __DIR__ . '/_app_boot.php';

$r = trim((string) ($_GET['r'] ?? ''), '/');
$me = api_user(); // resolves session OR Bearer token; null when logged out
$method = $_SERVER['REQUEST_METHOD'];

switch ($r) {

// ---------- public ----------

case 'meta':
    jout([
        'categories' => array_keys(categories()),
        'counties'   => counties(),
        'areas'      => areas_map(),
        'genders'    => GENDERS,
        'ages'       => AGE_BRACKETS,
        'venues'     => array_map(fn($v) => ['id' => (int) $v['id'], 'name' => $v['name'], 'place' => place_label($v)], venues_all()),
    ]);

case 'auth/login': {
    if ($method !== 'POST') jerr('POST only', 405);
    $u = find_user_by_email(trim((string) pin('email')));
    if (!$u || !password_verify((string) pin('password'), $u['pass_hash'])) jerr('Wrong email or password.', 401);
    if ($u['status'] !== 'active') jerr('This account is suspended.', 403);
    jout(['ok' => true, 'token' => api_issue_token((int) $u['id']),
          'user' => api_self($u), 'incomplete' => profile_incomplete($u)]);
}

case 'auth/register': {
    if ($method !== 'POST') jerr('POST only', 405);
    [$area, $estate] = resolve_location((string) pin('county'));
    $err = register_user(
        trim((string) pin('email')), (string) pin('password'), trim((string) pin('name')),
        $area, $estate, (string) pin('county'),
        (string) pin('gender'), (string) pin('age_bracket'), (string) pin('fun_fact')
    );
    if ($err) jerr($err);
    $u = find_user_by_email(trim((string) pin('email')));
    jout(['ok' => true, 'token' => api_issue_token((int) $u['id']),
          'user' => api_self($u), 'incomplete' => false]);
}

case 'auth/google': {
    // accepts a Google id_token from expo-auth-session; aud must match the web
    // client id or an id in config's GOOGLE_APP_CLIENT_IDS (comma-separated)
    if ($method !== 'POST') jerr('POST only', 405);
    $idt = (string) pin('id_token');
    if ($idt === '') jerr('Missing id_token.');
    $info = http_request('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idt));
    if (empty($info['email'])) jerr('Google sign-in failed — try again.', 401);
    $auds = array_filter(array_map('trim', explode(',', (defined('GOOGLE_APP_CLIENT_IDS') ? GOOGLE_APP_CLIENT_IDS : '') . ',' . (defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : ''))));
    if (!in_array($info['aud'] ?? '', $auds, true)) jerr('Google client not recognised.', 401);
    if (empty($info['email_verified']) || $info['email_verified'] === 'false') jerr('Google email is not verified.', 401);
    $email = mb_strtolower(trim($info['email']));
    $u = find_user_by_email($email);
    if (!$u) {
        $name = trim(mb_substr((string) ($info['name'] ?? ''), 0, 60)) ?: (strstr($email, '@', true) ?: 'Nipange member');
        db()->prepare('INSERT INTO users (email, pass_hash, name, photo, email_verified) VALUES (:e, :p, :n, :ph, 1)')
            ->execute([':e' => $email, ':p' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
                       ':n' => $name, ':ph' => oauth_avatar((string) ($info['picture'] ?? ''))]);
        $u = find_user_by_email($email);
    }
    if (!$u || $u['status'] !== 'active') jerr('This account is suspended.', 403);
    jout(['ok' => true, 'token' => api_issue_token((int) $u['id']),
          'user' => api_self($u), 'incomplete' => profile_incomplete($u)]);
}

case 'auth/forgot': {
    if ($method !== 'POST') jerr('POST only', 405);
    $u = find_user_by_email(trim((string) pin('email')));
    if ($u) {
        $link = absolute_url('reset.php?token=' . create_reset_token((int) $u['id']));
        smtp_send($u['email'], 'Your Nipange password reset link',
            "Hi {$u['name']},\n\nSomeone (hopefully you) asked to reset the password on your Nipange account.\n\nOpen this link to choose a new one:\n$link\n\n— Nipange\nhttps://nipange.co.ke");
    }
    jout(['ok' => true]); // never reveal whether the email exists
}

case 'feed': {
    $f = [
        'view' => (string) pin('view', 'discover'), 'interest' => (string) pin('interest'),
        'county' => (string) pin('county'), 'area' => (string) pin('area'),
        'q' => (string) pin('q'), 'when' => (string) pin('when'), 'cost' => (string) pin('cost'),
        'sort' => (string) pin('sort', 'soon'), 'page' => (int) pin('page', 1), 'per' => (int) pin('per', 20),
    ];
    jout(['items' => array_map('ev_out', gatherings($f)), 'page' => $f['page']]);
}

case 'event': {
    $ev = find_gathering((int) pin('id'));
    if (!$ev) jerr('Not found.', 404);
    $uid = user_id();
    $host = $ev['creator_id'] ? find_user((int) $ev['creator_id']) : null;
    $invite = null;
    if ($uid) {
        $q = db()->prepare('SELECT status FROM invites WHERE gathering_id=:g AND user_id=:u');
        $q->execute([':g' => $ev['id'], ':u' => $uid]);
        $invite = $q->fetchColumn() ?: null;
    }
    $siblings = [];
    if (!empty($ev['series_id'])) {
        $s = db()->prepare('SELECT id, event_at FROM gatherings WHERE series_id=:s AND id!=:i AND status="active" AND event_at>=NOW() ORDER BY event_at LIMIT 8');
        $s->execute([':s' => $ev['series_id'], ':i' => $ev['id']]);
        $siblings = array_map(fn($o) => ['id' => (int) $o['id'], 'at' => $o['event_at'], 'label' => date('D j M', strtotime($o['event_at']))], $s->fetchAll(PDO::FETCH_ASSOC));
    }
    jout([
        'event'     => ev_out($ev),
        'host'      => pub_user($host),
        'attendees' => array_map(fn($a) => ['id' => (int) ($a['user_id'] ?? 0), 'name' => $a['name'] ?? 'Guest', 'photo' => $a['photo'] ?? ''], attendees_of((int) $ev['id'])),
        'waitlist'  => $uid ? (waitlist_status((int) $ev['id'], $uid) ?: null) : null,
        'myInvite'  => $invite,
        'canChat'   => $uid ? can_event_chat($ev, $uid) : false,
        'chatMuted' => $uid ? event_chat_muted((int) $ev['id'], $uid) : false,
        'pinned'    => ($p = event_pinned((int) $ev['id'])) ? msg_out($p, $uid) + ['host' => (int) $p['sender_id'] === (int) ($ev['creator_id'] ?? 0)] : null,
        'siblings'  => $siblings,
        'friends'   => $uid ? array_map('pub_user', my_friends($uid)) : [],
        'invited'   => $uid ? invited_map((int) $ev['id']) : [],
    ]);
}

case 'rsvp': {
    $u = api_need(); $uid = (int) $u['id'];
    if ($method !== 'POST') jerr('POST only', 405);
    $id = (int) pin('id'); $action = (string) pin('action', 'join');
    $out = ['ok' => true];
    switch ($action) {
        case 'accept_invite':  $out['result'] = respond_invite($id, $uid, 'accept'); break;
        case 'decline_invite': $out['result'] = respond_invite($id, $uid, 'decline'); break;
        case 'cancel':         $out['result'] = rsvp($id, 'cancel'); break;
        case 'waitlist':       $out['result'] = waitlist_join($id, $uid); waitlist_promote($id); break;
        case 'unwaitlist':     waitlist_leave($id, $uid); $out['result'] = 'left'; break;
        case 'claim':          $out['result'] = waitlist_claim($id, $uid); break;
        case 'decline_offer':  waitlist_decline($id, $uid); $out['result'] = 'passed'; break;
        default:
            $out['result'] = rsvp($id, 'join');
            if ($out['result'] === 'joined') {
                $g = find_gathering($id);
                if ($g && $g['creator_id'] && (int) $g['creator_id'] !== $uid) {
                    notify((int) $g['creator_id'], 'rsvp', $u['name'] . ' joined "' . $g['title'] . '".', 'event.php?id=' . $id);
                }
            }
    }
    $out['event'] = ev_out(find_gathering($id) ?: []);
    jout($out);
}

case 'save': {
    $u = api_need();
    if ($method !== 'POST') jerr('POST only', 405);
    jout(['ok' => true, 'saved' => toggle_save((int) $u['id'], (int) pin('id'))]);
}

case 'event-save': {
    $u = api_need(); $uid = (int) $u['id'];
    if ($method !== 'POST') jerr('POST only', 405);
    $id = (int) pin('id');
    $ev = $id ? find_gathering($id) : null;
    if ($id && (!$ev || !(int) $ev['mine'])) jerr('Not your event.', 403);

    $title    = trim(mb_substr((string) pin('title'), 0, 80));
    $category = (string) pin('category', $ev['category'] ?? '');
    [$area, $estate] = resolve_location((string) pin('county', $ev['county'] ?? ''), $ev['area'] ?? '', $ev['estate'] ?? '');
    $county   = (string) pin('county', $ev['county'] ?? '');
    $venue    = trim(mb_substr((string) pin('venue', $ev['venue'] ?? ''), 0, 80));
    $date     = trim((string) pin('date', $ev['event_at'] ?? ''));
    $cost     = max(0, min(1000000, (int) pin('cost', $ev['cost'] ?? 0)));
    $capIn    = pin('capacity', $ev['capacity'] ?? '');
    $capacity = $capIn !== '' && $capIn !== null ? max(1, min(100000, (int) $capIn)) : null;
    $audience = in_array(pin('audience'), ['public', 'friends'], true) ? pin('audience') : ($ev['audience'] ?? 'public');
    $invite   = !empty($API_IN['invite_only']) ? 1 : 0;
    $desc     = trim(mb_substr((string) pin('description', $ev['description'] ?? ''), 0, 300));
    $repeat   = in_array(pin('repeat'), ['weekly', 'monthly'], true) ? pin('repeat') : '';
    $scope    = pin('scope') === 'series' && $ev && $ev['series_id'] ? 'series' : 'one';
    $venue_id = venue_by_name($venue);
    $cover    = api_store_image((string) pin('cover_b64'));
    if (str_starts_with($cover, '!')) jerr(substr($cover, 1));

    if ($title === '' || $venue === '' || $desc === '') jerr('Fill in the name, venue and details.');
    if (!isset(categories()[$category])) jerr('Pick a category.');
    if (!area_valid($county, $area)) jerr('Pick your county and area.');
    if (!strtotime($date)) jerr('Invalid date.');
    if (!$ev && strtotime($date) < time()) jerr('Pick a future date and time.');

    if ($ev) {
        $params = [':t' => $title, ':c' => $category, ':a' => $area, ':cy' => $county, ':es' => $estate,
                   ':v' => $venue, ':d' => date('Y-m-d H:i:s', strtotime($date)), ':k' => $cost,
                   ':au' => $audience, ':ds' => $desc, ':cap' => $capacity, ':io' => $invite, ':vid' => $venue_id];
        if ($scope === 'series') {
            $shared = $params; unset($shared[':d']);
            db()->prepare('UPDATE gatherings SET title=:t,category=:c,area=:a,county=:cy,estate=:es,venue=:v,
                           cost=:k,audience=:au,description=:ds,capacity=:cap,invite_only=:io,venue_id=:vid
                           WHERE series_id=:sid AND status="active" AND event_at >= NOW()')
                ->execute($shared + [':sid' => $ev['series_id']]);
            db()->prepare('UPDATE gatherings SET event_at=:d' . ($cover ? ',cover=:cv' : '') . ' WHERE id=:id')
                ->execute([':d' => $params[':d'], ':id' => $id] + ($cover ? [':cv' => $cover] : []));
        } else {
            db()->prepare('UPDATE gatherings SET title=:t,category=:c,area=:a,county=:cy,estate=:es,venue=:v,
                           event_at=:d,cost=:k,audience=:au,description=:ds,capacity=:cap,invite_only=:io,venue_id=:vid'
                           . ($cover ? ',cover=:cv' : '') . ' WHERE id=:id')
                ->execute($params + [':id' => $id]);
        }
        jout(['ok' => true, 'id' => $id]);
    }

    $sid = null;
    $dates = [date('Y-m-d H:i:s', strtotime($date))];
    if ($repeat) {
        db()->prepare('INSERT INTO gathering_series (freq, creator_id) VALUES (:f, :u)')
            ->execute([':f' => $repeat, ':u' => $uid]);
        $sid = (int) db()->lastInsertId();
        $n = $repeat === 'weekly' ? 7 : 3;
        $iv = $repeat === 'weekly' ? 'week' : 'month';
        for ($i = 1; $i <= $n; $i++) $dates[] = date('Y-m-d H:i:s', strtotime('+' . $i . ' ' . $iv, strtotime($date)));
    }
    $ins = db()->prepare('INSERT INTO gatherings
        (title, category, area, county, estate, venue, event_at, cost, audience, description,
         sample, creator, creator_id, capacity, invite_only, cover, series_id, venue_id)
        VALUES (:t, :c, :a, :cy, :es, :v, :d, :k, :au, :ds, 0, :sk, :uid, :cap, :io, :cv, :sid, :vid)');
    $firstId = null;
    foreach ($dates as $d) {
        $ins->execute([':t' => $title, ':c' => $category, ':a' => $area, ':cy' => $county, ':es' => $estate,
                       ':v' => $venue, ':d' => $d, ':k' => $cost, ':au' => $audience, ':ds' => $desc,
                       ':sk' => SESSION_KEY, ':uid' => $uid, ':cap' => $capacity, ':io' => $invite,
                       ':cv' => $cover ?: null, ':sid' => $sid, ':vid' => $venue_id]);
        $firstId ??= (int) db()->lastInsertId();
    }
    foreach (follower_ids($uid) as $fid) {
        notify($fid, 'host_event', $u['name'] . ' posted "' . $title . '" — '
            . date('D j M · g:i A', strtotime($date)) . ' at ' . $venue . ', ' . $area . '.', 'event.php?id=' . $firstId);
    }
    jout(['ok' => true, 'id' => $firstId]);
}

case 'event-cancel': {
    $u = api_need();
    if ($method !== 'POST') jerr('POST only', 405);
    $id = (int) pin('id');
    $ev = find_gathering($id);
    if (!$ev || !(int) $ev['mine']) jerr('Not your event.', 403);
    $scope = pin('scope') === 'series' && $ev['series_id'] ? 'series' : 'one';
    if ($scope === 'series') {
        db()->prepare('UPDATE gatherings SET status="cancelled" WHERE series_id=:s AND status="active" AND event_at >= NOW()')
            ->execute([':s' => $ev['series_id']]);
        $occs = db()->prepare('SELECT id FROM gatherings WHERE series_id=:s');
        $occs->execute([':s' => $ev['series_id']]);
        foreach ($occs->fetchAll(PDO::FETCH_COLUMN) as $oid) {
            foreach (attendees_of((int) $oid) as $a) {
                if ($a['user_id']) notify((int) $a['user_id'], 'event_cancelled', '"' . $ev['title'] . '" was cancelled by the host.', 'index.php');
            }
        }
    } else {
        db()->prepare('UPDATE gatherings SET status="cancelled" WHERE id=:i')->execute([':i' => $id]);
        foreach (attendees_of($id) as $a) {
            if ($a['user_id']) notify((int) $a['user_id'], 'event_cancelled', '"' . $ev['title'] . '" was cancelled by the host.', 'index.php');
        }
    }
    jout(['ok' => true]);
}

case 'invite': {
    $u = api_need();
    if ($method !== 'POST') jerr('POST only', 405);
    $res = invite_user((int) pin('id'), (int) pin('uid'), (int) $u['id']);
    jout(['ok' => true, 'result' => $res]);
}

case 'my-invites': {
    $u = api_need();
    jout(['items' => array_map(fn($i) => [
        'inviteId' => (int) $i['invite_id'], 'status' => $i['status'],
        'inviter' => $i['inviter_name'], 'event' => ev_out($i),
    ], my_invites((int) $u['id']))]);
}

case 'event-chat': {
    $u = api_need(); $uid = (int) $u['id'];
    $gid = (int) pin('id');
    $ev = find_gathering($gid);
    if (!$ev || !can_event_chat($ev, $uid)) jerr('Chat is for the host and confirmed attendees.', 403);
    if ($method === 'POST') {
        $do = (string) pin('do');
        if ($do === 'pin' || $do === 'unpin') {
            if (!(int) $ev['mine']) jerr('Only the host can pin.', 403);
            $mid = (int) pin('mid');
            if ($do === 'pin') db()->prepare('UPDATE event_messages SET pinned=0 WHERE gathering_id=:g')->execute([':g' => $gid]);
            db()->prepare('UPDATE event_messages SET pinned=:p WHERE id=:i')->execute([':p' => $do === 'pin' ? 1 : 0, ':i' => $mid]);
            jout(['ok' => true]);
        }
        if ($do === 'mute') {
            event_chat_set_muted($gid, $uid, !empty($API_IN['on']));
            jout(['ok' => true]);
        }
        $body = trim((string) pin('body'));
        if ($body === '') jerr('Say something first.');
        $mid = event_chat_send($gid, $uid, $body);
        $m = event_message_get($mid);
        foreach (event_chat_audience($gid, $uid) as $rid) {
            notify($rid, 'event_chat', $u['name'] . ' in "' . $ev['title'] . '": ' . mb_substr($body, 0, 60), 'event.php?id=' . $gid);
        }
        jout(['ok' => true, 'message' => msg_out($m, $uid) + ['host' => (int) $ev['creator_id'] === $uid]]);
    }
    $rows = event_messages($gid, (int) pin('after', 0));
    event_chat_mark_read($gid, $uid);
    $host = fn($m) => msg_out($m, $uid) + ['host' => (int) $m['sender_id'] === (int) ($ev['creator_id'] ?? 0)];
    jout(['items' => array_map($host, $rows),
          'pinned' => ($p = event_pinned($gid)) ? $host($p) : null]);
}

case 'people': {
    $u = api_need(); // people list requires login on site too (require_login in people.php)
    $f = ['county' => (string) pin('county'), 'area' => (string) pin('area'),
          'q' => (string) pin('q'), 'interest' => (string) pin('interest'),
          'my_interests' => array_filter(explode(',', $u['interests'] ?? '')),
          'my_county' => (string) ($u['county'] ?? ''), 'my_area' => (string) ($u['area'] ?? ''),
          'my_estate' => (string) ($u['estate'] ?? '')];
    jout(['items' => array_map(fn($p) => pub_user($p) + [
        'isFriend' => (bool) ($p['is_friend'] ?? 0),
        'rel' => $p['rel_status'] ?? null,
        'relRequester' => (int) ($p['rel_requester'] ?? 0),
        'shared' => $p['shared'] ?? shared_interests($f['my_interests'], $p['interests'] ?? ''),
    ], people($f, (int) $u['id']))]);
}

case 'user': {
    $u = api_need();
    $t = find_user((int) pin('id'));
    if (!$t || $t['status'] !== 'active') jerr('Not found.', 404);
    $uid = (int) $u['id'];
    $rel = friendship($uid, (int) $t['id']);
    jout([
        'user'      => pub_user($t),
        'rel'       => $rel ? ['status' => $rel['status'], 'requester' => (int) $rel['requester_id']] : null,
        'blocked'   => is_blocked($uid, (int) $t['id']),
        'following' => is_following($uid, (int) $t['id']),
        'followers' => follower_count((int) $t['id']),
        'shared'    => shared_interests(explode(',', $u['interests'] ?? ''), $t['interests'] ?? ''),
        'hosting'   => array_map('ev_out', host_gatherings((int) $t['id'])),
    ]);
}

case 'friend': {
    $u = api_need(); $uid = (int) $u['id'];
    if ($method !== 'POST') jerr('POST only', 405);
    $other = (int) pin('uid');
    if (!$other || $other === $uid || !find_user($other)) jerr('No such user.');
    $do = (string) pin('do');
    if ($do === 'block')   { block_user($uid, $other);   jout(['ok' => true, 'result' => 'blocked']); }
    if ($do === 'unblock') { unblock_user($uid, $other); jout(['ok' => true, 'result' => 'unblocked']); }
    if ($do === 'follow' || $do === 'unfollow') {
        follow_set($uid, $other, $do === 'follow');
        jout(['ok' => true, 'result' => $do]);
    }
    jout(['ok' => true, 'result' => friend_action($do, $uid, $other)]);
}

case 'friends': {
    $u = api_need(); $uid = (int) $u['id'];
    $shape = fn($p) => ['id' => (int) ($p['id'] ?? $p['requester_id'] ?? $p['addressee_id']),
                        'name' => $p['name'], 'photo' => $p['photo'] ?? '', 'place' => place_label($p),
                        'reqId' => (int) ($p['req_id'] ?? 0)];
    jout([
        'friends' => array_map($shape, my_friends($uid)),
        'pending' => array_map($shape, pending_requests($uid)),
        'outgoing' => array_map($shape, outgoing_requests($uid)),
        'blocked' => array_map($shape, blocked_list($uid)),
    ]);
}

case 'conversation': {
    $u = api_need(); $uid = (int) $u['id'];
    if ($method !== 'POST') jerr('POST only', 405);
    $other = (int) pin('uid');
    $t = $other ? find_user($other) : null;
    if (!$t) jerr('No such user.');
    $rel = friendship($uid, $other);
    if (is_blocked($uid, $other)) jerr('Blocked.', 403);
    if ((!$rel || $rel['status'] !== 'accepted') && !is_business_owner($other)) jerr('Messages are for friends.', 403);
    $c = get_conversation($uid, $other);
    jout(['ok' => true, 'conv' => (int) $c['id']]);
}

case 'conversations': {
    $u = api_need(); $uid = (int) $u['id'];
    jout(['items' => array_map(fn($c) => [
        'id' => (int) $c['id'], 'otherId' => (int) $c['other_id'],
        'name' => $c['other_name'], 'photo' => $c['other_photo'] ?? '',
        'last' => $c['last_msg'] ?? '', 'unread' => (int) $c['unread'],
        'muted' => (bool) $c['muted'], 'at' => $c['last_message_at'],
        'ago' => time_ago($c['last_message_at']),
    ], my_conversations($uid))]);
}

case 'messages': {
    $u = api_need(); $uid = (int) $u['id'];
    $conv = conversation_for((int) pin('conv'), $uid);
    if (!$conv) jerr('No conversation.', 403);
    $otherId = (int) ($conv['user_one'] == $uid ? $conv['user_two'] : $conv['user_one']);
    if (is_blocked($uid, $otherId)) jerr('Blocked.', 403);

    if ($method === 'POST') {
        $do = (string) pin('do');
        if ($do === 'typing') { set_typing((int) $conv['id'], $uid); jout(['ok' => true]); }
        if ($do === 'mute')   { set_conversation_muted((int) $conv['id'], $uid, !empty($API_IN['on'])); jout(['ok' => true]); }
        $rel = friendship($uid, $otherId);
        if ((!$rel || $rel['status'] !== 'accepted') && !is_business_owner($otherId) && !is_business_owner($uid)) jerr('Messages are for friends.', 403);
        $body = trim((string) pin('body'));
        $att = api_store_attachment((string) pin('att_b64'), (string) pin('att_name'));
        if (is_string($att) && str_starts_with($att, '!')) jerr(substr($att, 1), 422);
        if ($body === '' && !$att) jerr('Say something or attach a file.');
        $id = send_message((int) $conv['id'], $uid, $body, $att ?: null);
        set_typing((int) $conv['id'], $uid, false);
        $pending = db()->prepare('SELECT COUNT(*) FROM messages WHERE conversation_id=:c AND sender_id=:s AND read_at IS NULL');
        $pending->execute([':c' => $conv['id'], ':s' => $uid]);
        if ((int) $pending->fetchColumn() === 1 && !conversation_muted((int) $conv['id'], $otherId)) {
            notify($otherId, 'message', $u['name'] . ' sent you a message.', 'messages.php?c=' . $conv['id']);
        }
        jout(['ok' => true, 'message' => [
            'id' => $id, 'conv' => (int) $conv['id'], 'mine' => true, 'body' => mb_substr($body, 0, 1000),
            'time' => date('g:i A'), 'at' => date('Y-m-d H:i:s'),
            'att' => $att[0] ?? null, 'attName' => $att[1] ?? null, 'attKind' => $att[2] ?? null,
        ]]);
    }

    $rows = messages_since((int) $conv['id'], (int) pin('after', 0));
    mark_conversation_read((int) $conv['id'], $uid);
    $other = find_user($otherId);
    jout([
        'items'  => array_map(fn($m) => msg_out($m, $uid), $rows),
        'typing' => peer_typing((int) $conv['id'], $otherId),
        'other'  => pub_user($other),
        'muted'  => conversation_muted((int) $conv['id'], $uid),
    ]);
}

case 'event-chats': {
    $u = api_need();
    jout(['items' => array_map(fn($g) => [
        'id' => (int) $g['id'], 'title' => $g['title'], 'cover' => $g['cover'] ?? '',
        'at' => $g['event_at'], 'atLabel' => date('D j M', strtotime($g['event_at'])),
        'host' => (int) $g['creator_id'],
        'last' => $g['last_msg'] ?? '', 'unread' => (int) $g['unread'],
        'muted' => (bool) $g['muted'],
    ], my_event_chats((int) $u['id']))]);
}

case 'groups': {
    $u = api_need();
    $f = ['view' => (string) pin('view'), 'category' => (string) pin('category'),
          'county' => (string) pin('county'), 'area' => (string) pin('area'), 'q' => (string) pin('q')];
    jout(['items' => array_map(fn($g) => [
        'id' => (int) $g['id'], 'name' => $g['name'], 'category' => $g['category'],
        'place' => place_label($g), 'desc' => $g['description'],
        'members' => (int) $g['members'], 'creator' => $g['creator_name'],
        'myStatus' => $g['my_status'], 'myRole' => $g['my_role'],
        'joinPolicy' => $g['join_policy'],
    ], groups_list($f))]);
}

case 'group': {
    $u = api_need(); $uid = (int) $u['id'];
    $gid = (int) pin('id');
    $g = group_get($gid);
    if (!$g) jerr('Not found.', 404);

    if ($method === 'POST') {
        $do = (string) pin('do');
        $mem = group_membership($gid, $uid);
        $isAdmin = $mem && $mem['role'] === 'admin' && $mem['status'] === 'active';
        $isCreator = (int) $g['creator_id'] === $uid;
        switch ($do) {
            case 'join':
                $res = group_join($gid, $uid, $g['join_policy']);
                if ($res === 'requested' || $res === 'rerequested') {
                    foreach (group_admins($gid) as $aid) {
                        if ($aid !== $uid) notify($aid, 'group_request', $u['name'] . ' asked to join "' . $g['name'] . '".', 'group.php?id=' . $gid);
                    }
                }
                jout(['ok' => true, 'result' => $res ?: 'member']);
            case 'cancel_request':
                db()->prepare("DELETE FROM grp_members WHERE group_id=:g AND user_id=:u AND status='pending'")
                    ->execute([':g' => $gid, ':u' => $uid]);
                jout(['ok' => true]);
            case 'leave':
                if (!group_leave($gid, $uid)) jerr('You are the only admin — delete the group or make someone else an admin first.');
                jout(['ok' => true]);
            case 'approve': case 'decline':
                if (!$isAdmin && !$isCreator) jerr('Admins only.', 403);
                $target = (int) pin('uid');
                if (group_review($gid, $target, $do === 'approve')) {
                    if ($do === 'approve') notify($target, 'group_approved', 'You are in! "' . $g['name'] . '" accepted your request.', 'group.php?id=' . $gid);
                    jout(['ok' => true]);
                }
                jerr('Nothing to review.');
            case 'remove':
                if (!$isAdmin && !$isCreator) jerr('Admins only.', 403);
                $target = (int) pin('uid');
                if ($target === $uid || !group_remove_member($gid, $target)) jerr('Could not remove.');
                jout(['ok' => true]);
            case 'delete':
                if (!$isCreator) jerr('Only the creator can delete.', 403);
                group_delete($gid);
                jout(['ok' => true]);
        }
        jerr('Unknown action.');
    }

    $mem = group_membership($gid, $uid);
    jout([
        'group' => [
            'id' => (int) $g['id'], 'name' => $g['name'], 'category' => $g['category'],
            'place' => place_label($g), 'desc' => $g['description'],
            'county' => $g['county'] ?? '', 'area' => $g['area'] ?? '', 'estate' => $g['estate'] ?? '',
            'joinPolicy' => $g['join_policy'], 'creatorId' => (int) $g['creator_id'],
            'creator' => $g['creator_name'] ?? '',
        ],
        'members' => array_map(fn($m) => ['id' => (int) $m['user_id'], 'name' => $m['name'],
            'photo' => $m['photo'] ?? '', 'place' => place_label($m), 'role' => $m['role']], group_members($gid)),
        'pending' => array_map(fn($m) => ['id' => (int) $m['user_id'], 'name' => $m['name'],
            'photo' => $m['photo'] ?? '', 'place' => place_label($m)], group_members($gid, 'pending')),
        'my' => $mem ? ['status' => $mem['status'], 'role' => $mem['role']] : null,
    ]);
}

case 'group-save': {
    $u = api_need(); $uid = (int) $u['id'];
    if ($method !== 'POST') jerr('POST only', 405);
    $gid = (int) pin('id');
    $g = $gid ? group_get($gid) : null;
    if ($gid && (!$g || (int) $g['creator_id'] !== $uid)) {
        $mem = $gid ? group_membership($gid, $uid) : null;
        if (!$mem || $mem['role'] !== 'admin') jerr('Admins only.', 403);
    }
    $name  = trim(mb_substr((string) pin('name', $g['name'] ?? ''), 0, 80));
    $cat   = (string) pin('category', $g['category'] ?? '');
    $county = (string) pin('county', $g['county'] ?? '');
    [$area, $estate] = resolve_location($county, $g['area'] ?? '', $g['estate'] ?? '');
    $desc  = trim(mb_substr((string) pin('description', $g['description'] ?? ''), 0, 600));
    $policy = in_array(pin('join_policy'), ['open', 'approval'], true) ? pin('join_policy') : ($g['join_policy'] ?? 'open');
    if ($name === '') jerr('Give the group a name.');
    if (!isset(categories()[$cat])) jerr('Pick a category.');
    if ($area === '') jerr('Pick an area.');
    $d = ['name' => $name, 'description' => $desc, 'category' => $cat,
          'area' => $area, 'county' => $county, 'estate' => $estate, 'join_policy' => $policy];
    if ($g) { group_update($gid, $d); jout(['ok' => true, 'id' => $gid]); }
    jout(['ok' => true, 'id' => group_create($uid, $d)]);
}

case 'venues': {
    $u = api_need();
    $f = ['county' => (string) pin('county'), 'area' => (string) pin('area'), 'q' => (string) pin('q'),
          'me_county' => $u['county'] ?? '', 'me_area' => $u['area'] ?? '', 'me_estate' => $u['estate'] ?? ''];
    jout(['items' => array_map(fn($v) => [
        'id' => (int) $v['id'], 'name' => $v['name'], 'place' => place_label($v),
        'desc' => $v['description'], 'photo' => $v['photo'] ?? '',
        'creator' => $v['creator_name'] ?? '', 'upcoming' => (int) $v['upcoming'],
        'creatorId' => (int) $v['creator_id'],
    ], venues_list($f))]);
}

case 'venue': {
    $u = api_need();
    $v = venue_get((int) pin('id'));
    if (!$v) jerr('Not found.', 404);
    jout([
        'venue' => ['id' => (int) $v['id'], 'name' => $v['name'], 'place' => place_label($v),
                    'desc' => $v['description'], 'photo' => $v['photo'] ?? '',
                    'county' => $v['county'] ?? '', 'area' => $v['area'] ?? '', 'estate' => $v['estate'] ?? '',
                    'creatorId' => (int) $v['creator_id']],
        'events' => array_map('ev_out', venue_events((int) $v['id'])),
    ]);
}

case 'venue-save': {
    $u = api_need(); $uid = (int) $u['id'];
    if ($method !== 'POST') jerr('POST only', 405);
    $id = (int) pin('id');
    $v = $id ? venue_get($id) : null;
    if ($id && (!$v || (int) $v['creator_id'] !== $uid)) jerr('Only the creator can edit.', 403);
    $name = trim(mb_substr((string) pin('name', $v['name'] ?? ''), 0, 80));
    $county = in_array(pin('county'), counties(), true) ? pin('county') : ($v['county'] ?? '');
    [$area, $estate] = resolve_location($county, $v['area'] ?? '', $v['estate'] ?? '');
    $desc = trim(mb_substr((string) pin('description', $v['description'] ?? ''), 0, 600));
    $photo = api_store_image((string) pin('photo_b64'));
    if (str_starts_with($photo, '!')) jerr(substr($photo, 1));
    if ($name === '') jerr('Give the venue a name.');
    if ($area === '') jerr('Pick an area.');
    $d = ['name' => $name, 'area' => $area, 'county' => $county, 'estate' => $estate, 'description' => $desc];
    if ($photo) $d['photo'] = $photo;
    if ($v) { venue_update($id, $d); jout(['ok' => true, 'id' => $id]); }
    jout(['ok' => true, 'id' => venue_create($uid, $d)]);
}

case 'notifications': {
    $u = api_need(); $uid = (int) $u['id'];
    if ($method === 'POST') {
        db()->prepare('UPDATE notifications SET read_at=NOW() WHERE user_id=:u AND read_at IS NULL')->execute([':u' => $uid]);
        jout(['ok' => true]);
    }
    $out = ['unread' => unread_notifications($uid), 'unreadMsg' => unread_messages($uid), 'friendReqs' => friend_request_count($uid)];
    if (empty($_GET['count'])) {
        $out['items'] = array_map(fn($n) => [
            'id' => (int) $n['id'], 'type' => $n['type'], 'body' => $n['body'],
            'link' => $n['link'], 'unread' => $n['read_at'] === null, 'ago' => time_ago($n['created_at']),
        ], notifications_for($uid, 30));
    }
    jout($out);
}

case 'profile': {
    $u = api_need(); $uid = (int) $u['id'];
    if ($method === 'GET') jout(['user' => api_self($u), 'incomplete' => profile_incomplete($u)]);
    if ($method !== 'POST') jerr('POST only', 405);
    $name = trim(mb_substr((string) pin('name', $u['name']), 0, 60));
    $bio  = trim(mb_substr((string) pin('bio', $u['bio'] ?? ''), 0, 300));
    $county = in_array(pin('county'), counties(), true) ? pin('county') : ($u['county'] ?? '');
    [$area, $estate] = resolve_location($county, $u['area'] ?? '', $u['estate'] ?? '');
    $ints = array_values(array_filter((array) ($API_IN['interests'] ?? []), fn($i) => isset(categories()[$i])));
    $disc = array_key_exists('discoverable', $API_IN) ? (!empty($API_IN['discoverable']) ? 1 : 0) : (int) ($u['discoverable'] ?? 1);
    if ($name === '') jerr('Name is required.');
    $photo = api_store_image((string) pin('photo_b64'));
    if (str_starts_with($photo, '!')) jerr(substr($photo, 1));
    $gender = in_array(pin('gender'), GENDERS, true) ? pin('gender') : ($u['gender'] ?? '');
    $agebk  = in_array(pin('age_bracket'), AGE_BRACKETS, true) ? pin('age_bracket') : ($u['age_bracket'] ?? '');
    $funFact = array_key_exists('fun_fact', $API_IN) ? trim(mb_substr((string) pin('fun_fact'), 0, 160)) : ($u['fun_fact'] ?? '');
    $sql = 'UPDATE users SET name=:n,bio=:b,fun_fact=:ff,area=:a,county=:c,estate=:e,gender=:g,age_bracket=:ab,interests=:i,discoverable=:d';
    $p = [':n' => $name, ':b' => $bio, ':ff' => $funFact ?: null, ':a' => $area, ':c' => $county, ':e' => $estate,
          ':g' => $gender, ':ab' => $agebk, ':i' => implode(',', $ints), ':d' => $disc, ':u' => $uid];
    if ($photo) { $sql .= ',photo=:ph'; $p[':ph'] = $photo; }
    db()->prepare($sql . ' WHERE id=:u')->execute($p);
    jout(['ok' => true, 'user' => api_self(find_user($uid)), 'incomplete' => profile_incomplete(find_user($uid))]);
}

case 'complete-profile': {
    $u = api_need(); $uid = (int) $u['id'];
    if ($method !== 'POST') jerr('POST only', 405);
    $gender = (string) pin('gender');
    $age    = (string) pin('age_bracket');
    if (!in_array($gender, GENDERS, true)) jerr('Pick your gender.');
    if (!in_array($age, AGE_BRACKETS, true)) jerr('Pick your age bracket.');
    [$area, $estate] = resolve_location((string) pin('county'), $u['area'] ?? '', $u['estate'] ?? '');
    $county = in_array(pin('county'), counties(), true) ? pin('county') : ($u['county'] ?? '');
    $funFact = trim(mb_substr((string) pin('fun_fact'), 0, 160));
    db()->prepare('UPDATE users SET gender=:g, age_bracket=:ab, county=:c, area=:a, estate=:e, fun_fact=:ff WHERE id=:id')
        ->execute([':g' => $gender, ':ab' => $age, ':c' => $county, ':a' => $area, ':e' => $estate,
                   ':ff' => $funFact ?: null, ':id' => $uid]);
    jout(['ok' => true, 'user' => api_self(find_user($uid))]);
}

case 'password': {
    $u = api_need(); $uid = (int) $u['id'];
    if ($method !== 'POST') jerr('POST only', 405);
    if (!password_verify((string) pin('current'), $u['pass_hash'])) jerr('Current password is wrong.');
    if (mb_strlen((string) pin('new')) < 8) jerr('New password needs 8+ characters.');
    db()->prepare('UPDATE users SET pass_hash=:p WHERE id=:u')
        ->execute([':p' => password_hash((string) pin('new'), PASSWORD_DEFAULT), ':u' => $uid]);
    jout(['ok' => true]);
}

case 'report': {
    $u = api_need();
    if ($method !== 'POST') jerr('POST only', 405);
    $type = in_array(pin('type'), ['user', 'gathering', 'message', 'event_message'], true) ? pin('type') : 'gathering';
    add_report((int) $u['id'], $type, (int) pin('id'), trim((string) pin('reason')));
    jout(['ok' => true]);
}

case 'auth/logout': {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+([a-f0-9]{64})/i', $h, $m)) {
        db()->prepare('DELETE FROM api_tokens WHERE token_hash=:h')->execute([':h' => hash('sha256', strtolower($m[1]))]);
    }
    jout(['ok' => true]);
}

default:
    jerr('Unknown route.', 404);
}

// ---------- helpers ----------

function api_self(array $u): array {
    return pub_user($u) + [
        'email' => $u['email'], 'verified' => !empty($u['email_verified']),
        'county' => $u['county'] ?? '', 'area' => $u['area'] ?? '', 'estate' => $u['estate'] ?? '',
        'discoverable' => (bool) ($u['discoverable'] ?? 1),
        'genderFull' => $u['gender'] ?? '', 'role' => $u['role'] ?? 'member',
    ];
}

function invited_map(int $gid): array {
    $q = db()->prepare('SELECT user_id, status FROM invites WHERE gathering_id=:g');
    $q->execute([':g' => $gid]);
    return array_column($q->fetchAll(PDO::FETCH_ASSOC), 'status', 'user_id');
}
