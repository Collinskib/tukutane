<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_login('venues.php');

$f = [
    'q'    => trim($_GET['q'] ?? ''),
    'county' => in_array($_GET['county'] ?? '', counties(), true) ? $_GET['county'] : '',
    'area' => in_array($_GET['area'] ?? '', areas(), true) ? $_GET['area'] : '',
    'me_county' => user()['county'] ?? '', 'me_area' => user()['area'] ?? '', 'me_estate' => user()['estate'] ?? '',
];
$venues = venues_list($f);

page_head('Business');
site_header('venues');
?>
<main>
  <nav class="homepills" aria-label="Explore">
    <a href="/people.php">🧑‍🤝‍🧑 People</a>
    <a href="/create.php">📅 Create Event</a>
    <a class="on" href="/venues.php">🏪 Business</a>
  </nav>
  <div class="ph rv in">
    <h1>Business</h1>
    <p>Shops, cafés and spots around Kenya — message the owners, or create your own business page.</p>
  </div>

  <form class="toolbar" method="get" action="venues.php">
    <input name="q" value="<?= e($f['q']) ?>" placeholder="Search businesses…" maxlength="60">
    <span onchange="this.form.submit()"><?php location_picker($f['county'], $f['area'], '', 'filter'); ?></span>
    <button class="btn-mini" type="submit">Search</button>
    <a class="btn-mini solid" href="venue-edit.php">+ Add your business</a>
  </form>

  <div class="grid2" style="grid-template-columns:repeat(auto-fill,minmax(240px,1fr));margin-top:16px">
    <?php if (!$venues): ?>
    <div class="empty" style="grid-column:1/-1"><span class="big">🏪</span>
      <p>No businesses here yet — be the first to list yours.</p>
      <a class="primary btn" href="venue-edit.php">Add your business</a></div>
    <?php endif; ?>
    <?php foreach ($venues as $i => $v): ?>
    <div class="card rv" style="--d:<?= min($i * 40, 400) ?>ms">
      <?php if ($v['photo']): ?>
      <a href="venue.php?id=<?= (int) $v['id'] ?>"><img src="<?= e($v['photo']) ?>" alt="" style="width:100%;height:130px;object-fit:cover;display:block"></a>
      <?php else: ?>
      <div class="ribbon" style="background:var(--mist);color:var(--green-2)"><span>🏪 Business</span><span><?= (int) $v['upcoming'] ?> upcoming</span></div>
      <?php endif; ?>
      <div class="body">
        <h3><a href="venue.php?id=<?= (int) $v['id'] ?>" style="color:inherit;text-decoration:none"><?= e($v['name']) ?></a></h3>
        <?php if ($v['description']): ?><p><?= e(mb_strimwidth($v['description'], 0, 110, '…')) ?></p><?php endif; ?>
        <p class="fine" style="margin:0">📍 <?= e(place_label($v)) ?><?= $v['photo'] ? ' · ' . (int) $v['upcoming'] . ' upcoming' : '' ?><?= $v['creator_name'] ? ' · by ' . e($v['creator_name']) : '' ?></p>
      </div>
      <div class="actions" style="padding:0 16px 16px">
        <a class="btn-mini" href="venue.php?id=<?= (int) $v['id'] ?>">Open</a>
        <?php if ((int) $v['creator_id'] !== user_id()): ?>
        <a class="btn-mini solid" href="messages.php?to=<?= (int) $v['creator_id'] ?>">💬 Message</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</main>
<?php bottom_nav('venues'); site_footer(); ?>
