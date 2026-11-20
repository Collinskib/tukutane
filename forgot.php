<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $u = find_user_by_email(trim($_POST['email'] ?? ''));
    if ($u) {
        $token = create_reset_token((int) $u['id']);
        $link = absolute_url('reset.php?token=' . $token);
        $sent = smtp_send($u['email'], 'Nipange password reset',
              "Reset your Nipange password:\n\n$link\n\nThis link expires in 1 hour.");
        if (!$sent) {
            @mail($u['email'], 'Nipange password reset',
                  "Reset your Nipange password:\n\n$link\n\nThis link expires in 1 hour.",
                  'From: no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'nipange.co.ke'));
        }
    }
    $done = true; // never reveal whether the email exists
}

page_head('Reset password');
site_header();
?>
<main>
<div class="form rv in">
  <h2>Reset your password</h2>
  <?php if ($done): ?>
    <p>If that email has an account, a reset link is on its way. Check your inbox (and spam).</p>
    <p class="alt"><a href="auth.php">← Back to log in</a></p>
  <?php else: ?>
  <form method="post">
    <?= csrf_field() ?>
    <div class="field"><label for="email">Account email</label>
      <input id="email" name="email" type="email" required autocomplete="email"></div>
    <button class="btn-block" type="submit">Send reset link</button>
  </form>
  <?php endif; ?>
</div>
</main>
<?php bottom_nav(); site_footer(); ?>
