<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
require_admin();
require_once __DIR__ . '/_layout.php';

$stats = [
    'Users'          => db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'Active events'  => db()->query('SELECT COUNT(*) FROM gatherings WHERE status="active" AND event_at > NOW()')->fetchColumn(),
    'RSVPs'          => db()->query('SELECT COUNT(*) FROM rsvps')->fetchColumn(),
    'Friendships'    => db()->query('SELECT COUNT(*) FROM friendships WHERE status="accepted"')->fetchColumn(),
    'Messages'       => db()->query('SELECT COUNT(*) FROM messages')->fetchColumn(),
    'Open reports'   => db()->query('SELECT COUNT(*) FROM reports WHERE status="open"')->fetchColumn(),
];
$latestUsers = db()->query('SELECT id,name,email,area,created_at FROM users ORDER BY id DESC LIMIT 6')->fetchAll(PDO::FETCH_ASSOC);
$latestEvents = db()->query('SELECT id,title,area,event_at FROM gatherings ORDER BY id DESC LIMIT 6')->fetchAll(PDO::FETCH_ASSOC);

admin_page('Overview', 'index', function () use ($stats, $latestUsers, $latestEvents) { ?>
  <h2>Overview</h2>
  <div class="stat-grid">
    <?php foreach ($stats as $label => $n): ?>
    <div class="stat rv" style="--d:<?= array_search($label, array_keys($stats)) * 60 ?>ms">
      <b data-count="<?= (int) $n ?>">0</b><small><?= e($label) ?></small>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="grid2">
    <section class="panel">
      <h3 style="font-family:var(--serif);font-weight:400;padding:14px 16px 4px">Newest users</h3>
      <table class="admin"><tbody>
        <?php foreach ($latestUsers as $u): ?>
        <tr><td><a href="../user.php?id=<?= (int) $u['id'] ?>"><?= e($u['name']) ?></a></td>
            <td><?= e($u['email']) ?></td><td><?= e($u['area']) ?></td>
            <td><?= e(date('M j', strtotime($u['created_at']))) ?></td></tr>
        <?php endforeach; ?>
      </tbody></table>
    </section>
    <section class="panel">
      <h3 style="font-family:var(--serif);font-weight:400;padding:14px 16px 4px">Newest events</h3>
      <table class="admin"><tbody>
        <?php foreach ($latestEvents as $ev): ?>
        <tr><td><a href="../event.php?id=<?= (int) $ev['id'] ?>"><?= e($ev['title']) ?></a></td>
            <td><?= e($ev['area']) ?></td><td><?= e(when($ev['event_at'])) ?></td></tr>
        <?php endforeach; ?>
      </tbody></table>
    </section>
  </div>

  <script>
  // stat count-up on first view
  document.querySelectorAll('[data-count]').forEach(function(el){
    var target=+el.dataset.count,t0=null;
    if(matchMedia('(prefers-reduced-motion: reduce)').matches||target===0){el.textContent=target;return;}
    function step(ts){if(!t0)t0=ts;var p=Math.min((ts-t0)/700,1);
      el.textContent=Math.round(target*(1-Math.pow(1-p,3)));
      if(p<1)requestAnimationFrame(step);}
    requestAnimationFrame(step);
  });
  </script>
<?php });
