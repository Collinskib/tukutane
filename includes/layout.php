<?php
// Shared chrome: <head>, top nav, mobile bottom nav, footer.

function page_head(string $title): void {
    $unread = user() ? unread_notifications(user_id()) : 0;
    $unreadMsg = user() ? unread_messages(user_id()) : 0; ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#163e31">
<meta name="description" content="Nipange — find gatherings, meetups and new friends near you in Nairobi.">
<title><?= e($title) ?> · Nipange</title>
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" href="/assets/icons/icon-192.png">
<link rel="apple-touch-icon" href="/assets/icons/icon-192.png">
<link rel="stylesheet" href="/assets/style.css?v=6">
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
<script defer src="/assets/app.js?v=6"></script>
</head>
<body data-unread="<?= $unread ?>" data-unread-msg="<?= $unreadMsg ?>" data-signed-in="<?= user() ? '1' : '0' ?>">
<?php }

function site_header(string $active = ''): void {
    $u = user();
    [$fmsg, $fkind] = flash() ?: [null, 'ok']; ?>
<header class="site">
  <nav>
    <a class="brand" href="/index.php">Nipange<i>.</i></a>
    <a class="btn nav-link <?= $active === 'discover' ? 'active' : '' ?>" href="/index.php">Discover</a>
    <a class="btn nav-link <?= $active === 'people' ? 'active' : '' ?>" href="/people.php">People</a>
    <?php if ($u): ?>
    <a class="btn nav-link <?= $active === 'plans' ? 'active' : '' ?>" href="/plans.php">My plans</a>
    <a class="primary nav-create" href="/create.php">+ Create plan</a>
    <span class="nav-right" x-data="{open:false}">
      <a class="icon-btn" href="/plans.php?tab=notifications" aria-label="Notifications" id="bell">
        🔔<span class="badge" id="bell-badge" hidden>0</span>
      </a>
      <button class="avatar-btn" @click="open=!open" aria-label="Account menu">
        <?= avatar_html($u, 'sm') ?>
      </button>
      <span class="dropdown" x-show="open" x-transition @click.outside="open=false" x-cloak>
        <a href="/profile.php">My profile</a>
        <a href="/friends.php">Friends</a>
        <a href="/messages.php">Messages <span class="badge soft" id="msg-badge" hidden></span></a>
        <?php if (is_admin()): ?><a href="/admin/index.php">Admin</a><?php endif; ?>
        <a href="/logout.php">Log out</a>
      </span>
    </span>
    <?php else: ?>
    <span class="nav-right">
      <a class="btn" href="/auth.php">Log in</a>
      <a class="primary nav-join" href="/auth.php?mode=register">Join Nipange</a>
    </span>
    <?php endif; ?>
  </nav>
</header>
<?php if ($fmsg): ?>
<div class="toast <?= e($fkind) ?>" x-data="{show:true}" x-show="show" x-init="setTimeout(()=>show=false,4500)" x-transition><?= e($fmsg) ?></div>
<?php endif; ?>
<?php }

function bottom_nav(string $active = ''): void {
    $u = user(); ?>
<nav class="bottomnav" aria-label="Main">
  <a href="/index.php" class="<?= $active === 'discover' ? 'active' : '' ?>">
    <span>🔎</span>Discover</a>
  <a href="/people.php" class="<?= $active === 'people' ? 'active' : '' ?>">
    <span>🧑‍🤝‍🧑</span>People</a>
  <a href="/create.php" class="fab" aria-label="Create a plan"><span>＋</span></a>
  <a href="/messages.php" class="<?= $active === 'messages' ? 'active' : '' ?>">
    <span>💬</span>Chats<i class="badge dot" id="msg-dot" hidden></i></a>
  <a href="<?= $u ? '/plans.php' : '/auth.php' ?>" class="<?= $active === 'plans' ? 'active' : '' ?>">
    <span><?= $u ? '📅' : '👤' ?></span><?= $u ? 'Plans' : 'Join' ?></a>
</nav>
<?php }

function site_footer(): void { ?>
<footer class="site">
  <div class="foot-grid">
    <div><span class="brand sm">Nipange<i>.</i></span>
      <p>Find gatherings and new friends near you.</p></div>
    <div><strong>Explore</strong>
      <a href="/index.php">Gatherings</a><a href="/people.php">People nearby</a><a href="/create.php">Create a plan</a></div>
    <div><strong>Account</strong>
      <?php if (user()): ?><a href="/profile.php">Profile</a><a href="/plans.php">My plans</a><a href="/logout.php">Log out</a>
      <?php else: ?><a href="/auth.php">Log in</a><a href="/auth.php?mode=register">Join Nipange</a><?php endif; ?></div>
  </div>
  <p class="fine">Made for Nairobi · Nipange <?= date('Y') ?></p>
</footer>
<script>if('serviceWorker' in navigator)navigator.serviceWorker.register('/sw.js').catch(()=>{})</script>
</body>
</html>
<?php }
