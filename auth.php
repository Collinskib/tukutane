<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$mode = ($_GET['mode'] ?? 'login') === 'register' ? 'register' : 'login';
$next = $_GET['next'] ?? 'index.php';
if (!preg_match('~^[a-z0-9_./?=&#%-]+$~i', $next) || str_starts_with($next, '//')) $next = 'index.php';

// Already signed in (e.g. came back from Google or reopened a tab) — showing
// the login form reads like the sign-in failed, so just carry on.
if (user()) {
    $dest = basename((string) parse_url($next, PHP_URL_PATH) ?: '') === 'auth.php' ? 'index.php' : $next;
    redirect($dest);
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $next = $_POST['next'] ?? $next;
    if (($_POST['mode'] ?? '') === 'register') {
        $mode = 'register';
        [$regArea, $regEstate] = resolve_location($_POST['county'] ?? '');
        $err = register_user(
            trim($_POST['email'] ?? ''),
            $_POST['password'] ?? '',
            trim($_POST['name'] ?? ''),
            $regArea,
            $regEstate,
            $_POST['county'] ?? '',
            $_POST['gender'] ?? '',
            $_POST['age_bracket'] ?? '',
            $_POST['fun_fact'] ?? ''
        );
    } else {
        $err = attempt_login(trim($_POST['email'] ?? ''), $_POST['password'] ?? '', !empty($_POST['remember']));
    }
    if (!$err) {
        flash($mode === 'register' ? 'Karibu! Your account is ready.' : 'Welcome back!');
        redirect($mode === 'register' ? 'welcome.php?next=' . urlencode($next) : $next);
    }
}

page_head($mode === 'register' ? 'Join Nipange' : 'Log in');
site_header();
?>
<main class="auth-stage">
  <div class="auth-bg" aria-hidden="true">
    <img src="/assets/landing/hero.jpg" alt="" decoding="async">
    <img src="/assets/landing/students.jpg" alt="" decoding="async">
    <img src="/assets/landing/spots.jpg" alt="" decoding="async">
    <img src="/assets/landing/festival.jpg" alt="" decoding="async">
    <img src="/assets/landing/stage.jpg" alt="" decoding="async">
  </div>
  <div class="auth-veil" aria-hidden="true"></div>
<div class="form rv in">
  <div class="tabs" style="border:0;margin:0 0 14px">
    <a href="?<?= $mode === 'login' ? '' : 'mode=login&' ?>next=<?= urlencode($next) ?>" class="<?= $mode === 'login' ? 'active' : '' ?>">Log in</a>
    <a href="?mode=register&next=<?= urlencode($next) ?>" class="<?= $mode === 'register' ? 'active' : '' ?>">Join Nipange</a>
  </div>
  <?php if ($err): ?><p class="err" style="color:#a33a1f"><?= e($err) ?></p><?php endif; ?>

  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="mode" value="<?= $mode ?>">
    <input type="hidden" name="next" value="<?= e($next) ?>">

    <?php if ($mode === 'register'): ?>
    <div class="field"><label for="name">Your name</label>
      <input id="name" name="name" required maxlength="60" value="<?= e($_POST['name'] ?? '') ?>" autocomplete="name"></div>
    <?php endif; ?>

    <div class="field"><label for="email">Email</label>
      <input id="email" name="email" type="email" required maxlength="190"
             value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email"></div>

    <div class="field"><label for="password">Password <?= $mode === 'register' ? '(8+ characters)' : '' ?></label>
      <div class="pw-wrap">
        <input id="password" name="password" type="password" required minlength="8"
               autocomplete="<?= $mode === 'register' ? 'new-password' : 'current-password' ?>">
        <button type="button" class="peek">Show</button>
      </div>
    </div>

    <?php if ($mode === 'login'): ?>
    <label style="display:flex;align-items:center;gap:8px;margin:0 0 14px;font-size:14px;cursor:pointer"><input type="checkbox" name="remember" value="1" checked> Keep me logged in</label>
    <?php endif; ?>

    <?php if ($mode === 'register'): ?>
    <div class="field"><label for="gender">Gender</label>
      <select id="gender" name="gender" required>
        <option value="">Select…</option>
        <?php foreach (GENDERS as $g): ?>
        <option<?= ($_POST['gender'] ?? '') === $g ? ' selected' : '' ?>><?= e($g) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="field"><label for="age_bracket">Age bracket</label>
      <select id="age_bracket" name="age_bracket" required>
        <option value="">Select…</option>
        <?php foreach (AGE_BRACKETS as $ab): ?>
        <option<?= ($_POST['age_bracket'] ?? '') === $ab ? ' selected' : '' ?>><?= e($ab) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="field"><label for="fun_fact">My fun fact or silly joke (optional)</label>
      <input id="fun_fact" name="fun_fact" maxlength="160" value="<?= e($_POST['fun_fact'] ?? '') ?>"
             placeholder="The thing people should know about you"></div>
    <div class="field"><label>Where do you live? (optional)</label>
      <?php location_picker($_POST['county'] ?? '', $_POST['area'] ?? '', $_POST['estate'] ?? '', 'optional'); ?>
    </div>
    <?php endif; ?>

    <button class="btn-block" type="submit"><?= $mode === 'register' ? 'Create account' : 'Log in' ?></button>
  </form>

  <?php if (defined('GOOGLE_CLIENT_ID')): ?>
  <div class="oauth-sep"><span>or</span></div>
  <a class="btn-google" href="oauth-google.php?start=1&next=<?= urlencode($next) ?>">
    <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.1H42V20H24v8h11.3C33.7 32.7 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.2 8 3l5.7-5.7C34.3 6.1 29.4 4 24 4 13 4 4 13 4 24s9 20 20 20 20-9 20-20c0-1.3-.1-2.6-.4-3.9z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.9 1.2 8 3l5.7-5.7C34.3 6.1 29.4 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.1H42V20H24v8h11.3c-.8 2.3-2.3 4.3-4.1 5.7l6.2 5.2C41 35.4 44 30.2 44 24c0-1.3-.1-2.6-.4-3.9z"/></svg>
    Continue with Google
  </a>
  <?php endif; ?>

  <p class="alt">
    <?php if ($mode === 'login'): ?>
    <a href="forgot.php">Forgot password?</a> · New here? <a href="?mode=register&next=<?= urlencode($next) ?>">Join Nipange</a>
    <?php else: ?>
    Already have an account? <a href="?mode=login&next=<?= urlencode($next) ?>">Log in</a>
    <?php endif; ?>
  </p>
</div>
  <p class="auth-tag">Discover. Connect. Experience more.</p>
</main>
<?php bottom_nav(); site_footer(); ?>
