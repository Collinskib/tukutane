<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_login('index.php');

$id = (int) ($_GET['id'] ?? 0);
$v  = venue_get($id);

page_head($v ? $v['name'] : 'Venue');
site_header('venues');

if (!$v):
?>
<main><div class="empty"><span class="big">🫥</span><p>Venue not found.</p><a class="primary btn" href="venues.php">Back to Venues</a></div></main>
<?php else:
$events = venue_events($id);
$canEdit = user()['is_admin'] || (int) $v['creator_id'] === user_id();
?>
<main class="ev-wrap">
  <?php if ($v['photo']): ?>
  <div class="card rv in" style="overflow:hidden"><img src="<?= e($v['photo']) ?>" alt="<?= e($v['name']) ?>" style="width:100%;max-height:220px;object-fit:cover;display:block"></div>
  <?php endif; ?>

  <div class="prof-head rv in">
    <div class="info">
      <h2>📍 <?= e($v['name']) ?></h2>
      <p><?= e($v['area']) ?><?= $v['creator_name'] ? ' · added by ' . e($v['creator_name']) : '' ?></p>
      <?php if ($v['description']): ?><p style="margin-top:6px"><?= e($v['description']) ?></p><?php endif; ?>
    </div>
  </div>

  <div class="share-row">
    <?php if ($canEdit): ?><a class="btn-mini" href="venue-edit.php?id=<?= $id ?>">Edit venue</a><?php endif; ?>
    <a class="btn-mini" href="create.php">+ Plan something here</a>
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
