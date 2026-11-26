<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_login('plans.php');

$uid = user_id();
$tab = in_array($_GET['tab'] ?? '', ['joined','saved','hosted','waitlist','invites','notifications','requests'], true)
     ? $_GET['tab'] : 'joined';

$items = [];
$invites = $requests = $notifs = [];
switch ($tab) {
    case 'joined':        $items = gatherings(['view' => 'mine', 'per' => 60]); break;
    case 'hosted':        $items = gatherings(['view' => 'hosted', 'per' => 60]); break;
    case 'saved':         $items = gatherings(['view' => 'saved', 'per' => 60]); break;
    case 'waitlist':
        $q = db()->prepare('SELECT g.*, w.status AS wl_status FROM waitlists w
                            JOIN gatherings g ON g.id = w.gathering_id
                            WHERE w.user_id = :u AND w.status IN ("waiting","offered")
                            ORDER BY w.created_at DESC');
        $q->execute([':u' => $uid]);
        $items = $q->fetchAll(PDO::FETCH_ASSOC);
        break;
    case 'invites':       $invites = my_invites($uid); break;
    case 'requests':      $requests = pending_requests($uid); break;
    case 'notifications':
        $notifs = notifications_for($uid, 40);
        db()->prepare('UPDATE notifications SET read_at = NOW() WHERE user_id = :u AND read_at IS NULL')
            ->execute([':u' => $uid]);
        break;
}

function event_row(array $ev, bool $hosted = false): void {
    [$em, $col] = categories()[$ev['category']] ?? ['📅', '#e4e6db']; ?>
  <div class="plan-card">
    <div class="ribbon" style="background:<?= e($col) ?>;height:64px;width:74px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:26px;flex:none"><?= $em ?></div>
    <div class="info">
      <b><a href="event.php?id=<?= (int) $ev['id'] ?>" style="color:inherit;text-decoration:none"><?= e($ev['title']) ?></a><?= !empty($ev['series_id']) ? ' <span title="Repeats — next date shown">🔁</span>' : '' ?></b>
      <small><?= e(when($ev['event_at'])) ?> · <?= e(place_label($ev)) ?> · <?= e(price((int) $ev['cost'])) ?></small>
    </div>
    <?php if ($ev['status'] === 'cancelled'): ?><span class="status-pill warn">Cancelled</span>
    <?php elseif ($hosted): ?><span class="status-pill going"><?= going_count($ev) ?> going</span>
      <a class="btn-mini" href="edit.php?id=<?= (int) $ev['id'] ?>">Edit</a>
    <?php elseif (($ev['wl_status'] ?? '') === 'offered'): ?><span class="status-pill going">Spot open — claim it!</span>
    <?php elseif (($ev['wl_status'] ?? '') === 'waiting'): ?><span class="status-pill">Waitlisted</span>
    <?php else: ?><span class="status-pill going">Going</span>
    <?php endif; ?>
  </div>
<?php }

page_head('My plans');
site_header('plans');
?>
<main class="ev-wrap">
  <h2>My plans</h2>
  <nav class="tabs">
    <?php foreach (['joined' => 'Going', 'hosted' => 'Hosting', 'saved' => 'Saved', 'waitlist' => 'Waitlist', 'invites' => 'Invites', 'requests' => 'Friend requests', 'notifications' => 'Notifications'] as $t => $label): ?>
    <a href="?tab=<?= $t ?>" class="<?= $tab === $t ? 'active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </nav>

  <?php if (in_array($tab, ['joined', 'hosted', 'saved', 'waitlist'], true)): ?>
    <?php if (!$items): ?>
    <div class="empty"><span class="big">📅</span>
      <p><?= match($tab) {
          'joined'   => 'You haven\'t joined anything yet — explore the Discover feed!',
          'hosted'   => 'You haven\'t created an event yet.',
          'waitlist' => 'You\'re not on any waitlists.',
          default    => 'No saved gatherings — tap ☆ Save on any event.',
      } ?></p>
      <a class="primary btn" href="<?= $tab === 'hosted' ? 'create.php' : 'index.php' ?>"><?= $tab === 'hosted' ? '+ Create a plan' : 'Explore events' ?></a>
    </div>
    <?php endif; ?>
    <?php foreach ($items as $i => $ev): ?>
    <div class="rv" style="--d:<?= min($i*40,300) ?>ms"><?php event_row($ev, $tab === 'hosted'); ?></div>
    <?php endforeach; ?>

  <?php elseif ($tab === 'invites'): ?>
    <?php if (!$invites): ?><div class="empty"><span class="big">✉️</span><p>No invitations right now.</p></div><?php endif; ?>
    <?php foreach ($invites as $inv): [$em,$col] = categories()[$inv['category']] ?? ['📅','#e4e6db']; ?>
    <div class="plan-card">
      <div class="ribbon" style="background:<?= e($col) ?>;height:64px;width:74px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:26px;flex:none"><?= $em ?></div>
      <div class="info">
        <b><?= e($inv['title']) ?></b>
        <small><?= e(when($inv['event_at'])) ?> · <?= e(place_label($inv)) ?> · invited by <?= e($inv['inviter_name']) ?></small>
      </div>
      <?php if ($inv['status'] === 'pending'): ?>
      <form method="post" action="rsvp.php"><?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $inv['id'] ?>">
        <input type="hidden" name="back" value="plans.php?tab=invites">
        <input type="hidden" name="action" value="accept_invite">
        <button class="btn-mini solid" type="submit">Accept</button></form>
      <form method="post" action="rsvp.php"><?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $inv['id'] ?>">
        <input type="hidden" name="back" value="plans.php?tab=invites">
        <input type="hidden" name="action" value="decline_invite">
        <button class="btn-mini" type="submit">Decline</button></form>
      <?php else: ?><span class="status-pill <?= $inv['status'] === 'accepted' ? 'going' : '' ?>"><?= ucfirst($inv['status']) ?></span><?php endif; ?>
    </div>
    <?php endforeach; ?>

  <?php elseif ($tab === 'requests'): ?>
    <?php if (!$requests): ?><div class="empty"><span class="big">🧑‍🤝‍🧑</span><p>No pending friend requests.</p></div><?php endif; ?>
    <?php foreach ($requests as $r): ?>
    <div class="plan-card">
      <?= avatar_html($r) ?>
      <div class="info"><b><?= e($r['name']) ?></b><small><?= e(place_label($r, 'Kenya')) ?></small></div>
      <form method="post" action="friend.php"><?= csrf_field() ?>
        <input type="hidden" name="uid" value="<?= (int) $r['requester_id'] ?>">
        <input type="hidden" name="do" value="accept"><button class="btn-mini solid" type="submit">Accept</button></form>
      <form method="post" action="friend.php"><?= csrf_field() ?>
        <input type="hidden" name="uid" value="<?= (int) $r['requester_id'] ?>">
        <input type="hidden" name="do" value="decline"><button class="btn-mini" type="submit">Decline</button></form>
    </div>
    <?php endforeach; ?>

  <?php else: ?>
    <div class="panel">
    <?php if (!$notifs): ?><div class="empty"><span class="big">🔔</span><p>No notifications yet.</p></div><?php endif; ?>
    <?php foreach ($notifs as $i => $n):
        $icon = match($n['type']) {
            'friend_request' => '🧑‍🤝‍🧑', 'friend_accepted' => '🤝', 'invite' => '✉️',
            'rsvp' => '✅', 'reminder' => '⏰', 'event_cancelled' => '❌', 'message' => '💬', default => '🔔',
        }; ?>
    <a class="notif <?= $n['read_at'] === null ? 'unread' : '' ?> rv" style="--d:<?= min($i*30,300) ?>ms"
       href="<?= $n['link'] ? e($n['link']) : '#' ?>">
      <span style="font-size:20px"><?= $icon ?></span>
      <span style="flex:1"><?= e($n['body']) ?></span>
      <span class="t"><?= e(time_ago($n['created_at'])) ?></span>
    </a>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
<?php bottom_nav('plans'); site_footer(); ?>
