<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

require_login();
$u = user();

$next = $_GET['next'] ?? 'index.php';
if (!preg_match('~^[a-z0-9_./?=&#%-]+$~i', $next) || str_starts_with($next, '//')) $next = 'index.php';

page_head('Karibu, ' . $u['name']);
site_header();
?>
<main>
<div class="form rv in" style="max-width:520px;text-align:center">
  <span style="font-size:46px">🎉</span>
  <h2 style="font-family:var(--serif);font-weight:400;margin:8px 0 6px">Karibu, <?= e(explode(' ', $u['name'])[0]) ?>!</h2>
  <p style="color:var(--ink-soft);margin:0 0 22px">You're in. What would you like to do first?</p>

  <a href="/create.php" class="wel" style="background:var(--green-1);color:#fff">
    <span style="font-size:30px">📅</span>
    <span style="text-align:left">
      <b>Plan an event</b><br>
      <small style="opacity:.85">Coffee meetups, hikes, game nights — gather your people.</small>
    </span>
  </a>
  <a href="/venue-edit.php" class="wel" style="background:var(--accent);color:#fff">
    <span style="font-size:30px">🏪</span>
    <span style="text-align:left">
      <b>List your business</b><br>
      <small style="opacity:.85">Put your café, shop or studio on the map — free.</small>
    </span>
  </a>

  <a href="<?= e($next) ?>" style="display:inline-block;margin-top:18px;color:var(--ink-soft);font-size:14px">Skip for now →</a>
</div>
</main>
<style>
  .wel{display:flex;gap:14px;align-items:center;padding:16px 18px;border-radius:var(--r-card);text-decoration:none;margin-bottom:12px;box-shadow:var(--shadow);transition:transform .18s ease}
  .wel:hover{transform:translateY(-2px)}
  .wel b{font-family:var(--serif);font-size:19px;font-weight:400}
</style>
<?php site_footer(); ?>
