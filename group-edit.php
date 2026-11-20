<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_login('groups.php');

$uid = user_id();
$g = null;
if (!empty($_GET['id'])) {
    $g = group_get((int) $_GET['id']);
    if (!$g) { flash('Group not found.', 'err'); redirect('groups.php'); }
    $me = group_membership((int) $g['id'], $uid);
    if ((!$me || $me['role'] !== 'admin' || $me['status'] !== 'active') && (int) $g['creator_id'] !== $uid) {
        flash('Only group admins can edit.', 'err');
        redirect('group.php?id=' . (int) $g['id']);
    }
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name    = trim(mb_substr($_POST['name'] ?? '', 0, 80));
    $desc    = trim(mb_substr($_POST['description'] ?? '', 0, 600));
    $cat     = $_POST['category'] ?? '';
    [$area, $estate] = resolve_location($_POST['county'] ?? '', $g['area'] ?? '', $g['estate'] ?? '');
    $county  = $_POST['county'] ?? '';
    $policy  = in_array($_POST['join_policy'] ?? '', ['open', 'approval'], true) ? $_POST['join_policy'] : 'open';

    if ($name === '')                          $err = 'Give the group a name.';
    elseif (!isset(categories()[$cat]))        $err = 'Pick a category.';
    elseif (!area_valid($county, $area))       $err = 'Pick your county and area.';
    else {
        $d = ['name' => $name, 'description' => $desc, 'category' => $cat, 'area' => $area, 'county' => $county, 'estate' => $estate, 'join_policy' => $policy];
        if ($g) {
            group_update((int) $g['id'], $d);
            flash('Group updated.');
            redirect('group.php?id=' . (int) $g['id']);
        } else {
            $gid = group_create($uid, $d);
            flash('Your group is live!');
            redirect('group.php?id=' . $gid);
        }
    }
}

$v = fn(string $k, string $def = '') => e($_POST[$k] ?? ($g[$k] ?? $def));

page_head($g ? 'Edit group' : 'Start a group');
site_header('groups');
?>
<main>
<div class="form rv in" style="max-width:520px">
  <h2><?= $g ? 'Edit group' : 'Start a group' ?></h2>
  <?php if ($err): ?><p style="color:#a33a1f"><?= e($err) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <div class="field"><label for="name">Group name</label>
      <input id="name" name="name" required maxlength="80" value="<?= $v('name') ?>"
             placeholder="e.g. Westlands runners, Kilimani book club"></div>

    <div class="grid2">
      <div class="field"><label for="category">Interest</label>
        <select id="category" name="category">
          <?php foreach (array_keys(categories()) as $cat): ?>
          <option<?= ($_POST['category'] ?? $g['category'] ?? '') === $cat ? ' selected' : '' ?>><?= e($cat) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="field"><label>Where</label>
        <?php location_picker($_POST['county'] ?? ($g['county'] ?? ''), $_POST['area'] ?? ($g['area'] ?? ''), $_POST['estate'] ?? ($g['estate'] ?? '')); ?></div>
    </div>

    <div class="field"><label for="desc">What is this group about?</label>
      <textarea id="desc" name="description" maxlength="600" rows="4"
                placeholder="Who it's for, when you meet, what to expect…"><?= $v('description') ?></textarea></div>

    <div class="field"><label>Who can join?</label>
      <label style="display:flex;gap:8px;align-items:center;cursor:pointer;font-weight:400">
        <input type="radio" name="join_policy" value="open" style="width:auto"<?= ($_POST['join_policy'] ?? $g['join_policy'] ?? 'open') === 'open' ? ' checked' : '' ?>>
        Open — anyone can join instantly</label>
      <label style="display:flex;gap:8px;align-items:center;cursor:pointer;font-weight:400;margin-top:6px">
        <input type="radio" name="join_policy" value="approval" style="width:auto"<?= ($_POST['join_policy'] ?? $g['join_policy'] ?? '') === 'approval' ? ' checked' : '' ?>>
        Approval — new members need an admin's OK</label>
    </div>

    <button class="primary" type="submit"><?= $g ? 'Save changes' : 'Create group' ?></button>
  </form>
</div>
</main>
<?php bottom_nav('groups'); site_footer(); ?>
