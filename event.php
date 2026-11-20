<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$id = (int) ($_GET['id'] ?? 0);
$ev = find_gathering($id);
if (!$ev) {
    http_response_code(404);
    page_head('Not found'); site_header();
    echo '<main><div class="empty"><span class="big">🗺️</span><p>That gathering doesn\'t exist or was removed.</p><a class="primary btn" href="index.php">Back to Discover</a></div></main>';
    bottom_nav(); site_footer(); exit;
}

// invite-only gate: only creator/invitees/admins may view
$uid = user_id();
$myInvite = null;
if ($ev['invite_only'] && !$ev['mine'] && !is_admin()) {
    $stmt = db()->prepare('SELECT status FROM invites WHERE gathering_id = :g AND user_id = :u');
    $stmt->execute([':g' => $id, ':u' => $uid]);
    $myInvite = $stmt->fetchColumn();
    if (!$myInvite || $myInvite === 'declined') {
        page_head('Private gathering'); site_header();
        echo '<main><div class="empty"><span class="big">🔒</span><p>This gathering is invite-only.</p><a class="primary btn" href="index.php">Back to Discover</a></div></main>';
        bottom_nav(); site_footer(); exit;
    }
}

[$emoji, $ribbon] = categories()[$ev['category']] ?? ['📅', '#e4e6db'];
$host   = $ev['creator_id'] ? find_user((int) $ev['creator_id']) : null;
$going  = going_count($ev);
$full   = $ev['capacity'] !== null && $going >= (int) $ev['capacity'];
$wl     = user() ? waitlist_status($id, user_id()) : null;
$attendees = attendees_of($id);
$related = array_filter(
    gatherings(['interest' => $ev['category'], 'per' => 4, 'sort' => 'soon']),
    fn($r) => (int) $r['id'] !== $id
);
$shareUrl = absolute_url('event.php?id=' . $id);

