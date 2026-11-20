<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_login('friends.php');

$uid      = user_id();
$incoming = pending_requests($uid);
$outgoing = outgoing_requests($uid);
$friends  = my_friends($uid);
$blocked  = blocked_list($uid);

page_head('Friends');
site_header();
?>
<main class="ev-wrap">
  <h2>Friends</h2>

  <?php if ($incoming): ?>
  <section class="panel rv in" style="margin-bottom:18px">
    <h3 style="font-family:var(--serif);font-weight:400;padding:14px 16px 4px">Requests (<?= count($incoming) ?>)</h3>
    <?php foreach ($incoming as $r): ?>
    <div style="display:flex;gap:12px;align-items:center;padding:11px 16px;border-top:1px solid var(--line-soft)">
      <a href="user.php?id=<?= (int) $r['requester_id'] ?>"><?= avatar_html($r) ?></a>
      <div style="flex:1;min-width:0"><b><?= e($r['name']) ?></b><br>
        <small style="color:var(--ink-soft)"><?= e(place_label($r, 'Kenya')) ?></small></div>
      <form method="post" action="friend.php"><?= csrf_field() ?>
        <input type="hidden" name="uid" value="<?= (int) $r['requester_id'] ?>">
        <input type="hidden" name="do" value="accept"><button class="btn-mini solid" type="submit">Accept</button></form>
      <form method="post" action="friend.php"><?= csrf_field() ?>
        <input type="hidden" name="uid" value="<?= (int) $r['requester_id'] ?>">
        <input type="hidden" name="do" value="decline"><button class="btn-mini" type="submit">Decline</button></form>
    </div>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

  <?php if ($outgoing): ?>
  <section class="panel rv in" style="margin-bottom:18px">
    <h3 style="font-family:var(--serif);font-weight:400;padding:14px 16px 4px">Sent (<?= count($outgoing) ?>)</h3>
    <?php foreach ($outgoing as $r): ?>
    <div style="display:flex;gap:12px;align-items:center;padding:11px 16px;border-top:1px solid var(--line-soft)">
      <?= avatar_html($r) ?>
      <div style="flex:1"><b><?= e($r['name']) ?></b><br><small style="color:var(--ink-soft)">pending</small></div>
      <form method="post" action="friend.php"><?= csrf_field() ?>
        <input type="hidden" name="uid" value="<?= (int) $r['addressee_id'] ?>">
        <input type="hidden" name="do" value="cancel"><button class="btn-mini" type="submit">Cancel</button></form>
    </div>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

  <section class="panel rv in">
    <h3 style="font-family:var(--serif);font-weight:400;padding:14px 16px 4px">Your friends (<?= count($friends) ?>)</h3>
    <?php if (!$friends): ?>
    <div class="empty"><span class="big">🧑‍🤝‍🧑</span><p>No friends yet — find people in <a href="people.php">People nearby</a>.</p></div>
    <?php endif; ?>
    <?php foreach ($friends as $fr): ?>
    <div style="display:flex;gap:12px;align-items:center;padding:11px 16px;border-top:1px solid var(--line-soft)">
      <a href="user.php?id=<?= (int) $fr['id'] ?>"><?= avatar_html($fr) ?></a>
      <div style="flex:1"><a href="user.php?id=<?= (int) $fr['id'] ?>" style="color:inherit;text-decoration:none"><b><?= e($fr['name']) ?></b></a><br>
        <small style="color:var(--ink-soft)"><?= e(place_label($fr, 'Kenya')) ?></small></div>
      <a class="btn-mini solid" href="messages.php?to=<?= (int) $fr['id'] ?>">💬 Message</a>
      <form method="post" action="friend.php"><?= csrf_field() ?>
        <input type="hidden" name="uid" value="<?= (int) $fr['id'] ?>">
        <input type="hidden" name="do" value="remove"><button class="btn-mini" type="submit">Remove</button></form>
    </div>
    <?php endforeach; ?>
  </section>

  <?php if ($blocked): ?>
  <section class="panel rv in" style="margin-top:18px">
    <h3 style="font-family:var(--serif);font-weight:400;padding:14px 16px 4px">Blocked (<?= count($blocked) ?>)</h3>
    <?php foreach ($blocked as $b): ?>
    <div style="display:flex;gap:12px;align-items:center;padding:11px 16px;border-top:1px solid var(--line-soft)">
      <?= avatar_html($b) ?>
      <div style="flex:1"><b><?= e($b['name']) ?></b></div>
      <form method="post" action="friend.php"><?= csrf_field() ?>
        <input type="hidden" name="uid" value="<?= (int) $b['id'] ?>">
        <input type="hidden" name="do" value="unblock"><button class="btn-mini" type="submit">Unblock</button></form>
    </div>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>
</main>
<?php bottom_nav('people'); site_footer(); ?>
