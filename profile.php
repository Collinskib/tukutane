<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_login('profile.php');

$u   = user();
$err = '';
$uid = user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    switch ($_POST['do'] ?? '') {
        case 'save':
            $name  = trim(mb_substr($_POST['name'] ?? '', 0, 60));
            $bio   = trim(mb_substr($_POST['bio'] ?? '', 0, 300));
            $county = in_array($_POST['county'] ?? '', counties(), true) ? $_POST['county'] : '';
            [$area, $estate] = resolve_location($county);
            if ($county === '') $area = '';
            $ints  = array_values(array_filter($_POST['interests'] ?? [], fn($i) => isset(categories()[$i])));
            $disc  = !empty($_POST['discoverable']) ? 1 : 0;
            if ($name === '') { $err = 'Name is required.'; break; }
            $photo = upload_image('photo');
            if (str_starts_with($photo, '!')) { $err = substr($photo, 1); break; }
            $gender = in_array($_POST['gender'] ?? '', GENDERS, true) ? $_POST['gender'] : ($u['gender'] ?? '');
            $agebk  = in_array($_POST['age_bracket'] ?? '', AGE_BRACKETS, true) ? $_POST['age_bracket'] : ($u['age_bracket'] ?? '');
            $funFact = trim(mb_substr($_POST['fun_fact'] ?? '', 0, 160));
            $sql = 'UPDATE users SET name=:n,bio=:b,fun_fact=:ff,area=:a,county=:c,estate=:e,gender=:g,age_bracket=:ab,interests=:i,discoverable=:d';
            $p = [':n' => $name, ':b' => $bio, ':ff' => $funFact ?: null, ':a' => $area, ':c' => $county, ':e' => $estate,
                  ':g' => $gender, ':ab' => $agebk,
                  ':i' => implode(',', $ints), ':d' => $disc, ':u' => $uid];
            if ($photo) { $sql .= ',photo=:ph'; $p[':ph'] = $photo; }
            db()->prepare($sql . ' WHERE id=:u')->execute($p);
            auth_refresh();
            flash('Profile saved.');
            redirect('profile.php');
            break;
        case 'password':
            $cur = $_POST['current'] ?? ''; $new = $_POST['new'] ?? '';
            if (!password_verify($cur, $u['pass_hash']))       $err = 'Current password is wrong.';
            elseif (mb_strlen($new) < 8)                       $err = 'New password needs 8+ characters.';
            else {
                db()->prepare('UPDATE users SET pass_hash=:p WHERE id=:u')
                    ->execute([':p' => password_hash($new, PASSWORD_DEFAULT), ':u' => $uid]);
                flash('Password changed.');
                redirect('profile.php');
            }
            break;
        case 'delete':
            if (!password_verify($_POST['password'] ?? '', $u['pass_hash'])) {
                $err = 'Enter your password to delete the account.';
                break;
            }
            db()->prepare('DELETE FROM users WHERE id = :u')->execute([':u' => $uid]);
            logout();
            flash('Your account was deleted.');
            redirect('index.php');
            break;
    }
}

$myInts = array_filter(explode(',', $u['interests'] ?? ''));
page_head('My profile');
site_header();
?>
<main>
<div class="form rv in" style="max-width:520px">
  <div class="prof-head" style="border:0;padding:0;margin-bottom:16px">
    <?= avatar_html($u, 'lg', true) ?>
    <div class="info"><h2><?= e($u['name']) ?></h2><p><?= e($u['email']) ?><?= !empty($u['email_verified']) ? ' ✓' : '' ?><?= place_label($u) ? ' · ' . e(place_label($u)) : '' ?></p></div>
  </div>
  <?php if ($err): ?><p style="color:#a33a1f"><?= e($err) ?></p><?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="save">
    <div class="field"><label>Photo</label>
      <img id="photoprev" class="imgprev" <?= $u['photo'] ? 'src="' . img_url($u['photo'], 160) . '"' : 'hidden' ?> alt="">
      <input name="photo" type="file" accept="image/jpeg,image/png,image/webp" data-preview="#photoprev"></div>
    <div class="field"><label>Name</label><input name="name" required maxlength="60" value="<?= e($u['name']) ?>"></div>
    <div class="field"><label>Bio</label><textarea name="bio" maxlength="300" rows="3"
        placeholder="A line about you"><?= e($u['bio']) ?></textarea></div>
    <div class="field"><label>My fun fact or silly joke</label>
      <input name="fun_fact" maxlength="160" value="<?= e($u['fun_fact'] ?? '') ?>"
             placeholder="The thing people should know about you — it shows on your card"></div>
    <div class="field"><label>Gender</label>
      <select name="gender">
        <option value="">Prefer not to say</option>
        <?php foreach (['Male', 'Female'] as $g): ?>
        <option<?= ($u['gender'] ?? '') === $g ? ' selected' : '' ?>><?= e($g) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="field"><label>Age bracket</label>
      <select name="age_bracket">
        <option value="">Select…</option>
        <?php foreach (AGE_BRACKETS as $ab): ?>
        <option<?= ($u['age_bracket'] ?? '') === $ab ? ' selected' : '' ?>><?= e($ab) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="field"><label>Where you live</label>
      <?php location_picker($_POST['county'] ?? $u['county'] ?? '', $_POST['area'] ?? $u['area'] ?? '', $_POST['estate'] ?? $u['estate'] ?? '', 'optional'); ?></div>
    <div class="field"><label>Your interests</label>
      <div class="checkrow">
        <?php foreach (categories() as $cat => [$em, $col]): ?>
        <label><input type="checkbox" name="interests[]" value="<?= e($cat) ?>"
            <?= in_array($cat, $myInts, true) ? 'checked' : '' ?>> <?= $em ?> <?= e($cat) ?></label>
        <?php endforeach; ?>
      </div></div>
    <div class="field"><label style="display:flex;gap:8px;align-items:center;font-weight:400">
      <input type="checkbox" name="discoverable" value="1" style="width:auto"<?= $u['discoverable'] ? ' checked' : '' ?>>
      Show me in People nearby</label></div>
    <button class="btn-block" type="submit">Save profile</button>
  </form>
</div>

<div class="form rv in" style="max-width:520px">
  <h2>Change password</h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="password">
    <div class="field"><label>Current password</label>
      <div class="pw-wrap"><input name="current" type="password" required autocomplete="current-password">
        <button type="button" class="peek">Show</button></div></div>
    <div class="field"><label>New password (8+)</label>
      <div class="pw-wrap"><input name="new" type="password" required minlength="8" autocomplete="new-password">
        <button type="button" class="peek">Show</button></div></div>
    <button class="btn-block" type="submit">Change password</button>
  </form>
</div>

<div class="form rv in" style="max-width:520px;border-color:#eccfc8">
  <h2 style="color:#a33a1f">Delete account</h2>
  <p style="color:var(--ink-soft)">Removes your profile, RSVPs, friendships and messages. Permanent.</p>
  <form method="post" onsubmit="return confirm('Really delete your account? This is permanent.')">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="delete">
    <div class="field"><label>Confirm with your password</label>
      <input name="password" type="password" required autocomplete="current-password"></div>
    <button class="btn-danger" type="submit">Delete my account</button>
  </form>
</div>
</main>
<?php bottom_nav('plans'); site_footer(); ?>
