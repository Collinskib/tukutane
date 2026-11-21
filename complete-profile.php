<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

require_login();
$u = user();

$next = $_GET['next'] ?? 'index.php';
if (!preg_match('~^[a-z0-9_./?=&#%-]+$~i', $next) || str_starts_with($next, '//')) $next = 'index.php';

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $next = $_POST['next'] ?? $next;
    if (!preg_match('~^[a-z0-9_./?=&#%-]+$~i', $next) || str_starts_with($next, '//')) $next = 'index.php';
    $gender = $_POST['gender'] ?? '';
    $age    = $_POST['age_bracket'] ?? '';
    if (!in_array($gender, GENDERS, true))      $err = 'Pick your gender.';
    elseif (!in_array($age, AGE_BRACKETS, true)) $err = 'Pick your age bracket.';
    else {
        [$area, $estate] = resolve_location($_POST['county'] ?? '', $u['area'] ?? '', $u['estate'] ?? '');
        $county = in_array($_POST['county'] ?? '', counties(), true) ? $_POST['county'] : ($u['county'] ?? '');
        $funFact = trim(mb_substr($_POST['fun_fact'] ?? '', 0, 160));
        db()->prepare('UPDATE users SET gender=:g, age_bracket=:ab, county=:c, area=:a, estate=:e, fun_fact=:ff WHERE id=:id')
            ->execute([
                ':g' => $gender, ':ab' => $age,
                ':c' => $county, ':a' => $area, ':e' => $estate,
                ':ff' => $funFact ?: null,
                ':id' => $u['id'],
            ]);
        auth_refresh();
        flash('All set — karibu, ' . $u['name'] . '!');
        redirect('welcome.php?next=' . urlencode($next));
    }
}

page_head('Complete your profile');
site_header();
?>
<main>
<div class="form rv in" style="max-width:460px">
  <h2 style="margin:0 0 6px">One more step</h2>
  <p class="muted" style="margin:0 0 16px">Just the same details we ask everyone at signup — it takes ten seconds.</p>
  <?php if ($err): ?><p class="err" style="color:#a33a1f"><?= e($err) ?></p><?php endif; ?>

  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <div class="field"><label for="gender">Gender</label>
      <select id="gender" name="gender" required>
        <option value="">Choose…</option>
        <?php foreach (GENDERS as $g): ?>
        <option<?= ($_POST['gender'] ?? $u['gender'] ?? '') === $g ? ' selected' : '' ?>><?= e($g) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="field"><label for="age_bracket">Age bracket</label>
      <select id="age_bracket" name="age_bracket" required>
        <option value="">Choose…</option>
        <?php foreach (AGE_BRACKETS as $ab): ?>
        <option<?= ($_POST['age_bracket'] ?? $u['age_bracket'] ?? '') === $ab ? ' selected' : '' ?>><?= e($ab) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="field"><label for="fun_fact">My fun fact or silly joke (optional)</label>
      <input id="fun_fact" name="fun_fact" maxlength="160" value="<?= e($_POST['fun_fact'] ?? $u['fun_fact'] ?? '') ?>"
             placeholder="The thing people should know about you"></div>
    <div class="field"><label>Where do you live? (optional)</label>
      <?php location_picker($_POST['county'] ?? $u['county'] ?? '', $_POST['area'] ?? $u['area'] ?? '', $_POST['estate'] ?? $u['estate'] ?? '', 'optional'); ?>
    </div>
    <button class="btn primary" type="submit">Save and continue</button>
  </form>
</div>
</main>
<?php site_footer(); ?>
