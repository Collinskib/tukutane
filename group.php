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
$canModerate = $isAdmin || $isCreator;

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
            if (!$canModerate) break;
            $target = (int) ($_POST['uid'] ?? 0);
            if (group_review($gid, $target, $do === 'approve')) {
                if ($do === 'approve') {
                    notify($target, 'group_approved', 'You are in! "' . $g['name'] . '" accepted your request.', 'group.php?id=' . $gid);
                    flash('Member approved.');
                } else flash('Request declined.');
            }
            break;
        case 'remove':
            if (!$canModerate) break;
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

        // ---------- discussion ----------
        case 'post_new':
            if (!$isMember) { flash('Join the group to post.', 'err'); break; }
            $title = trim(mb_substr($_POST['title'] ?? '', 0, 160));
            $body  = trim(mb_substr($_POST['body'] ?? '', 0, 4000));
            if ($title === '') { flash('Give your post a title.', 'err'); break; }
            $pid = gpost_create($gid, $uid, $title, $body);
            foreach (group_admins($gid) as $aid) {
                if ($aid !== $uid) notify($aid, 'group_post', user()['name'] . ' posted in "' . $g['name'] . '": ' . $title, 'group.php?id=' . $gid . '#p' . $pid);
            }
            redirect('group.php?id=' . $gid . '#p' . $pid);
            break;
        case 'post_pin': case 'post_unpin':
            if (!$canModerate) break;
            $pid = (int) ($_POST['pid'] ?? 0);
            $p = gpost_get($pid);
            if ($p && (int) $p['group_id'] === $gid) {
                if ($do === 'post_pin') {
                    db()->prepare('UPDATE grp_posts SET pinned = 0 WHERE group_id = :g')->execute([':g' => $gid]);
                }
                db()->prepare('UPDATE grp_posts SET pinned = :p WHERE id = :i')
                    ->execute([':p' => $do === 'post_pin' ? 1 : 0, ':i' => $pid]);
            }
            break;
        case 'post_delete':
            $pid = (int) ($_POST['pid'] ?? 0);
            $p = gpost_get($pid);
            if ($p && (int) $p['group_id'] === $gid && ($canModerate || (int) $p['user_id'] === $uid)) {
                gpost_delete($pid);
                flash('Post removed.');
            }
            break;
        case 'comment_add':
            if (!$isMember) { flash('Join the group to comment.', 'err'); break; }
            $pid = (int) ($_POST['pid'] ?? 0);
            $p = gpost_get($pid);
            $body = trim(mb_substr($_POST['body'] ?? '', 0, 1000));
            if (!$p || (int) $p['group_id'] !== $gid || $body === '') break;
            $parent = null;
            $pp = (int) ($_POST['parent'] ?? 0);
            if ($pp) {
                $pc = gcomment_get($pp);
                // replies attach to the top-level parent (one level of nesting)
                if ($pc && (int) $pc['post_id'] === $pid) {
                    $parent = $pc['parent_id'] ? (int) $pc['parent_id'] : (int) $pc['id'];
                }
            }
            $cid = gcomment_add($pid, $gid, $uid, $parent, $body);
            if ((int) $p['user_id'] !== $uid) {
                notify((int) $p['user_id'], 'group_comment', user()['name'] . ' commented on "' . $p['title'] . '"', 'group.php?id=' . $gid . '#c' . $cid);
            }
            if ($parent) {
                $pc = gcomment_get($parent);
                if ($pc && (int) $pc['user_id'] !== $uid && (int) $pc['user_id'] !== (int) $p['user_id']) {
                    notify((int) $pc['user_id'], 'group_reply', user()['name'] . ' replied to your comment in "' . $g['name'] . '"', 'group.php?id=' . $gid . '#c' . $cid);
                }
            }
            redirect('group.php?id=' . $gid . '#c' . $cid);
            break;
        case 'comment_delete':
            $cid = (int) ($_POST['cid'] ?? 0);
            $cm = gcomment_get($cid);
            if ($cm && (int) $cm['group_id'] === $gid && ($canModerate || (int) $cm['user_id'] === $uid)) {
                gcomment_delete($cid);
            }
            redirect('group.php?id=' . $gid . '#p' . (int) ($cm['post_id'] ?? 0));
            break;
    }
    redirect('group.php?id=' . $gid);
}

