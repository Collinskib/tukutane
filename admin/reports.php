<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
require_admin();
require_once __DIR__ . '/_layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    db()->prepare('UPDATE reports SET status="resolved" WHERE id=:i')
        ->execute([':i' => (int) ($_POST['id'] ?? 0)]);
    flash('Report resolved.');
    redirect('reports.php');
}

$reports = db()->query(
    'SELECT r.*, u.name AS reporter,
            m.body AS msg_body, m.conversation_id AS msg_conv,
            em.body AS emsg_body, em.gathering_id AS emsg_gath,
            gp.title AS gpost_title, gp.group_id AS gpost_gid,
            gc.body AS gcm_body, gc.group_id AS gcm_gid
     FROM reports r
     JOIN users u ON u.id = r.reporter_id
     LEFT JOIN messages m ON r.target_type = "message" AND m.id = r.target_id
     LEFT JOIN event_messages em ON r.target_type = "event_message" AND em.id = r.target_id
     LEFT JOIN grp_posts gp ON r.target_type = "gpost" AND gp.id = r.target_id
     LEFT JOIN grp_comments gc ON r.target_type = "gcomment" AND gc.id = r.target_id
     ORDER BY r.status = "open" DESC, r.id DESC LIMIT 200'
)->fetchAll(PDO::FETCH_ASSOC);

admin_page('Reports', 'reports', function () use ($reports) { ?>
  <h2>Reports</h2>
  <?php if (!$reports): ?><div class="empty"><span class="big">🎉</span><p>No reports — the community is healthy.</p></div><?php endif; ?>
  <table class="admin">
    <thead><tr><th>Type</th><th>Target</th><th>Reason</th><th>By</th><th>When</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($reports as $r):
        $link = match($r['target_type']) {
            'user'      => '../user.php?id=' . (int) $r['target_id'],
            'gathering' => '../event.php?id=' . (int) $r['target_id'],
            'message'   => $r['msg_conv'] ? '../messages.php?c=' . (int) $r['msg_conv'] : '#',
            'event_message' => $r['emsg_gath'] ? '../event.php?id=' . (int) $r['emsg_gath'] . '#chat' : '#',
            'gpost'     => $r['gpost_gid'] ? '../group.php?id=' . (int) $r['gpost_gid'] . '#p' . (int) $r['target_id'] : '#',
            'gcomment'  => $r['gcm_gid'] ? '../group.php?id=' . (int) $r['gcm_gid'] . '#c' . (int) $r['target_id'] : '#',
            default     => '#',
        }; ?>
    <tr>
      <td><?= e($r['target_type']) ?></td>
      <td><a href="<?= e($link) ?>">#<?= (int) $r['target_id'] ?></a>
        <?php if ($r['target_type'] === 'message' && $r['msg_body'] !== null): ?>
          <br><small><?= e(mb_strimwidth($r['msg_body'], 0, 60, '…')) ?></small><?php endif; ?>
        <?php if ($r['target_type'] === 'event_message' && $r['emsg_body'] !== null): ?>
          <br><small><?= e(mb_strimwidth($r['emsg_body'], 0, 60, '…')) ?></small><?php endif; ?>
        <?php if ($r['target_type'] === 'gpost' && $r['gpost_title'] !== null): ?>
          <br><small><?= e(mb_strimwidth($r['gpost_title'], 0, 60, '…')) ?></small><?php endif; ?>
        <?php if ($r['target_type'] === 'gcomment' && $r['gcm_body'] !== null): ?>
          <br><small><?= e(mb_strimwidth($r['gcm_body'], 0, 60, '…')) ?></small><?php endif; ?></td>
      <td><?= e($r['reason']) ?></td>
      <td><?= e($r['reporter']) ?></td>
      <td><?= e(time_ago($r['created_at'])) ?></td>
      <td><span class="status-pill <?= $r['status'] === 'resolved' ? 'going' : 'warn' ?>"><?= e($r['status']) ?></span></td>
      <td><?php if ($r['status'] === 'open'): ?>
        <form method="post" style="display:inline"><?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
          <button class="btn-mini solid" type="submit">Resolve</button></form>
      <?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php });
