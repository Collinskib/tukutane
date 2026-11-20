<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_login('groups.php');

$view = ($_GET['view'] ?? '') === 'mine' ? 'mine' : 'discover';
$f = [
    'q'        => trim($_GET['q'] ?? ''),
    'category' => isset(categories()[$_GET['category'] ?? '']) ? $_GET['category'] : '',
    'county'   => in_array($_GET['county'] ?? '', counties(), true) ? $_GET['county'] : '',
    'area'     => in_array($_GET['area'] ?? '', areas($_GET['county'] ?? ''), true) ? $_GET['area'] : '',
    'view'     => $view,
];
$groups = groups_list($f);

page_head('Groups');
site_header('groups');
?>
<main>
  <div class="toolbar" style="justify-content:flex-start;gap:10px">
    <h2>Local groups</h2>
    <a class="primary" href="group-edit.php" style="margin-left:auto">+ Start a group</a>
  </div>
  <nav class="tabs" style="margin:4px 0 14px">
    <a href="groups.php" class="<?= $view === 'discover' ? 'active' : '' ?>">Discover</a>
    <a href="groups.php?view=mine" class="<?= $view === 'mine' ? 'active' : '' ?>">My groups</a>
  </nav>
  <form method="get" class="toolbar" style="justify-content:flex-start;gap:10px">
    <?php if ($view === 'mine'): ?><input type="hidden" name="view" value="mine"><?php endif; ?>
    <input name="q" type="search" placeholder="Search groups" value="<?= e($f['q']) ?>" style="border:1px solid var(--line);border-radius:var(--r-input);padding:10px 13px">
    <select name="category" onchange="this.form.submit()">
      <option value="">All interests</option>
      <?php foreach (array_keys(categories()) as $c): ?><option<?= $f['category'] === $c ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
    </select>
    <span onchange="this.form.submit()"><?php location_picker($f['county'], $f['area'], 'filter'); ?></span>
    <button class="btn-mini" type="submit">Search</button>
  </form>

  <div class="grid2" style="grid-template-columns:repeat(auto-fill,minmax(240px,1fr));margin-top:16px">
    <?php if (!$groups): ?>
    <div class="empty" style="grid-column:1/-1"><span class="big">🏘️</span>
      <p><?= $view === 'mine' ? 'You have not joined any groups yet — find one below or start your own.' : 'No groups match — try different filters, or start the first one for your neighbourhood.' ?></p></div>
    <?php endif; ?>
    <?php foreach ($groups as $i => $g): ?>
    <div class="card rv" style="--d:<?= min($i * 40, 400) ?>ms">
      <div class="ribbon" style="background:var(--mist);color:var(--green-2)">
        <span><?= e($g['category']) ?></span><span><?= (int) $g['members'] ?> 👥</span>
      </div>
      <div class="body">
        <h3><a href="group.php?id=<?= (int) $g['id'] ?>" style="color:inherit;text-decoration:none"><?= e($g['name']) ?></a>
          <?php if ($g['my_status'] === 'pending'): ?><span class="status-pill">Pending</span>
          <?php elseif ($g['my_status'] === 'active'): ?><span class="status-pill going"><?= $g['my_role'] === 'admin' ? 'Admin' : 'Joined' ?></span><?php endif; ?>
        </h3>
        <p><?= e(mb_strimwidth($g['description'], 0, 110, '…')) ?></p>
        <p class="fine" style="margin:0">📍 <?= e(place_label($g)) ?> · by <?= e($g['creator_name']) ?><?= $g['join_policy'] === 'approval' ? ' · approval needed' : '' ?></p>
      </div>
      <div class="actions" style="padding:0 16px 16px">
        <a class="btn-mini<?= $g['my_status'] === 'active' ? '' : ' solid' ?>" href="group.php?id=<?= (int) $g['id'] ?>">
          <?= $g['my_status'] === 'active' ? 'Open' : ($g['my_status'] === 'pending' ? 'View request' : ($g['join_policy'] === 'open' ? 'Join' : 'Request to join')) ?></a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</main>
<?php bottom_nav('groups'); site_footer(); ?>
