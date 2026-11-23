<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_login('index.php');

$id = (int) ($_GET['id'] ?? 0);
$v  = venue_get($id);

page_head($v ? $v['name'] : 'Business');
site_header('venues');

if (!$v):
?>
<main><div class="empty"><span class="big">🫥</span><p>Business not found.</p><a class="primary btn" href="venues.php">Back to Business</a></div></main>
<?php else:
$events = venue_events($id);
$canEdit = user()['is_admin'] || (int) $v['creator_id'] === user_id();
?>
<main class="ev-wrap">
  <div class="card rv in" style="padding:0;overflow:hidden">
    <?php if ($v['photo']): ?>
    <img src="<?= img_url($v['photo'], 800) ?>" alt="<?= e($v['name']) ?>" style="width:100%;height:230px;object-fit:cover;display:block">
    <?php else: ?>
    <div class="cband tall" style="background:linear-gradient(135deg,#f0e0bb,var(--mist))"><span class="big-emo">🏪</span></div>
    <?php endif; ?>
    <div class="body" style="padding:20px 22px">
      <h2 style="margin:0 0 4px">📍 <?= e($v['name']) ?></h2>
      <p class="fine" style="margin:0"><?= e(place_label($v)) ?><?= $v['creator_name'] ? ' · added by ' . e($v['creator_name']) : '' ?><?= $events ? ' · ' . count($events) . ' upcoming' : '' ?></p>
      <?php if ($v['description']): ?><p style="margin:10px 0 0;max-width:60ch;color:var(--ink-soft)"><?= nl2br(e($v['description'])) ?></p><?php endif; ?>
      <div class="share-row" style="margin-top:14px">
        <?php if (!$canEdit && (int) $v['creator_id'] !== user_id()): ?>
        <a class="btn-mini solid" href="messages.php?to=<?= (int) $v['creator_id'] ?>">💬 Message business</a>
        <?php endif; ?>
        <?php if ($canEdit): ?><a class="btn-mini" href="venue-edit.php?id=<?= $id ?>">Edit business</a><?php endif; ?>
        <a class="btn-mini" href="create.php">+ Plan something here</a>
      </div>
    </div>
  </div>

  <h3 style="font-family:var(--serif);font-weight:400">Upcoming at <?= e($v['name']) ?></h3>
  <?php if (!$events): ?>
  <div class="empty"><span class="big">🗓️</span><p>Nothing planned here yet — be the first.</p>
    <a class="primary btn" href="create.php">Create a plan</a></div>
  <?php else: ?>
  <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(220px,1fr))">
    <?php foreach ($events as $r): [, $rc] = categories()[$r['category']] ?? ['', '#e4e6db']; ?>
    <article class="card">
      <div class="ribbon" style="background:<?= e($rc) ?>"><span><?= e($r['category']) ?></span><span><?= e((categories()[$r['category']] ?? ['📅'])[0]) ?></span></div>
      <div class="body">
        <h3><a href="event.php?id=<?= (int) $r['id'] ?>" style="color:inherit;text-decoration:none"><?= e($r['title']) ?></a>
          <?= $r['series_id'] ? '🔁' : '' ?></h3>
        <p><?= e(when($r['event_at'])) ?> · <?= (int) $r['real_rsvps'] + (int) $r['going_base'] ?> going<?= $r['capacity'] ? ' / ' . (int) $r['capacity'] : '' ?></p>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>
<?php endif; ?>
<?php bottom_nav('venues'); site_footer(); ?>
