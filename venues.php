<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_login('venues.php');

$f = [
    'q'    => trim($_GET['q'] ?? ''),
    'area' => in_array($_GET['area'] ?? '', areas(), true) ? $_GET['area'] : '',
];
$venues = venues_list($f);

page_head('Venues');
site_header('venues');
?>
<main>
  <div class="ph rv in">
    <h1>Venues</h1>
    <p>Spots around Nairobi where plans happen — link yours when you create one.</p>
  </div>

  <form class="toolbar" method="get" action="venues.php">
    <input name="q" value="<?= e($f['q']) ?>" placeholder="Search venues…" maxlength="60">
    <select name="area" onchange="this.form.submit()">
      <option value="">All neighbourhoods</option>
      <?php foreach (areas() as $a): ?><option<?= $f['area'] === $a ? ' selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?>
    </select>
    <button class="btn-mini" type="submit">Search</button>
    <a class="btn-mini solid" href="venue-edit.php">+ Add a venue</a>
  </form>

  <div class="grid2" style="grid-template-columns:repeat(auto-fill,minmax(240px,1fr));margin-top:16px">
    <?php if (!$venues): ?>
    <div class="empty" style="grid-column:1/-1"><span class="big">📍</span>
      <p>No venues yet — add the spot where your plans happen.</p>
      <a class="primary btn" href="venue-edit.php">Add the first venue</a></div>
    <?php endif; ?>
    <?php foreach ($venues as $i => $v): ?>
    <div class="card rv" style="--d:<?= min($i * 40, 400) ?>ms">
      <?php if ($v['photo']): ?>
      <a href="venue.php?id=<?= (int) $v['id'] ?>"><img src="<?= e($v['photo']) ?>" alt="" style="width:100%;height:130px;object-fit:cover;display:block"></a>
      <?php else: ?>
      <div class="ribbon" style="background:var(--mist);color:var(--green-2)"><span>📍 Venue</span><span><?= (int) $v['upcoming'] ?> upcoming</span></div>
      <?php endif; ?>
      <div class="body">
        <h3><a href="venue.php?id=<?= (int) $v['id'] ?>" style="color:inherit;text-decoration:none"><?= e($v['name']) ?></a></h3>
        <?php if ($v['description']): ?><p><?= e(mb_strimwidth($v['description'], 0, 110, '…')) ?></p><?php endif; ?>
        <p class="fine" style="margin:0">📍 <?= e($v['area']) ?><?= $v['photo'] ? ' · ' . (int) $v['upcoming'] . ' upcoming' : '' ?><?= $v['creator_name'] ? ' · by ' . e($v['creator_name']) : '' ?></p>
      </div>
      <div class="actions" style="padding:0 16px 16px"><a class="btn-mini" href="venue.php?id=<?= (int) $v['id'] ?>">Open</a></div>
    </div>
    <?php endforeach; ?>
  </div>
</main>
<?php bottom_nav('venues'); site_footer(); ?>