$members  = group_members($gid, 'active');
$pending  = $canModerate ? group_members($gid, 'pending') : [];
$sort     = ($_GET['sort'] ?? '') === 'new' ? 'new' : 'hot';
$posts    = gposts_list($gid, $uid, $sort);

page_head($g['name'] . ' — Groups');
site_header('groups');
?>
<main>
  <p style="margin:0 0 8px"><a href="groups.php" style="color:var(--ink-soft);text-decoration:none">← All groups</a></p>
  <div class="card rv in" style="padding:24px">
    <div class="thread-head" style="flex-wrap:wrap;gap:10px">
      <div class="who" style="flex:1;min-width:200px">
        <h2 style="margin:0"><?= e($g['name']) ?></h2>
        <small><?= e($g['category']) ?> · <?= e(place_label($g)) ?> · <?= (int) $g['members'] ?> member<?= (int) $g['members'] === 1 ? '' : 's' ?><?= $g['join_policy'] === 'approval' ? ' · join by approval' : ' · open' ?></small>
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
      <a class="who" href="user.php?id=<?= (int) $m['user_id'] ?>" style="text-decoration:none;color:inherit"><b><?= e($m['name']) ?></b><small><?= e(place_label($m, 'Kenya')) ?></small></a>
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

  <div class="gtabs">
    <a class="<?= $sort === 'hot' ? 'on' : '' ?>" href="group.php?id=<?= $gid ?>">Top posts</a>
    <a class="<?= $sort === 'new' ? 'on' : '' ?>" href="group.php?id=<?= $gid ?>&sort=new">Newest</a>
  </div>

  <?php if ($isMember): ?>
  <details class="card gcomposer" id="composer">
    <summary><span class="gcompose-hint">✎ Start a post in <?= e($g['name']) ?>…</span></summary>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="do" value="post_new">
      <input name="title" required maxlength="160" placeholder="Title — what's on your mind?">
      <textarea name="body" rows="3" maxlength="4000" placeholder="Add details (optional)"></textarea>
      <button class="primary" type="submit">Post to group</button>
    </form>
  </details>
  <?php elseif (!$isPending): ?>
  <div class="card gcomposer-nudge"><p>Join <?= e($g['name']) ?> to post, vote and comment.</p></div>
  <?php endif; ?>

  <?php if (!$posts): ?>
  <div class="empty"><span class="big">💬</span><p>No posts yet — start the conversation.</p></div>
  <?php endif; ?>

  <?php foreach ($posts as $p):
    $comments = gcomments_list((int) $p['id'], $uid);
    $roots = array_values(array_filter($comments, fn($c) => !$c['parent_id']));
    $kids  = [];
    foreach ($comments as $c) {
        if ($c['parent_id']) $kids[(int) $c['parent_id']][] = $c;
    }
    $myVote = (int) ($p['my_vote'] ?? 0);
  ?>
  <article class="gpost card" id="p<?= (int) $p['id'] ?>">
    <div class="gvote<?= $isMember ? '' : ' ro' ?>" data-kind="post" data-id="<?= (int) $p['id'] ?>">
      <button class="vup<?= $myVote === 1 ? ' on' : '' ?>" type="button" aria-label="Upvote" <?= $isMember ? '' : 'disabled' ?>>▲</button>
      <b class="vscore"><?= (int) $p['score'] ?></b>
      <button class="vdn<?= $myVote === -1 ? ' on' : '' ?>" type="button" aria-label="Downvote" <?= $isMember ? '' : 'disabled' ?>>▼</button>
    </div>
    <div class="gpost-main">
      <div class="gpost-meta">
        <?= $p['pinned'] ? '<span class="status-pill going">📌 Pinned</span>' : '' ?>
        <a href="user.php?id=<?= (int) $p['user_id'] ?>"><?= e($p['author_name']) ?></a> · <?= e(time_ago($p['created_at'])) ?> ago
      </div>
      <h3><?= e($p['title']) ?></h3>
      <?php if ($p['body']): ?><p class="gpost-body"><?= nl2br(e($p['body'])) ?></p><?php endif; ?>
      <div class="gpost-acts">
        <?php if ($canModerate): ?>
        <form method="post" style="display:inline"><?= csrf_field() ?>
          <input type="hidden" name="do" value="<?= $p['pinned'] ? 'post_unpin' : 'post_pin' ?>"><input type="hidden" name="pid" value="<?= (int) $p['id'] ?>">
          <button class="gact" type="submit"><?= $p['pinned'] ? 'Unpin' : 'Pin' ?></button></form>
        <?php endif; ?>
        <?php if ($canModerate || (int) $p['user_id'] === $uid): ?>
        <form method="post" style="display:inline" onsubmit="return confirm('Delete this post?')"><?= csrf_field() ?>
          <input type="hidden" name="do" value="post_delete"><input type="hidden" name="pid" value="<?= (int) $p['id'] ?>">
          <button class="gact danger" type="submit">Delete</button></form>
        <?php endif; ?>
      </div>

      <details class="gthread">
        <summary>💬 <?= (int) $p['comment_count'] ?> comment<?= (int) $p['comment_count'] === 1 ? '' : 's' ?></summary>
        <?php if ($isMember): ?>
        <form method="post" class="gcform">
          <?= csrf_field() ?>
          <input type="hidden" name="do" value="comment_add"><input type="hidden" name="pid" value="<?= (int) $p['id'] ?>">
          <input name="body" required maxlength="1000" placeholder="Write a comment…" autocomplete="off">
          <button class="btn-mini solid" type="submit">Comment</button>
        </form>
        <?php endif; ?>

        <?php foreach ($roots as $c):
            $cvote = (int) ($c['my_vote'] ?? 0);
            $creplies = $kids[(int) $c['id']] ?? [];
        ?>
        <div class="gcm" id="c<?= (int) $c['id'] ?>">
          <div class="gvote sm<?= $isMember ? '' : ' ro' ?>" data-kind="comment" data-id="<?= (int) $c['id'] ?>">
            <button class="vup<?= $cvote === 1 ? ' on' : '' ?>" type="button" aria-label="Upvote" <?= $isMember ? '' : 'disabled' ?>>▲</button>
            <b class="vscore"><?= (int) $c['score'] ?></b>
            <button class="vdn<?= $cvote === -1 ? ' on' : '' ?>" type="button" aria-label="Downvote" <?= $isMember ? '' : 'disabled' ?>>▼</button>
          </div>
          <div class="gcm-main">
            <div class="gpost-meta">
              <a href="user.php?id=<?= (int) $c['user_id'] ?>"><?= e($c['deleted'] ? 'removed' : $c['author_name']) ?></a> · <?= e(time_ago($c['created_at'])) ?> ago
            </div>
            <?php if ($c['deleted']): ?>
            <p class="gcm-body removed">[removed]</p>
            <?php else: ?>
            <p class="gcm-body"><?= nl2br(e($c['body'])) ?></p>
            <div class="gpost-acts">
              <?php if ($isMember): ?>
              <button class="gact greply" type="button" data-cid="<?= (int) $c['id'] ?>">Reply</button>
              <?php endif; ?>
              <?php if ($canModerate || (int) $c['user_id'] === $uid): ?>
              <form method="post" style="display:inline" onsubmit="return confirm('Remove this comment?')"><?= csrf_field() ?>
                <input type="hidden" name="do" value="comment_delete"><input type="hidden" name="cid" value="<?= (int) $c['id'] ?>">
                <button class="gact danger" type="submit">Delete</button></form>
              <?php endif; ?>
            </div>
            <form method="post" class="gcform greply-form" id="rf<?= (int) $c['id'] ?>" hidden>
              <?= csrf_field() ?>
              <input type="hidden" name="do" value="comment_add"><input type="hidden" name="pid" value="<?= (int) $p['id'] ?>">
              <input type="hidden" name="parent" value="<?= (int) $c['id'] ?>">
              <input name="body" required maxlength="1000" placeholder="Reply to <?= e($c['author_name']) ?>…" autocomplete="off">
              <button class="btn-mini solid" type="submit">Reply</button>
            </form>
            <?php endif; ?>

            <?php foreach ($creplies as $r):
                $rvote = (int) ($r['my_vote'] ?? 0);
            ?>
            <div class="gcm reply" id="c<?= (int) $r['id'] ?>">
              <div class="gvote sm<?= $isMember ? '' : ' ro' ?>" data-kind="comment" data-id="<?= (int) $r['id'] ?>">
                <button class="vup<?= $rvote === 1 ? ' on' : '' ?>" type="button" aria-label="Upvote" <?= $isMember ? '' : 'disabled' ?>>▲</button>
                <b class="vscore"><?= (int) $r['score'] ?></b>
                <button class="vdn<?= $rvote === -1 ? ' on' : '' ?>" type="button" aria-label="Downvote" <?= $isMember ? '' : 'disabled' ?>>▼</button>
              </div>
              <div class="gcm-main">
                <div class="gpost-meta">
                  <a href="user.php?id=<?= (int) $r['user_id'] ?>"><?= e($r['deleted'] ? 'removed' : $r['author_name']) ?></a> · <?= e(time_ago($r['created_at'])) ?> ago
                </div>
                <?php if ($r['deleted']): ?>
                <p class="gcm-body removed">[removed]</p>
                <?php else: ?>
                <p class="gcm-body"><?= nl2br(e($r['body'])) ?></p>
                <?php if ($canModerate || (int) $r['user_id'] === $uid): ?>
                <div class="gpost-acts">
                  <form method="post" style="display:inline" onsubmit="return confirm('Remove this comment?')"><?= csrf_field() ?>
                    <input type="hidden" name="do" value="comment_delete"><input type="hidden" name="cid" value="<?= (int) $r['id'] ?>">
                    <button class="gact danger" type="submit">Delete</button></form>
                </div>
                <?php endif; ?>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </details>
    </div>
  </article>
  <?php endforeach; ?>

  <h3 style="margin-top:28px">Members</h3>
  <div class="card" style="padding:8px 0">
    <?php foreach ($members as $m): ?>
    <div class="member-row">
      <a href="user.php?id=<?= (int) $m['user_id'] ?>"><?= avatar_html($m, 'sm') ?></a>
      <a class="who" href="user.php?id=<?= (int) $m['user_id'] ?>" style="text-decoration:none;color:inherit">
        <b><?= e($m['name']) ?></b>
        <small><?= $m['role'] === 'admin' ? 'Admin · ' : '' ?><?= e(place_label($m, 'Kenya')) ?></small></a>
      <?php if ($canModerate && (int) $m['user_id'] !== $uid && $m['role'] !== 'admin'): ?>
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
<script>
const GCSRF = <?= json_encode(csrf_token()) ?>;
document.addEventListener('click', function (ev) {
  const rb = ev.target.closest('.greply');
  if (rb) {
    const f = document.getElementById('rf' + rb.dataset.cid);
    if (f) { f.hidden = !f.hidden; if (!f.hidden) f.querySelector('input[name=body]').focus(); }
    return;
  }
  const vb = ev.target.closest('.gvote button');
  if (!vb || vb.disabled) return;
  const box = vb.closest('.gvote');
  const fd = new FormData();
  fd.append('csrf', GCSRF);
  fd.append('kind', box.dataset.kind);
  fd.append('id', box.dataset.id);
  fd.append('dir', vb.classList.contains('vup') ? '1' : '-1');
  fetch('/api/group_vote.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } })
    .then(r => r.json()).then(j => {
      if (!j.ok) return;
      box.querySelector('.vscore').textContent = j.score;
      box.querySelector('.vup').classList.toggle('on', j.mine === 1);
      box.querySelector('.vdn').classList.toggle('on', j.mine === -1);
    }).catch(() => {});
});
</script>
<?php bottom_nav('groups'); site_footer(); ?>
