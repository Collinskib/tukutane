<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
require_admin();
require_once __DIR__ . '/_layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int) ($_POST['id'] ?? 0);
    switch ($_POST['do'] ?? '') {
        case 'feature':
            db()->prepare('UPDATE gatherings SET featured = 1 - featured WHERE id = :i')->execute([':i' => $id]);
            flash('Toggled featured.'); break;
        case 'cancel':
            db()->prepare('UPDATE gatherings SET status="cancelled" WHERE id=:i')->execute([':i' => $id]);
            flash('Event cancelled.'); break;
        case 'restore':
            db()->prepare('UPDATE gatherings SET status="active" WHERE id=:i')->execute([':i' => $id]);
            flash('Event restored.'); break;
    }
    redirect('events.php');
}

$events = db()->query(
    'SELECT g.*, u.name AS host_name,
            (SELECT COUNT(*) FROM rsvps r WHERE r.gathering_id = g.id) AS rsvps
     FROM gatherings g LEFT JOIN users u ON u.id = g.creator_id
     ORDER BY g.event_at DESC LIMIT 200'
)->fetchAll(PDO::FETCH_ASSOC);

admin_page('Events', 'events', function () use ($events) { ?>
  <h2>Events</h2>
  <table class="admin">
    <thead><tr><th>Event</th><th>Host</th><th>When</th><th>Area</th><th>RSVPs</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($events as $ev): ?>
    <tr>
      <td><a href="../event.php?id=<?= (int) $ev['id'] ?>"><?= e($ev['title']) ?></a>
          <?= $ev['featured'] ? ' <span class="status-pill going">★</span>' : '' ?>
          <?= $ev['invite_only'] ? ' 🔒' : '' ?></td>
      <td><?= e($ev['host_name'] ?? '—') ?></td>
      <td><?= e(when($ev['event_at'])) ?></td>
      <td><?= e($ev['area']) ?></td><td><?= (int) $ev['rsvps'] ?></td>
      <td><span class="status-pill <?= $ev['status'] === 'active' ? 'going' : 'warn' ?>"><?= e($ev['status']) ?></span></td>
      <td style="white-space:nowrap">
        <form method="post" style="display:inline"><?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
          <button class="btn-mini" name="do" value="feature" type="submit"><?= $ev['featured'] ? 'Unfeature' : '★ Feature' ?></button>
          <?php if ($ev['status'] === 'active'): ?>
          <button class="btn-mini warn" name="do" value="cancel" type="submit">Cancel</button>
          <?php else: ?>
          <button class="btn-mini solid" name="do" value="restore" type="submit">Restore</button>
          <?php endif; ?>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php });
