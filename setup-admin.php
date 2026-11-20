<?php
// One-time "createsuperuser" — the shared-hosting equivalent of Django's
// createsuperuser. Works ONLY while zero admins exist; permanently inert
// after the first admin is created (further admins via Admin → Users).
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$adminCount = (int) db()->query('SELECT COUNT(*) FROM users WHERE role = "admin"')->fetchColumn();
$err = '';

if ($adminCount === 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name  = trim(mb_substr($_POST['name'] ?? '', 0, 60));
    $email = trim(mb_substr($_POST['email'] ?? '', 0, 190));
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';
    $area  = trim(mb_substr($_POST['area'] ?? '', 0, 60));

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Give a name and a valid email.';
    } elseif (strlen($pass) < 8) {
        $err = 'Password needs 8+ characters.';
    } elseif ($pass !== $pass2) {
        $err = 'Passwords don\'t match.';
    } elseif (find_user_by_email($email)) {
        $err = 'That email already has an account — log in, then an existing admin can promote it from Admin → Users.';
    } else {
        db()->prepare(
            'INSERT INTO users (email, pass_hash, name, area, role) VALUES (:e, :h, :n, :a, "admin")'
        )->execute([':e' => $email, ':h' => password_hash($pass, PASSWORD_DEFAULT), ':n' => $name, ':a' => $area]);
        flash('Admin account created — log in now.');
        redirect('/auth.php');
    }
}

page_head('Admin setup');
site_header();
?>
<main>
<div class="form rv in" style="max-width:460px">
  <?php if ($adminCount > 0): ?>
    <h2>Admin already exists</h2>
    <p>This one-time setup page is disabled. Existing admins can grant the role from <strong>Admin → Users → Make admin</strong>.</p>
    <p class="alt"><a href="/auth.php">← Back to log in</a></p>
  <?php else: ?>
  <h2>Create the first admin</h2>
  <p style="color:var(--ink-soft);font-size:14px">Like Django's <code>createsuperuser</code> — this page only works while no admin exists, then disables itself.</p>
  <?php if ($err): ?><p class="status-pill warn"><?= e($err) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <div class="field"><label for="name">Your name</label>
      <input id="name" name="name" required maxlength="60" value="<?= e($_POST['name'] ?? '') ?>"></div>
    <div class="field"><label for="email">Email</label>
      <input id="email" name="email" type="email" required maxlength="190" value="<?= e($_POST['email'] ?? '') ?>"></div>
    <div class="field"><label for="area">Neighbourhood (optional)</label>
      <input id="area" name="area" maxlength="60" value="<?= e($_POST['area'] ?? '') ?>"></div>
    <div class="field"><label for="password">Password (8+ characters)</label>
      <input id="password" name="password" type="password" required minlength="8"></div>
    <div class="field"><label for="password2">Repeat password</label>
      <input id="password2" name="password2" type="password" required minlength="8"></div>
    <button class="btn-block" type="submit">Create admin</button>
  </form>
  <?php endif; ?>
</div>
</main>
<?php bottom_nav(); site_footer(); ?>
