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
    'my_county'    => user()['county'] ?? '',
    'my_area'      => user()['area'] ?? '',
    'my_estate'    => user()['estate'] ?? '',
];
$tab      = in_array($_GET['tab'] ?? '', ['friends', 'requests'], true) ? $_GET['tab'] : 'add';
$uid      = user_id();
$people   = people($f, $uid);
$incoming = pending_requests($uid);
$outgoing = outgoing_requests($uid);
$friends  = my_friends($uid);

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
  <nav class="homepills" aria-label="Explore">
    <a class="on" href="/people.php">🧑‍🤝‍🧑 People</a>
    <a href="/create.php">📅 Create Event</a>
    <a href="/venues.php">🏪 Business</a>
  </nav>

  <div class="tabs" style="border:0;margin:0 0 16px;display:inline-flex">
    <a href="people.php?tab=friends" class="<?= $tab === 'friends' ? 'active' : '' ?>">Friends</a>
    <a href="people.php" class="<?= $tab === 'add' ? 'active' : '' ?>">Add friends</a>
    <a href="people.php?tab=requests" class="<?= $tab === 'requests' ? 'active' : '' ?>">Requests<?= count($incoming) ? ' (' . count($incoming) . ')' : '' ?></a>
  </div>

  <?php if ($tab === 'friends'): ?>
  <section class="panel rv in">
    <?php if (!$friends): ?>
    <div class="empty"><span class="big">🧑‍🤝‍🧑</span><p>No friends yet — browse <a href="people.php">Add friends</a> to find your people.</p></div>
    <?php endif; ?>
    <?php foreach ($friends as $fr): ?>
    <div style="display:flex;gap:12px;align-items:center;padding:11px 16px;border-top:1px solid var(--line-soft)">
      <a href="user.php?id=<?= (int) $fr['id'] ?>"><?= avatar_html($fr) ?></a>
      <div style="flex:1"><a href="user.php?id=<?= (int) $fr['id'] ?>" style="color:inherit;text-decoration:none"><b><?= e($fr['name']) ?></b></a><br>
        <small style="color:var(--ink-soft)"><?= e(place_label($fr, 'Kenya')) ?></small></div>
      <a class="btn-mini solid" href="messages.php?to=<?= (int) $fr['id'] ?>">💬 Message</a>
      <form method="post" action="friend.php"><?= csrf_field() ?>
        <input type="hidden" name="uid" value="<?= (int) $fr['id'] ?>">
        <input type="hidden" name="do" value="remove"><button class="btn-mini" type="submit">Remove</button></form>
    </div>
    <?php endforeach; ?>
  </section>

  <?php elseif ($tab === 'requests'): ?>
  <section class="panel rv in" style="margin-bottom:16px">
    <h3 style="font-family:var(--serif);font-weight:400;padding:14px 16px 4px">Waiting on you (<?= count($incoming) ?>)</h3>
    <?php if (!$incoming): ?>
    <div class="empty"><span class="big">📭</span><p>No requests right now.</p></div>
    <?php endif; ?>
    <?php foreach ($incoming as $r): ?>
    <div style="display:flex;gap:12px;align-items:center;padding:11px 16px;border-top:1px solid var(--line-soft)">
      <a href="user.php?id=<?= (int) $r['requester_id'] ?>"><?= avatar_html($r) ?></a>
      <div style="flex:1;min-width:0"><b><?= e($r['name']) ?></b><br>
        <small style="color:var(--ink-soft)"><?= e(place_label($r, 'Kenya')) ?></small></div>
      <form method="post" action="friend.php"><?= csrf_field() ?>
        <input type="hidden" name="uid" value="<?= (int) $r['requester_id'] ?>">
        <input type="hidden" name="do" value="accept"><button class="btn-mini solid" type="submit">Accept</button></form>
      <form method="post" action="friend.php"><?= csrf_field() ?>
        <input type="hidden" name="uid" value="<?= (int) $r['requester_id'] ?>">
        <input type="hidden" name="do" value="decline"><button class="btn-mini" type="submit">Decline</button></form>
    </div>
    <?php endforeach; ?>
  </section>
  <?php if ($outgoing): ?>
  <section class="panel rv in">
    <h3 style="font-family:var(--serif);font-weight:400;padding:14px 16px 4px">Sent by you (<?= count($outgoing) ?>)</h3>
    <?php foreach ($outgoing as $r): ?>
    <div style="display:flex;gap:12px;align-items:center;padding:11px 16px;border-top:1px solid var(--line-soft)">
      <a href="user.php?id=<?= (int) $r['addressee_id'] ?>"><?= avatar_html($r) ?></a>
      <div style="flex:1"><b><?= e($r['name']) ?></b><br><small style="color:var(--ink-soft)">pending</small></div>
      <form method="post" action="friend.php"><?= csrf_field() ?>
        <input type="hidden" name="uid" value="<?= (int) $r['addressee_id'] ?>">
        <input type="hidden" name="do" value="cancel"><button class="btn-mini" type="submit">Cancel</button></form>
    </div>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

  <?php else: ?>
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

  <div class="plist">
    <?php if (!$people): ?>
    <div class="empty"><span class="big">🫂</span>
      <p>No people match — try clearing filters or updating your interests in your <a href="profile.php">profile</a>.</p></div>
    <?php endif; ?>
    <?php foreach ($people as $i => $p): ?>
    <?php
      $quote = trim($p['fun_fact'] ?? '') ?: trim($p['bio'] ?? '');
      [, $pc] = categories()[$p['shared'][0] ?? ''] ?? ['', 'var(--line)'];
    ?>
    <div class="person rv" style="--d:<?= min($i * 40, 400) ?>ms;--pc:<?= e($pc) ?>">
      <a class="pcov" href="user.php?id=<?= (int) $p['id'] ?>">
        <?php if (!empty($p['photo'])): ?>
        <img src="<?= img_url($p['photo'], 480) ?>" alt="<?= e($p['name']) ?>" loading="lazy" decoding="async">
        <?php else: ?>
        <span class="pcov-ph"><?= e(mb_strtoupper(mb_substr($p['name'], 0, 1))) ?></span>
        <?php endif; ?>
      </a>
      <div class="pinfo">
        <a class="name" href="user.php?id=<?= (int) $p['id'] ?>"><?= e($p['name']) ?></a>
        <span class="meta">📍 <?= e(place_label($p, 'Kenya')) ?><?php
          $demo = array_filter([in_array($p['gender'] ?? '', ['Male', 'Female'], true) ? $p['gender'] : '']);
          if ($demo) echo ' · ' . e(implode(' · ', $demo));
        ?></span>
        <?php if ($quote): ?><p class="qt"><?= e(mb_strimwidth($quote, 0, 70, '…')) ?></p><?php endif; ?>
        <?php if ($p['shared']): ?>
        <div class="tags">
          <?php foreach (array_slice($p['shared'], 0, 2) as $s): ?><span class="tag shared">✓ <?= e($s) ?></span><?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <div class="actions"><?= rel_button($p) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>
<?php bottom_nav('people'); site_footer(); ?>
