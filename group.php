<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_login('groups.php');

$uid = user_id();
$g = group_get((int) ($_GET['id'] ?? 0));
if (!$g) { flash('Group not found.', 'err'); redirect('groups.php'); }
$gid = (int) $g['id'];

$me = group_membership($gid, $uid);
$isMember  = $me && $me['status'] === 'active';
$isPending = $me && $me['status'] === 'pending';
$isAdmin   = $isMember && $me['role'] === 'admin';
$isCreator = (int) $g['creator_id'] === $uid;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $do = $_POST['do'] ?? '';
    switch ($do) {
        case 'join':
            $r = group_join($gid, $uid, $g['join_policy']);
            if ($r === 'requested' || $r === 'rerequested') {
                foreach (group_admins($gid) as $aid) {
                    if ($aid !== $uid) notify($aid, 'group_request', user()['name'] . ' asked to join "' . $g['name'] . '".', 'group.php?id=' . $gid);
                }
                flash('Request sent — the group admin will review it.');
            } elseif ($r) flash('Karibu — you joined "' . $g['name'] . '"!');
            else flash('You are already in this group.');
            break;
        case 'cancel_request':
            if ($isPending) {
                db()->prepare('DELETE FROM grp_members WHERE group_id = :g AND user_id = :u')
                    ->execute([':g' => $gid, ':u' => $uid]);
                flash('Request withdrawn.');
            }
            break;
        case 'leave':
            if (group_leave($gid, $uid)) flash('You left "' . $g['name'] . '".');
            else flash('You are the only admin — delete the group or make someone else an admin first.', 'err');
            break;
        case 'approve': case 'decline':
            if (!$isAdmin && !$isCreator) break;
            $target = (int) ($_POST['uid'] ?? 0);
            if (group_review($gid, $target, $do === 'approve')) {
                if ($do === 'approve') {
                    notify($target, 'group_approved', 'You are in! "' . $g['name'] . '" accepted your request.', 'group.php?id=' . $gid);
                    flash('Member approved.');
                } else flash('Request declined.');
            }
            break;
        case 'remove':
            if (!$isAdmin && !$isCreator) break;
            $target = (int) ($_POST['uid'] ?? 0);
            if ($target !== $uid && group_remove_member($gid, $target)) flash('Member removed.');
            break;
        case 'delete':
            if ($isCreator) {
                group_delete($gid);
                flash('Group deleted.');
                redirect('groups.php');
            }
            break;
    }
    redirect('group.php?id=' . $gid);
}

$members  = group_members($gid, 'active');
$pending  = ($isAdmin || $isCreator) ? group_members($gid, 'pending') : [];

page_head($g['name'] . ' — Groups');
site_header('groups');
?>
<main>
  <p style="margin:0 0 8px"><a href="groups.php" style="color:var(--ink-soft);text-decoration:none">← All groups</a></p>
  <div class="card rv in" style="padding:24px">
    <div class="thread-head" style="flex-wrap:wrap;gap:10px">
      <div class="who" style="flex:1;min-width:200px">
        <h2 style="margin:0"><?= e($g['name']) ?></h2>
        <small><?= e($g['category']) ?> · <?= e($g['area']) ?> · <?= (int) $g['members'] ?> member<?= (int) $g['members'] === 1 ? '' : 's' ?><?= $g['join_policy'] === 'approval' ? ' · join by approval' : ' · open' ?></small>
      </div>
      <?php if ($isCreator || $isAdmin): ?>
      <a class="btn-mini" href="group-edit.php?id=<?= $gid ?>">Edit</a>
      <?php endif; ?>
      <?php if (!$me || $me['status'] === 'declined'): ?>
      <form method="post" style="display:inline"><?= csrf_field() ?>
        <input type="hidden" name="do" value="join">
        <button class="primary" type="submit"><?= $g['join_policy'] === 'open' ? 'Join group' : 'Request to join' ?></button></form>
      <?php elseif ($isPending): ?>
      <span class="status-pill">Request pending</span>
      <form method="post" style="display:inline"><?= csrf_field() ?>
        <input type="hidden" name="do" value="cancel_request">
        <button class="btn-mini" type="submit">Withdraw</button></form>
      <?php elseif ($isMember && !$isCreator): ?>
      <form method="post" style="display:inline"><?= csrf_field() ?>
        <input type="hidden" name="do" value="leave">
        <button class="btn-mini" type="submit">Leave group</button></form>
      <?php endif; ?>
    </div>
    <?php if ($g['description']): ?><p style="color:var(--ink-soft);max-width:60ch"><?= nl2br(e($g['description'])) ?></p><?php endif; ?>
    <p class="fine">Started by <a href="user.php?id=<?= (int) $g['creator_id'] ?>"><?= e($g['creator_name']) ?></a></p>
  </div>

  <?php if ($pending): ?>
  <h3>Join requests (<?= count($pending) ?>)</h3>
  <div class="card" style="padding:8px 0">
    <?php foreach ($pending as $m): ?>
    <div class="member-row">
      <a href="user.php?id=<?= (int) $m['user_id'] ?>"><?= avatar_html($m, 'sm') ?></a>
      <a class="who" href="user.php?id=<?= (int) $m['user_id'] ?>" style="text-decoration:none;color:inherit"><b><?= e($m['name']) ?></b><small><?= e($m['area'] ?: 'Nairobi') ?></small></a>
      <form method="post" style="display:inline"><?= csrf_field() ?>
        <input type="hidden" name="do" value="approve"><input type="hidden" name="uid" value="<?= (int) $m['user_id'] ?>">
        <button class="btn-mini solid" type="submit">Approve</button></form>
      <form method="post" style="display:inline"><?= csrf_field() ?>
        <input type="hidden" name="do" value="decline"><input type="hidden" name="uid" value="<?= (int) $m['user_id'] ?>">
        <button class="btn-mini" type="submit">Decline</button></form>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <h3>Members</h3>
  <div class="card" style="padding:8px 0">
    <?php foreach ($members as $m): ?>
    <div class="member-row">
      <a href="user.php?id=<?= (int) $m['user_id'] ?>"><?= avatar_html($m, 'sm') ?></a>
      <a class="who" href="user.php?id=<?= (int) $m['user_id'] ?>" style="text-decoration:none;color:inherit">
        <b><?= e($m['name']) ?></b>
        <small><?= $m['role'] === 'admin' ? 'Admin · ' : '' ?><?= e($m['area'] ?: 'Nairobi') ?></small></a>
      <?php if (($isAdmin || $isCreator) && (int) $m['user_id'] !== $uid && $m['role'] !== 'admin'): ?>
      <form method="post" style="display:inline;margin-left:auto"><?= csrf_field() ?>
        <input type="hidden" name="do" value="remove"><input type="hidden" name="uid" value="<?= (int) $m['user_id'] ?>">
        <button class="btn-mini" type="submit">Remove</button></form>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if ($isCreator): ?>
  <form method="post" style="margin-top:24px" onsubmit="return confirm('Delete this group for everyone?')"><?= csrf_field() ?>
    <input type="hidden" name="do" value="delete">
    <button class="btn-mini warn" type="submit">Delete group</button></form>
  <?php endif; ?>
</main>
<?php bottom_nav('groups'); site_footer(); ?>
