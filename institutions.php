<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$type   = in_array($_GET['type'] ?? '', array_keys(INSTITUTION_TYPES), true) ? $_GET['type'] : '';
$county = in_array($_GET['county'] ?? '', counties(), true) ? $_GET['county'] : '';
$q      = trim($_GET['q'] ?? '');

$rows = institutions_list(compact('type', 'county', 'q'));

page_head('Institutions');
site_header();
?>
<main>
  <div class="toolbar">
    <h2 id="heading">🎓 Institutions</h2>
    <form method="get" action="institutions.php" style="display:flex;gap:8px;flex-wrap:wrap">
      <input name="q" type="search" placeholder="Search universities & colleges"
             value="<?= e($q) ?>" style="max-width:280px">
      <?php if ($type): ?><input type="hidden" name="type" value="<?= e($type) ?>"><?php endif; ?>
      <?php if ($county): ?><input type="hidden" name="county" value="<?= e($county) ?>"><?php endif; ?>
      <button class="btn-mini" type="submit">Search</button>
    </form>
  </div>

  <div class="chips" style="margin-bottom:8px">
    <a class="chip <?= $type === '' ? 'on' : '' ?>" href="institutions.php<?= $county || $q ? '?' . e(http_build_query(array_filter(compact('county', 'q')))) : '' ?>">All</a>
    <?php foreach (INSTITUTION_TYPES as $t => $label): ?>
    <a class="chip <?= $type === $t ? 'on' : '' ?>"
       href="institutions.php?<?= e(http_build_query(array_filter(['type' => $t, 'county' => $county, 'q' => $q]))) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>
  <form method="get" action="institutions.php" class="chips" style="margin-bottom:16px">
    <?php if ($type): ?><input type="hidden" name="type" value="<?= e($type) ?>"><?php endif; ?>
    <?php if ($q): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
    <select name="county" onchange="this.form.submit()" style="width:auto">
      <option value="">All counties</option>
      <?php foreach (counties() as $c): ?>
      <option<?= $county === $c ? ' selected' : '' ?>><?= e($c) ?></option>
      <?php endforeach; ?>
    </select>
  </form>

  <?php if (!$rows): ?>
  <div class="empty"><span class="big">🏫</span><p>No institutions match — try another search.</p></div>
  <?php else: ?>
  <p class="fine" style="margin:0 0 10px"><?= count($rows) ?><?= count($rows) === 600 ? '+' : '' ?> institutions</p>
  <div class="plist instgrid">
    <?php foreach ($rows as $i => $r): ?>
    <article class="card instcard rv" style="--d:<?= min($i * 25, 300) ?>ms">
      <a href="institution.php?id=<?= (int) $r['id'] ?>" class="instcard-in">
        <div class="inst-ic">🏫</div>
        <div>
          <h3><?= e($r['name']) ?></h3>
          <p class="fine"><?= e(institution_type_label($r['type'])) ?><?= $r['county'] ? ' · ' . e($r['county']) : '' ?><?= $r['town'] ? ', ' . e($r['town']) : '' ?></p>
          <?php if ((int) $r['campus_count'] > 1): ?><p class="fine"><?= (int) $r['campus_count'] ?> campuses</p><?php endif; ?>
        </div>
      </a>
    </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>
<?php bottom_nav(); site_footer(); ?>
