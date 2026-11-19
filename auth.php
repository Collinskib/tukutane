<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$mode = ($_GET['mode'] ?? 'login') === 'register' ? 'register' : 'login';
$next = $_GET['next'] ?? 'index.php';
if (!preg_match('~^[a-z0-9_./?=&#%-]+$~i', $next) || str_starts_with($next, '//')) $next = 'index.php';

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $next = $_POST['next'] ?? $next;
    if (($_POST['mode'] ?? '') === 'register') {
        $mode = 'register';
        $err = register_user(
            trim($_POST['email'] ?? ''),
            $_POST['password'] ?? '',
            trim($_POST['name'] ?? ''),
            $_POST['area'] ?? ''
        );
    } else {
        $err = attempt_login(trim($_POST['email'] ?? ''), $_POST['password'] ?? '');
    }
    if (!$err) {
        flash($mode === 'register' ? 'Karibu! Your account is ready.' : 'Welcome back!');
        redirect($next);
    }
}

page_head($mode === 'register' ? 'Join Nipange' : 'Log in');
site_header();
?>
<main>
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

    <?php if ($mode === 'register'): ?>
    <div class="field"><label for="area">Your neighbourhood (optional)</label>
      <select id="area" name="area">
        <option value="">Choose…</option>
        <?php foreach (areas() as $a): ?>
        <option<?= ($_POST['area'] ?? '') === $a ? ' selected' : '' ?>><?= e($a) ?></option>
        <?php endforeach; ?>
      </select></div>
    <?php endif; ?>

    <button class="btn-block" type="submit"><?= $mode === 'register' ? 'Create account' : 'Log in' ?></button>
  </form>

  <p class="alt">
    <?php if ($mode === 'login'): ?>
    <a href="forgot.php">Forgot password?</a> · New here? <a href="?mode=register&next=<?= urlencode($next) ?>">Join Nipange</a>
    <?php else: ?>
    Already have an account? <a href="?mode=login&next=<?= urlencode($next) ?>">Log in</a>
    <?php endif; ?>
  </p>
</div>
</main>
<?php bottom_nav(); site_footer(); ?>
