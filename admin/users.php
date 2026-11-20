<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
require_admin();
require_once __DIR__ . '/_layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $uid = (int) ($_POST['uid'] ?? 0);
    if ($uid && $uid !== user_id()) {
        switch ($_POST['do'] ?? '') {
            case 'suspend':
                db()->prepare('UPDATE users SET status="suspended" WHERE id=:u')->execute([':u' => $uid]);
                flash('User suspended.'); break;
            case 'activate':
                db()->prepare('UPDATE users SET status="active" WHERE id=:u')->execute([':u' => $uid]);
                flash('User reactivated.'); break;
            case 'admin':
                db()->prepare('UPDATE users SET role="admin" WHERE id=:u')->execute([':u' => $uid]);
                flash('Admin role granted.'); break;
            case 'demote':
                db()->prepare('UPDATE users SET role="member" WHERE id=:u')->execute([':u' => $uid]);
                flash('Admin role removed.'); break;
        }
    }
    redirect('users.php' . (!empty($_GET['q']) ? '?q=' . urlencode($_GET['q']) : ''));
}

$q = trim($_GET['q'] ?? '');
$stmt = db()->prepare(
    'SELECT id,name,email,area,role,status,created_at,
            (SELECT COUNT(*) FROM gatherings g WHERE g.creator_id = users.id) AS hosted
     FROM users ' . ($q ? 'WHERE name LIKE :q1 OR email LIKE :q2' : '') . ' ORDER BY id DESC LIMIT 200'
);
$stmt->execute($q ? [':q1' => "%$q%", ':q2' => "%$q%"] : []);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

admin_page('Users', 'users', function () use ($users, $q) { ?>
  <h2>Users</h2>
  <form method="get" class="toolbar" style="justify-content:flex-start;margin-bottom:14px">
    <input name="q" value="<?= e($q) ?>" placeholder="Search name or email"
           style="border:1px solid var(--line);border-radius:var(--r-input);padding:10px 13px;min-width:260px">
    <button class="btn-mini" type="submit">Search</button>
  </form>
  <table class="admin">
    <thead><tr><th>User</th><th>Email</th><th>Area</th><th>Hosted</th><th>Role</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
    <tr>
      <td><a href="../user.php?id=<?= (int) $u['id'] ?>"><?= e($u['name']) ?></a></td>
      <td><?= e($u['email']) ?></td><td><?= e($u['area']) ?></td><td><?= (int) $u['hosted'] ?></td>
      <td><?= e($u['role']) ?></td>
      <td><span class="status-pill <?= $u['status'] === 'active' ? 'going' : 'warn' ?>"><?= e($u['status']) ?></span></td>
      <td style="white-space:nowrap">
        <?php if ((int) $u['id'] !== user_id()): ?>
        <form method="post" style="display:inline"><?= csrf_field() ?>
          <input type="hidden" name="uid" value="<?= (int) $u['id'] ?>">
          <?php if ($u['status'] === 'active'): ?>
          <button class="btn-mini warn" name="do" value="suspend" type="submit">Suspend</button>
          <?php else: ?>
          <button class="btn-mini solid" name="do" value="activate" type="submit">Activate</button>
          <?php endif; ?>
          <?php if ($u['role'] !== 'admin'): ?>
          <button class="btn-mini" name="do" value="admin" type="submit">Make admin</button>
          <?php else: ?>
          <button class="btn-mini" name="do" value="demote" type="submit"
                  onclick="return confirm('Remove this admin role?')">Remove admin</button>
          <?php endif; ?>
        </form>
        <?php else: ?><small>(you)</small><?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php });
