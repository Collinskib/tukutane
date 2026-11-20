<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
require_admin();
require_once __DIR__ . '/_layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    switch ($_POST['do'] ?? '') {
        case 'add_cat':
            $name = trim(mb_substr($_POST['name'] ?? '', 0, 40));
            $emoji = trim(mb_substr($_POST['emoji'] ?? '📅', 0, 8));
            $color = preg_match('/^#[0-9a-f]{6}$/i', $_POST['color'] ?? '') ? $_POST['color'] : '#e4e6db';
            if ($name) db()->prepare('INSERT INTO categories (name,emoji,color) VALUES (:n,:e,:c)
                                      ON DUPLICATE KEY UPDATE emoji=:e2,color=:c2')
                       ->execute([':n'=>$name,':e'=>$emoji,':c'=>$color,':e2'=>$emoji,':c2'=>$color]);
            break;
        case 'del_cat':
            db()->prepare('DELETE FROM categories WHERE name=:n')->execute([':n'=>$_POST['name'] ?? '']);
            break;
        case 'add_area':
            $name   = trim(mb_substr($_POST['name'] ?? '', 0, 60));
            $county = in_array($_POST['county'] ?? '', counties(), true) ? $_POST['county'] : '';
            $sort   = (int) ($_POST['sort'] ?? 99);
            if ($name && $county) db()->prepare('INSERT INTO areas (county,name,sort) VALUES (:c,:n,:s)
                                      ON DUPLICATE KEY UPDATE sort=:s2')
                       ->execute([':c'=>$county,':n'=>$name,':s'=>$sort,':s2'=>$sort]);
            break;
        case 'del_area':
            db()->prepare('DELETE FROM areas WHERE county=:c AND name=:n')
                ->execute([':c'=>$_POST['county'] ?? '', ':n'=>$_POST['name'] ?? '']);
            break;
    }
    // bust the per-request caches by simply redirecting
    redirect('vocab.php');
}

$cats = db()->query('SELECT * FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$ar   = db()->query('SELECT * FROM areas ORDER BY county, sort, name')->fetchAll(PDO::FETCH_ASSOC);
$byCounty = [];
foreach ($ar as $a) $byCounty[$a['county']][] = $a;
$filterCounty = $_GET['county'] ?? 'Nairobi';
if (!isset($byCounty[$filterCounty])) $filterCounty = array_key_first($byCounty) ?? 'Nairobi';

admin_page('Categories & areas', 'vocab', function () use ($cats, $byCounty, $filterCounty) { ?>
  <h2>Categories & locations</h2>
  <div class="grid2">
    <section class="panel" style="padding:16px">
      <h3 style="font-family:var(--serif);font-weight:400;margin-top:0">Categories</h3>
      <?php foreach ($cats as $c): ?>
      <div style="display:flex;gap:10px;align-items:center;padding:7px 0;border-bottom:1px solid var(--line-soft)">
        <span style="width:28px;height:28px;border-radius:8px;background:<?= e($c['color']) ?>;display:inline-flex;align-items:center;justify-content:center"><?= e($c['emoji']) ?></span>
        <b style="flex:1"><?= e($c['name']) ?></b><code style="font-size:12px;color:var(--ink-soft)"><?= e($c['color']) ?></code>
        <form method="post" onsubmit="return confirm('Delete category <?= e($c['name']) ?>?')"><?= csrf_field() ?>
          <input type="hidden" name="do" value="del_cat"><input type="hidden" name="name" value="<?= e($c['name']) ?>">
          <button class="btn-mini" type="submit">✕</button></form>
      </div>
      <?php endforeach; ?>
      <form method="post" style="display:flex;gap:8px;margin-top:14px;flex-wrap:wrap"><?= csrf_field() ?>
        <input type="hidden" name="do" value="add_cat">
        <input name="name" placeholder="Name" required maxlength="40" style="flex:2;border:1px solid var(--line);border-radius:9px;padding:8px">
        <input name="emoji" placeholder="Emoji" maxlength="8" style="width:64px;border:1px solid var(--line);border-radius:9px;padding:8px">
        <input name="color" type="color" value="#dce9d3" style="width:44px;height:38px;border:1px solid var(--line);border-radius:9px;padding:2px">
        <button class="btn-mini solid" type="submit">Add</button>
      </form>
    </section>
    <section class="panel" style="padding:16px">
      <h3 style="font-family:var(--serif);font-weight:400;margin-top:0">Locations (<?= count($byCounty) ?> counties)</h3>
      <form method="get" style="margin-bottom:10px">
        <select name="county" onchange="this.form.submit()" style="border:1px solid var(--line);border-radius:9px;padding:8px;width:100%">
          <?php foreach (counties() as $c): ?><option<?= $c === $filterCounty ? ' selected' : '' ?>><?= e($c) ?> (<?= count($byCounty[$c] ?? []) ?>)</option><?php endforeach; ?>
        </select>
      </form>
      <?php foreach (($byCounty[$filterCounty] ?? []) as $a): ?>
      <div style="display:flex;gap:10px;align-items:center;padding:7px 0;border-bottom:1px solid var(--line-soft)">
        <b style="flex:1"><?= e($a['name']) ?></b><small style="color:var(--ink-soft)">sort <?= (int) $a['sort'] ?></small>
        <form method="post" onsubmit="return confirm('Delete <?= e($a['name']) ?>, <?= e($a['county']) ?>?')"><?= csrf_field() ?>
          <input type="hidden" name="do" value="del_area">
          <input type="hidden" name="county" value="<?= e($a['county']) ?>"><input type="hidden" name="name" value="<?= e($a['name']) ?>">
          <button class="btn-mini" type="submit">✕</button></form>
      </div>
      <?php endforeach; ?>
      <form method="post" style="display:flex;gap:8px;margin-top:14px;flex-wrap:wrap"><?= csrf_field() ?>
        <input type="hidden" name="do" value="add_area">
        <select name="county" required style="flex:1;border:1px solid var(--line);border-radius:9px;padding:8px">
          <?php foreach (counties() as $c): ?><option<?= $c === $filterCounty ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
        </select>
        <input name="name" placeholder="Area / neighbourhood" required maxlength="60" style="flex:2;border:1px solid var(--line);border-radius:9px;padding:8px">
        <input name="sort" type="number" value="99" style="width:64px;border:1px solid var(--line);border-radius:9px;padding:8px">
        <button class="btn-mini solid" type="submit">Add</button>
      </form>
    </section>
  </div>
<?php });
