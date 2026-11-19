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
            $name = trim(mb_substr($_POST['name'] ?? '', 0, 60));
            $sort = (int) ($_POST['sort'] ?? 99);
            if ($name) db()->prepare('INSERT INTO areas (name,sort) VALUES (:n,:s)
                                      ON DUPLICATE KEY UPDATE sort=:s2')
                       ->execute([':n'=>$name,':s'=>$sort,':s2'=>$sort]);
            break;
        case 'del_area':
            db()->prepare('DELETE FROM areas WHERE name=:n')->execute([':n'=>$_POST['name'] ?? '']);
            break;
    }
    // bust the per-request caches by simply redirecting
    redirect('vocab.php');
}

$cats = db()->query('SELECT * FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$ar   = db()->query('SELECT * FROM areas ORDER BY sort, name')->fetchAll(PDO::FETCH_ASSOC);

admin_page('Categories & areas', 'vocab', function () use ($cats, $ar) { ?>
  <h2>Categories & neighbourhoods</h2>
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
      <h3 style="font-family:var(--serif);font-weight:400;margin-top:0">Neighbourhoods</h3>
      <?php foreach ($ar as $a): ?>
      <div style="display:flex;gap:10px;align-items:center;padding:7px 0;border-bottom:1px solid var(--line-soft)">
        <b style="flex:1"><?= e($a['name']) ?></b><small style="color:var(--ink-soft)">sort <?= (int) $a['sort'] ?></small>
        <form method="post" onsubmit="return confirm('Delete <?= e($a['name']) ?>?')"><?= csrf_field() ?>
          <input type="hidden" name="do" value="del_area"><input type="hidden" name="name" value="<?= e($a['name']) ?>">
          <button class="btn-mini" type="submit">✕</button></form>
      </div>
      <?php endforeach; ?>
      <form method="post" style="display:flex;gap:8px;margin-top:14px"><?= csrf_field() ?>
        <input type="hidden" name="do" value="add_area">
        <input name="name" placeholder="Neighbourhood" required maxlength="60" style="flex:2;border:1px solid var(--line);border-radius:9px;padding:8px">
        <input name="sort" type="number" value="99" style="width:64px;border:1px solid var(--line);border-radius:9px;padding:8px">
        <button class="btn-mini solid" type="submit">Add</button>
      </form>
    </section>
  </div>
<?php });
