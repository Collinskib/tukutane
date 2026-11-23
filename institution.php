<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$id   = (int) ($_GET['id'] ?? 0);
$inst = institution_get($id);

page_head($inst ? $inst['name'] : 'Institution');
site_header();

if (!$inst || $inst['status'] !== 'active'):
?>
<main><div class="empty"><span class="big">🫥</span><p>Institution not found.</p>
  <a class="primary btn" href="institutions.php">Browse institutions</a></div></main>
<?php else:
$camps  = campuses_for($id);
$events = institution_events($id);
$biz    = institution_venues($id);
?>
<main class="ev-wrap">
  <div class="card rv in" style="padding:0;overflow:hidden">
    <div class="cband tall" style="background:linear-gradient(135deg,var(--green-2),var(--green-3))"><span class="big-emo">🎓</span></div>
    <div class="body" style="padding:20px 22px">
      <h2 style="margin:0 0 4px"><?= e($inst['name']) ?></h2>
      <p class="fine" style="margin:0"><?= e(institution_type_label($inst['type'])) ?>
        <?= $inst['county'] ? ' · ' . e($inst['county']) : '' ?><?= $inst['town'] ? ', ' . e($inst['town']) : '' ?></p>
      <?php if ($inst['ref']): ?><p class="fine" style="margin:4px 0 0">Ref: <?= e($inst['ref']) ?> · source: <?= e($inst['source'] === 'cue' ? 'Commission for University Education' : 'TVET Authority') ?></p><?php endif; ?>
      <div class="share-row" style="margin-top:14px">
        <a class="btn-mini" href="index.php?scope=inst&inst=<?= $id ?>">📅 Events at <?= e(mb_strimwidth($inst['name'], 0, 24, '…')) ?></a>
        <a class="btn-mini" href="venues.php?inst=<?= $id ?>">🏪 Businesses nearby</a>
        <?php if (user()): ?><a class="btn-mini" href="create.php">+ Create an event</a><?php endif; ?>
      </div>
    </div>
  </div>

  <h3 style="font-family:var(--serif);font-weight:400">Campuses</h3>
  <div class="chips" style="margin-bottom:18px">
    <?php foreach ($camps as $c): ?>
    <span class="chip"><?= e($c['name']) ?><?= $c['town'] ? ' · ' . e($c['town']) : '' ?><?= $c['county'] && $c['county'] !== $inst['county'] ? ', ' . e($c['county']) : '' ?></span>
    <?php endforeach; ?>
    <?php if (!$camps): ?><span class="chip">Main Campus</span><?php endif; ?>
  </div>

  <h3 style="font-family:var(--serif);font-weight:400">Upcoming events</h3>
  <?php if (!$events): ?>
  <div class="empty"><span class="big">🗓️</span><p>No events tagged to <?= e($inst['name']) ?> yet.</p>
    <?php if (user()): ?><a class="primary btn" href="create.php">Create one</a><?php endif; ?></div>
  <?php else: ?>
  <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(220px,1fr))">
    <?php foreach ($events as $i => $r): [$em, $rc] = categories()[$r['category']] ?? ['📅', '#e4e6db']; ?>
    <article class="card rv" style="--d:<?= min($i * 45, 300) ?>ms">
      <?php if (!empty($r['cover'])): ?>
      <div class="ribbon cover"><img src="<?= img_url($r['cover'], 800) ?>" alt="" loading="lazy" decoding="async"></div>
      <?php else: ?>
      <div class="ribbon" style="background:<?= e($rc) ?>"><span><?= e($r['category']) ?></span><span><?= $em ?></span></div>
      <?php endif; ?>
      <div class="body">
        <h3><a href="event.php?id=<?= (int) $r['id'] ?>" style="color:inherit;text-decoration:none"><?= e($r['title']) ?></a></h3>
        <p><?= e(when($r['event_at'])) ?> · <?= e(place_label($r)) ?></p>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($biz): ?>
  <h3 style="font-family:var(--serif);font-weight:400">Businesses for this community</h3>
  <div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(220px,1fr))">
    <?php foreach ($biz as $b): ?>
    <article class="card"><div class="body">
      <h3><a href="venue.php?id=<?= (int) $b['id'] ?>" style="color:inherit;text-decoration:none">🏪 <?= e($b['name']) ?></a></h3>
      <p><?= e(place_label($b)) ?></p>
    </div></article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>
<?php endif; ?>
<?php bottom_nav(); site_footer(); ?>
