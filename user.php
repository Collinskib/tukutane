<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_login('index.php');

$id = (int) ($_GET['id'] ?? 0);
if ($id === user_id()) redirect('profile.php');
$p = find_user($id);

page_head($p ? $p['name'] : 'Not found');
site_header('people');

if (!$p || $p['status'] !== 'active' || is_blocked(user_id(), $id)):
?>
<main><div class="empty"><span class="big">🫥</span><p>This profile isn't available.</p><a class="primary btn" href="people.php">Back to People</a></div></main>
<?php else:
$rel   = friendship(user_id(), $id);
$isFriend = $rel && $rel['status'] === 'accepted';
$their = array_filter(explode(',', $p['interests']));
$shared = shared_interests(array_filter(explode(',', user()['interests'] ?? '')), $p['interests']);

// follow / unfollow this organizer
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $do = $_POST['do'] ?? '';
    if (in_array($do, ['follow', 'unfollow'], true)) {
        follow_set(user_id(), $id, $do === 'follow');
        flash($do === 'follow' ? 'Following — you\'ll get their new event alerts.' : 'Unfollowed.');
    }
    redirect('user.php?id=' . $id);
}

$following  = is_following(user_id(), $id);
$followers  = follower_count($id);
$hosted     = host_gatherings($id, true, 12);
$pastHosted = host_gatherings($id, false, 6);
?>
<main class="ev-wrap">
  <section class="hl-hero rv in">
    <div class="hl-avatar"><?= avatar_html($p, 'lg', true) ?></div>
    <h1><?= e($p['name']) ?></h1>
    <p class="hl-sub"><?= e(place_label($p, 'Nipange user')) ?><?php
      $demo = array_filter([$p['age_bracket'] ?? '', in_array($p['gender'] ?? '', ['Male', 'Female'], true) ? $p['gender'] : '']);
      if ($demo) echo ' · ' . e(implode(', ', $demo));
    ?><?= $isFriend ? ' · <span class="status-pill going">Friends</span>' : '' ?><?= $followers ? ' · ' . $followers . ' follower' . ($followers === 1 ? '' : 's') : '' ?></p>
    <?php if ($p['bio']): ?><p class="hl-bio"><?= e($p['bio']) ?></p><?php endif; ?>
    <?php if (!empty($p['fun_fact'])): ?>
    <div class="hl-quote"><span>❝</span><p><?= e($p['fun_fact']) ?></p></div>
    <?php endif; ?>
    <?php if ($their): ?>
    <ul class="hl-ints">
      <?php foreach ($their as $t): [$em,] = categories()[$t] ?? ['✨']; $sh = in_array($t, $shared, true); ?>
      <li<?= $sh ? ' class="shared"' : '' ?>><span class="hl-ic"><?= $em ?></span><b><?= $sh ? '✓ ' : '' ?><?= e($t) ?></b></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <div class="hl-acts">
      <?php if ($isFriend): ?>
        <a class="a-btn" href="messages.php?to=<?= $id ?>">💬 Message</a>
        <form method="post" action="friend.php" style="display:inline"><?= csrf_field() ?>
          <input type="hidden" name="uid" value="<?= $id ?>"><input type="hidden" name="do" value="remove">
          <button class="a-btn ghost" type="submit">Remove friend</button></form>
      <?php elseif ($rel && $rel['status'] === 'pending'): ?>
        <?php if ((int) $rel['requester_id'] === user_id()): ?>
        <form method="post" action="friend.php" style="display:inline"><?= csrf_field() ?>
          <input type="hidden" name="uid" value="<?= $id ?>"><input type="hidden" name="do" value="cancel">
          <button class="a-btn ghost" type="submit">Requested ✓ (cancel)</button></form>
        <?php else: ?>
        <form method="post" action="friend.php" style="display:inline"><?= csrf_field() ?>
          <input type="hidden" name="uid" value="<?= $id ?>"><input type="hidden" name="do" value="accept">
          <button class="a-btn" type="submit">Accept request</button></form>
        <form method="post" action="friend.php" style="display:inline"><?= csrf_field() ?>
          <input type="hidden" name="uid" value="<?= $id ?>"><input type="hidden" name="do" value="decline">
          <button class="a-btn ghost" type="submit">Decline</button></form>
        <?php endif; ?>
      <?php else: ?>
        <form method="post" action="friend.php" style="display:inline"><?= csrf_field() ?>
          <input type="hidden" name="uid" value="<?= $id ?>"><input type="hidden" name="do" value="send">
          <button class="a-btn" type="submit">+ Add friend</button></form>
      <?php endif; ?>
      <form method="post" style="display:inline"><?= csrf_field() ?>
        <input type="hidden" name="do" value="<?= $following ? 'unfollow' : 'follow' ?>">
        <button class="a-btn ghost" type="submit"><?= $following ? '✓ Following' : '🔔 Follow' ?></button></form>
      <button class="a-btn ghost" type="button" data-dialog="#moredlg">More…</button>
    </div>
    <a class="hl-goto" href="#hosted" aria-label="Scroll to gatherings">▾</a>
  </section>

  <?php if ($hosted): ?>
  <h3 id="hosted" style="font-family:var(--serif);font-weight:400;scroll-margin-top:90px">Upcoming gatherings by <?= e($p['name']) ?></h3>
  <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(220px,1fr))">
    <?php foreach ($hosted as $r): [, $rc] = categories()[$r['category']] ?? ['', '#e4e6db']; ?>
    <article class="card">
      <div class="ribbon" style="background:<?= e($rc) ?>"><span><?= e($r['category']) ?></span><span><?= e((categories()[$r['category']] ?? ['📅'])[0]) ?></span></div>
      <div class="body">
        <h3><a href="event.php?id=<?= (int) $r['id'] ?>" style="color:inherit;text-decoration:none"><?= e($r['title']) ?></a></h3>
        <p><?= e(when($r['event_at'])) ?> · <?= e(place_label($r)) ?></p>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($pastHosted): ?>
  <h3 style="font-family:var(--serif);font-weight:400;margin-top:26px">Past gatherings</h3>
  <div class="card" style="padding:8px 0">
    <?php foreach ($pastHosted as $r): ?>
    <div class="member-row">
      <span class="who" style="flex:1"><b><a href="event.php?id=<?= (int) $r['id'] ?>" style="color:inherit;text-decoration:none"><?= e($r['title']) ?></a></b>
        <small><?= e(date('D j M Y', strtotime($r['event_at']))) ?> · <?= e(place_label($r)) ?> · <?= (int) $r['real_rsvps'] + (int) $r['going_base'] ?> went</small></span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>

<dialog id="moredlg">
  <h3 style="margin-top:0">More options</h3>
  <form method="post" action="friend.php" onsubmit="return confirm('Block <?= e($p['name']) ?>? They won\'t see or message you.')">
    <?= csrf_field() ?>
    <input type="hidden" name="uid" value="<?= $id ?>"><input type="hidden" name="do" value="block">
    <button class="btn-danger" type="submit" style="width:100%;margin-bottom:10px">Block user</button>
  </form>
  <form method="post" action="report.php">
    <?= csrf_field() ?>
    <input type="hidden" name="type" value="user"><input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="back" value="user.php?id=<?= $id ?>">
    <div class="field"><label>Report this profile</label>
      <textarea name="reason" required maxlength="300" rows="3"></textarea></div>
    <button class="btn-danger" type="submit">Send report</button>
  </form>
  <div class="actions" style="margin-top:10px"><button class="btn-ghost" type="button" data-close>Close</button></div>
</dialog>
<?php endif; ?>
<?php bottom_nav('people'); site_footer(); ?>