page_head($ev['title']);
site_header('discover');
?>
<main class="ev-wrap">
  <div class="ev-cover rv in" <?= $ev['cover'] ? '' : 'style="background:' . e($ribbon) . '"' ?>>
    <?php if ($ev['cover']): ?><img src="uploads/<?= e($ev['cover']) ?>" alt=""><?php endif; ?>
    <div class="grad"></div>
    <div class="cap">
      <small><?= e($ev['category']) ?> <?= $emoji ?><?= $ev['invite_only'] ? ' · 🔒 Invite only' : '' ?></small>
      <h1><?= e($ev['title']) ?></h1>
    </div>
  </div>

  <div class="ev-meta rv" style="--d:80ms">
    <div class="m"><b>When</b><?= e(when($ev['event_at'])) ?></div>
    <div class="m"><b>Where</b><?= e($ev['venue']) ?>, <?= e($ev['area']) ?></div>
    <div class="m"><b>Cost</b><?= e(price((int) $ev['cost'])) ?></div>
    <div class="m"><b>Going</b><?= $going ?><?= $ev['capacity'] ? ' / ' . (int) $ev['capacity'] : '' ?></div>
  </div>

  <p class="rv" style="--d:140ms;color:var(--ink-soft);font-size:17px;line-height:1.55"><?= nl2br(e($ev['description'])) ?></p>

  <div class="hostcard rv" style="--d:180ms">
    <?= avatar_html($host) ?>
    <div class="who">
      <b><?= e($host['name'] ?? 'Nipange member') ?></b>
      <small> · Host<?= $host && $host['area'] ? ' · ' . e($host['area']) : '' ?></small>
    </div>
    <?php if ($host && (int) $host['id'] !== $uid): ?>
    <a class="btn-mini" href="user.php?id=<?= (int) $host['id'] ?>">View profile</a>
    <?php endif; ?>
  </div>

  <?php if ($ev['status'] === 'cancelled'): ?>
    <p class="rv status-pill warn" style="--d:200ms">This gathering was cancelled by the host.</p>
  <?php endif; ?>

  <div class="share-row rv" style="--d:220ms">
    <?php if ($ev['mine']): ?>
      <a class="btn-mini solid" href="edit.php?id=<?= $id ?>">Manage gathering</a>
    <?php elseif ($ev['status'] !== 'cancelled'): ?>
      <?php if (user()): ?>
        <?php if ($ev['invite_only'] && $myInvite === 'pending'): ?>
        <form method="post" action="rsvp.php" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= $id ?>">
          <input type="hidden" name="back" value="event.php?id=<?= $id ?>">
          <input type="hidden" name="action" value="accept_invite">
          <button class="primary" type="submit">Accept invitation</button>
        </form>
        <form method="post" action="rsvp.php" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= $id ?>">
          <input type="hidden" name="back" value="event.php?id=<?= $id ?>">
          <input type="hidden" name="action" value="decline_invite">
          <button class="btn-ghost" type="submit">Decline</button>
        </form>
        <?php else: ?>
          <?php if ($wl && $wl['status'] === 'offered'): ?>
          <form method="post" action="rsvp.php" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="back" value="event.php?id=<?= $id ?>">
            <input type="hidden" name="action" value="claim">
            <button class="primary" type="submit">Claim your spot</button>
          </form>
          <form method="post" action="rsvp.php" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="back" value="event.php?id=<?= $id ?>">
            <input type="hidden" name="action" value="decline_offer">
            <button class="btn-ghost" type="submit">Pass</button>
          </form>
          <small style="display:block;width:100%;color:var(--ink-soft)">🎉 A spot opened for you — claim within 2 hours.</small>
          <?php elseif ($wl && $wl['status'] === 'waiting'): ?>
          <button class="primary" type="submit" disabled>On the waitlist</button>
          <form method="post" action="rsvp.php" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="back" value="event.php?id=<?= $id ?>">
            <input type="hidden" name="action" value="unwaitlist">
            <button class="btn-ghost" type="submit">Leave waitlist</button>
          </form>
          <?php else: ?>
          <form method="post" action="rsvp.php" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="back" value="event.php?id=<?= $id ?>">
            <input type="hidden" name="action" value="<?= $ev['joined'] ? 'cancel' : ($full ? 'waitlist' : 'join') ?>">
            <button class="primary" type="submit">
              <?= $ev['joined'] ? 'Cancel RSVP' : ($full ? 'Join waitlist' : 'Join gathering') ?></button>
          </form>
          <?php endif; ?>
        <?php endif; ?>
        <form method="post" action="save.php" data-ajax data-swap-label style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= $id ?>">
          <button class="btn-ghost" type="submit"><?= $ev['saved'] ? '★ Saved' : '☆ Save' ?></button>
        </form>
      <?php else: ?>
        <a class="primary" href="auth.php?next=<?= urlencode('event.php?id=' . $id) ?>">Join gathering</a>
      <?php endif; ?>
    <?php endif; ?>
    <a class="btn-ghost" href="<?= e(whatsapp_share($ev['title'], $shareUrl)) ?>" target="_blank" rel="noopener">Share on WhatsApp</a>
    <?php if (user() && !$ev['mine']): ?>
    <button class="btn-ghost" type="button" data-dialog="#reportdlg">Report</button>
    <?php endif; ?>
  </div>

  <?php if ($attendees): ?>
  <section class="rv" style="--d:260ms">
    <h3 style="font-family:var(--serif);font-weight:400">Who's going (<?= count($attendees) ?>)</h3>
    <div class="attendees">
      <?php foreach (array_slice($attendees, 0, 14) as $j => $a): ?>
      <span class="rv" style="--d:<?= $j * 40 ?>ms"><?= $a['user_id']
          ? '<a href="user.php?id=' . (int) $a['user_id'] . '">' . avatar_html($a) . '</a>'
          : avatar_html($a) ?></span>
      <?php endforeach; ?>
      <?php if (count($attendees) > 14): ?><span class="more">+<?= count($attendees) - 14 ?> more</span><?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($related): ?>
  <section class="related rv" style="--d:300ms">
    <h3>More <?= e($ev['category']) ?> gatherings</h3>
    <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(220px,1fr))">
      <?php foreach (array_slice($related, 0, 3) as $j => $r):
        [, $rc] = categories()[$r['category']] ?? ['', '#e4e6db']; ?>
      <article class="card rv" style="--d:<?= $j * 60 ?>ms">
        <div class="ribbon" style="background:<?= e($rc) ?>"><span><?= e($r['category']) ?></span><span><?= e((categories()[$r['category']] ?? ['📅'])[0]) ?></span></div>
        <div class="body">
          <h3><a href="event.php?id=<?= (int) $r['id'] ?>" style="color:inherit;text-decoration:none"><?= e($r['title']) ?></a></h3>
          <p><?= e(when($r['event_at'])) ?></p>
          <p><?= e($r['area']) ?> · <?= e(price((int) $r['cost'])) ?></p>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</main>

<?php if (user() && !$ev['mine']): ?>
<dialog id="reportdlg">
  <form method="post" action="report.php">
    <h3>Report this gathering</h3>
    <?= csrf_field() ?>
    <input type="hidden" name="type" value="gathering">
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="back" value="event.php?id=<?= $id ?>">
    <div class="field"><label>What's wrong?</label>
      <textarea name="reason" required maxlength="300" rows="3" placeholder="Spam, unsafe, wrong details…"></textarea></div>
    <div class="actions">
      <button type="button" class="btn-ghost" data-close>Cancel</button>
      <button class="btn-danger" type="submit">Send report</button>
    </div>
  </form>
</dialog>
<?php endif; ?>

<?php bottom_nav('discover'); site_footer(); ?>
