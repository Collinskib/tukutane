<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $uid = consume_reset_token($token);
    if (!$uid) {
        $err = 'That link is expired or already used — request a new one.';
    } elseif (mb_strlen($_POST['password'] ?? '') < 8) {
        $err = 'Password needs at least 8 characters.';
    } else {
        db()->prepare('UPDATE users SET pass_hash = :p WHERE id = :u')
            ->execute([':p' => password_hash($_POST['password'], PASSWORD_DEFAULT), ':u' => $uid]);
        flash('Password updated — log in with the new one.');
        redirect('auth.php');
    }
}

page_head('Choose a new password');
site_header();
?>
<main>
<div class="form rv in">
  <h2>New password</h2>
  <?php if ($err): ?><p style="color:#a33a1f"><?= e($err) ?></p><?php endif; ?>
  <?php if (!$err): ?>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <div class="field"><label for="password">New password (8+ characters)</label>
      <div class="pw-wrap">
        <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">
        <button type="button" class="peek">Show</button>
      </div></div>
    <button class="btn-block" type="submit">Save password</button>
  </form>
  <?php else: ?>
  <p class="alt"><a href="forgot.php">Request a new link</a></p>
  <?php endif; ?>
</div>
</main>
<?php bottom_nav(); site_footer(); ?>
