<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_login('people.php');

$f = [
    'county'      => in_array($_GET['county'] ?? '', counties(), true) ? $_GET['county'] : '',
    'area'        => in_array($_GET['area'] ?? '', areas(), true) ? $_GET['area'] : '',
    'interest'    => isset(categories()[$_GET['interest'] ?? '']) ? $_GET['interest'] : '',
    'q'           => trim($_GET['q'] ?? ''),
    'my_interests' => array_filter(explode(',', user()['interests'] ?? '')),
];
$people = people($f, user_id());

function rel_button(array $p): string {
    $uid = (int) $p['id'];
    if ($p['is_friend']) return '<span class="status-pill going">Friends</span>';
    if ($p['rel_status'] === 'pending') {
        return (int) $p['rel_requester'] === user_id()
            ? '<form method="post" action="friend.php">' . csrf_field()
              . '<input type="hidden" name="uid" value="' . $uid . '"><input type="hidden" name="do" value="cancel">'
              . '<button class="btn-mini" type="submit">Requested ✓</button></form>'
            : '<form method="post" action="friend.php">' . csrf_field()
              . '<input type="hidden" name="uid" value="' . $uid . '"><input type="hidden" name="do" value="accept">'
              . '<button class="btn-mini solid" type="submit">Accept request</button></form>';
    }
    return '<form method="post" action="friend.php">' . csrf_field()
           . '<input type="hidden" name="uid" value="' . $uid . '"><input type="hidden" name="do" value="send">'
           . '<button class="btn-mini solid" type="submit">+ Add friend</button></form>';
}

page_head('People nearby');
site_header('people');
?>
<main>
  <h2>People nearby</h2>
  <form method="get" class="toolbar" style="justify-content:flex-start;gap:10px">
    <input name="q" type="search" placeholder="Search people" value="<?= e($f['q']) ?>" style="border:1px solid var(--line);border-radius:var(--r-input);padding:10px 13px">
    <span onchange="this.form.submit()"><?php location_picker($f['county'], $f['area'], '', 'filter'); ?></span>
    <select name="interest" onchange="this.form.submit()">
      <option value="">All interests</option>
      <?php foreach (array_keys(categories()) as $c): ?><option<?= $f['interest'] === $c ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
    </select>
    <button class="btn-mini" type="submit">Search</button>
  </form>

  <div class="grid2" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr))">
    <?php if (!$people): ?>
    <div class="empty" style="grid-column:1/-1"><span class="big">🫂</span>
      <p>No people match — try clearing filters or updating your interests in your <a href="profile.php">profile</a>.</p></div>
    <?php endif; ?>
    <?php foreach ($people as $i => $p): ?>
    <div class="person rv" style="--d:<?= min($i * 40, 400) ?>ms">
      <a href="user.php?id=<?= (int) $p['id'] ?>"><?= avatar_html($p, 'lg') ?></a>
      <a class="name" href="user.php?id=<?= (int) $p['id'] ?>"><?= e($p['name']) ?></a>
      <span class="meta"><?= e(place_label($p, 'Kenya')) ?></span>
      <?php if ($p['bio']): ?><span class="meta"><?= e(mb_strimwidth($p['bio'], 0, 80, '…')) ?></span><?php endif; ?>
      <div class="tags">
        <?php foreach ($p['shared'] as $s): ?><span class="tag shared">✓ <?= e($s) ?></span><?php endforeach; ?>
      </div>
      <div class="actions"><?= rel_button($p) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</main>
<?php bottom_nav('people'); site_footer(); ?>
