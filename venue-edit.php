<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_login('venues.php');

$id = (int) ($_GET['id'] ?? 0);
$v  = $id ? venue_get($id) : null;
if ($id && !$v) { flash('Venue not found.'); redirect('venues.php'); }
if ($v && !user()['is_admin'] && (int) $v['creator_id'] !== user_id()) {
    flash('Only the person who added this venue can edit it.');
    redirect('venue.php?id=' . $id);
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = trim(mb_substr($_POST['name'] ?? '', 0, 80));
    $county = in_array($_POST['county'] ?? '', counties(), true) ? $_POST['county'] : '';
    [$area, $estate] = resolve_location($county, $v['area'] ?? '', $v['estate'] ?? '');
    $desc = trim(mb_substr($_POST['description'] ?? '', 0, 600));
    $photo = upload_image('photo');
    if (str_starts_with($photo, '!')) $err = substr($photo, 1);
    elseif ($name === '') $err = 'Give the venue a name.';
    elseif ($area === '') $err = 'Pick a neighbourhood.';
    else {
        $d = ['name' => $name, 'area' => $area, 'county' => $county, 'estate' => $estate, 'description' => $desc];
        if ($photo) $d['photo'] = $photo;
        if ($v) {
            venue_update($id, $d);
            flash('Venue updated.');
        } else {
            $id = venue_create(user_id(), $d);
            flash('Venue added — pick it next time you create a plan!');
        }
        redirect('venue.php?id=' . $id);
    }
}

page_head($v ? 'Edit ' . $v['name'] : 'Add a venue');
site_header('venues');
?>
<main class="ev-wrap">
  <div class="card rv in"><div class="body">
    <h2 style="margin-top:0;font-family:var(--serif);font-weight:400"><?= $v ? 'Edit ' . e($v['name']) : 'Add a venue' ?></h2>
    <?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="field"><label for="name">Name</label>
        <input id="name" name="name" required maxlength="80" value="<?= e($_POST['name'] ?? ($v['name'] ?? '')) ?>"
               placeholder="e.g. Yaya Centre rooftop, Karura gate B"></div>
      <div class="field"><label>Where</label>
        <?php location_picker($_POST['county'] ?? ($v['county'] ?? ''), $_POST['area'] ?? ($v['area'] ?? ''), $_POST['estate'] ?? ($v['estate'] ?? '')); ?></div>
      <div class="field"><label for="description">About this spot <small>(optional)</small></label>
        <textarea id="description" name="description" rows="3" maxlength="600"
                  placeholder="What is it good for? How do people find it?"><?= e($_POST['description'] ?? ($v['description'] ?? '')) ?></textarea></div>
      <div class="field"><label for="photo">Photo <small>(optional)</small></label>
        <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp">
        <?php if ($v['photo'] ?? ''): ?><small>Current: <a href="<?= e($v['photo']) ?>" target="_blank">view</a> — upload to replace.</small><?php endif; ?></div>
      <button class="primary" type="submit"><?= $v ? 'Save changes' : 'Add venue' ?></button>
      <a class="btn-ghost" href="<?= $v ? 'venue.php?id=' . $id : 'venues.php' ?>">Cancel</a>
    </form>
  </div></div>
</main>
<?php bottom_nav('venues'); site_footer(); ?>
