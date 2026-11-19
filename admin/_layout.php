<?php
// Admin chrome — requires require_admin() already called.

function admin_page(string $title, string $active, callable $content): void {
    page_head('Admin — ' . $title);
    site_header(); ?>
<main>
  <div class="admin-layout">
    <div class="admin-side">
      <?php foreach (['index' => '📊 Overview', 'users' => '👥 Users', 'events' => '📅 Events', 'reports' => '🚩 Reports', 'vocab' => '🏷️ Categories & areas'] as $k => $l): ?>
      <a href="<?= $k ?>.php" class="<?= $active === $k ? 'active' : '' ?>"><?= $l ?></a>
      <?php endforeach; ?>
    </div>
    <div><?php $content(); ?></div>
  </div>
</main>
<?php bottom_nav(); site_footer();
}
