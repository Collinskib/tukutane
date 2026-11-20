<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_login('index.php');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$ev = find_gathering($id);
if (!$ev || (!$ev['mine'] && !is_admin())) {
    http_response_code(403);
    page_head('Not allowed'); site_header();
    echo '<main><div class="empty"><p>You can only manage your own gatherings.</p><a class="primary btn" href="index.php">Back</a></div></main>';
    bottom_nav(); site_footer(); exit;
}
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    switch ($_POST['do'] ?? '') {
        case 'save':
            $title    = trim(mb_substr($_POST['title'] ?? '', 0, 80));
            $category = $_POST['category'] ?? $ev['category'];
            $area     = $_POST['area'] ?? $ev['area'];
            $venue    = trim(mb_substr($_POST['venue'] ?? '', 0, 80));
            $date     = trim($_POST['date'] ?? '');
            $cost     = max(0, min(1000000, (int) ($_POST['cost'] ?? 0)));
            $capacity = ($_POST['capacity'] ?? '') !== '' ? max(1, min(100000, (int) $_POST['capacity'])) : null;
            $audience = in_array($_POST['audience'] ?? '', ['public', 'friends'], true) ? $_POST['audience'] : 'public';
            $venue_id = venue_by_name($venue);
            $invite   = !empty($_POST['invite_only']) ? 1 : 0;
            $desc     = trim(mb_substr($_POST['description'] ?? '', 0, 300));
            if ($title === '' || $venue === '' || $desc === '')        $err = 'Fill in name, venue and details.';
            elseif (!isset(categories()[$category]))                    $err = 'Pick a category.';
            elseif (!in_array($area, areas(), true))                    $err = 'Pick a neighbourhood.';
            elseif (!strtotime($date))                                   $err = 'Invalid date.';
            else {
                $cover = upload_image('cover');
                if (str_starts_with($cover, '!')) { $err = substr($cover, 1); break; }
                $params = [
                    ':t' => $title, ':c' => $category, ':a' => $area, ':v' => $venue,
                    ':d' => date('Y-m-d H:i:s', strtotime($date)), ':k' => $cost,
                    ':au' => $audience, ':ds' => $desc, ':cap' => $capacity,
                    ':io' => $invite, ':vid' => $venue_id, ':id' => $id,
                ];
                if ($cover) $params[':cv'] = $cover;
                $scope = ($_POST['scope'] ?? '') === 'series' && $ev['series_id'] ? 'series' : 'one';
                if ($scope === 'series') {
                    // shared fields on every upcoming occurrence; date stays per-occurrence
                    $shared = $params; unset($shared[':d'], $shared[':cv'], $shared[':id']);
                    db()->prepare('UPDATE gatherings SET title=:t,category=:c,area=:a,venue=:v,
                                   cost=:k,audience=:au,description=:ds,capacity=:cap,invite_only=:io,
                                   venue_id=:vid
                                   WHERE series_id=:sid AND status="active" AND event_at >= NOW()')
                        ->execute($shared + [':sid' => $ev['series_id']]);
                    db()->prepare('UPDATE gatherings SET event_at=:d' . ($cover ? ',cover=:cv' : '') . ' WHERE id=:id')
                        ->execute([':d' => $params[':d'], ':id' => $id] + ($cover ? [':cv' => $cover] : []));
                    // notify each upcoming occurrence's attendees when venue/area moved
                    if ($ev['venue'] !== $venue || $ev['area'] !== $area) {
                        $occs = db()->prepare('SELECT id, event_at FROM gatherings WHERE series_id=:s AND status="active" AND event_at >= NOW()');
                        $occs->execute([':s' => $ev['series_id']]);
                        foreach ($occs->fetchAll(PDO::FETCH_ASSOC) as $oc) {
                            $when = date('D j M · g:i A', strtotime($oc['event_at']));
                            foreach (attendees_of((int) $oc['id']) as $a) {
                                if ($a['user_id']) notify((int) $a['user_id'], 'event_updated',
                                    '"' . $title . '" was updated — ' . $when . ' at ' . $venue . ', ' . $area . '.',
                                    'event.php?id=' . $oc['id']);
                            }
                        }
                    }
                    flash('Saved across the whole series.');
                } else {
                    db()->prepare('UPDATE gatherings SET title=:t,category=:c,area=:a,venue=:v,
                                   event_at=:d,cost=:k,audience=:au,description=:ds,
                                   capacity=:cap,invite_only=:io,venue_id=:vid'
                                   . ($cover ? ',cover=:cv' : '') . ' WHERE id=:id')
                        ->execute($params);
                    if ($ev['venue'] !== $venue || $ev['area'] !== $area || strtotime($ev['event_at']) !== strtotime($date)) {
                        $when = date('D j M · g:i A', strtotime($date));
                        foreach (attendees_of($id) as $a) {
                            if ($a['user_id']) notify((int) $a['user_id'], 'event_updated',
                                '"' . $title . '" was updated — ' . $when . ' at ' . $venue . ', ' . $area . '.',
                                'event.php?id=' . $id);
                        }
                    }
                    flash('Saved.');
                }
                if ($capacity === null || $ev['capacity'] === null || $capacity > (int) $ev['capacity']) {
                    waitlist_promote($id);
                }
                redirect('edit.php?id=' . $id);
            }
            break;
        case 'cancel_event':
            $scope = ($_POST['scope'] ?? '') === 'series' && $ev['series_id'] ? 'series' : 'one';
            if ($scope === 'series') {
                $occs = db()->prepare('SELECT id FROM gatherings WHERE series_id=:s AND status="active" AND event_at >= NOW()');
                $occs->execute([':s' => $ev['series_id']]);
                $ids = array_map('intval', $occs->fetchAll(PDO::FETCH_COLUMN));
                db()->prepare('UPDATE gatherings SET status="cancelled" WHERE series_id=:s AND status="active" AND event_at >= NOW()')
                    ->execute([':s' => $ev['series_id']]);
                foreach ($ids as $oid) {
                    foreach (attendees_of($oid) as $a) {
                        if ($a['user_id']) notify((int) $a['user_id'], 'event_cancelled',
                            '"' . $ev['title'] . '" was cancelled by the host.', 'index.php');
                    }
                }
                flash('Whole series cancelled — attendees have been notified.');
            } else {
                db()->prepare('UPDATE gatherings SET status = "cancelled" WHERE id = :i')->execute([':i' => $id]);
                foreach (attendees_of($id) as $a) {
                    if ($a['user_id']) notify((int) $a['user_id'], 'event_cancelled',
                        '"' . $ev['title'] . '" was cancelled by the host.', 'index.php');
                }
                flash('Gathering cancelled — attendees have been notified.');
            }
            redirect('edit.php?id=' . $id);
            break;
        case 'invite':
            $res = invite_user($id, (int) ($_POST['uid'] ?? 0), user_id());
            flash($res === 'invited' ? 'Invitation sent.' : 'Already invited or going.', $res === 'invited' ? 'ok' : 'err');
            redirect('edit.php?id=' . $id . '#attendees');
            break;
    }
}

$attendees = attendees_of($id);
$friends   = user() ? my_friends(user_id()) : [];
$goingIds  = array_map(fn($a) => (int) ($a['user_id'] ?? 0), $attendees);
$invited   = db()->prepare('SELECT user_id, status FROM invites WHERE gathering_id = :g');
$invited->execute([':g' => $id]);
$invMap = array_column($invited->fetchAll(PDO::FETCH_ASSOC), 'status', 'user_id');

$series = null; $siblings = [];
if (!empty($ev['series_id'])) {
    $series = db()->prepare('SELECT s.* FROM gathering_series s WHERE s.id = :s');
    $series->execute([':s' => $ev['series_id']]);
    $series = $series->fetch(PDO::FETCH_ASSOC) ?: null;
    $sib = db()->prepare('SELECT id, event_at, status FROM gatherings WHERE series_id = :s AND id != :i AND event_at >= NOW() ORDER BY event_at LIMIT 10');
    $sib->execute([':s' => $ev['series_id'], ':i' => $id]);
    $siblings = $sib->fetchAll(PDO::FETCH_ASSOC);
}

page_head('Manage — ' . $ev['title']);
site_header('plans');
?>
<main>
<div class="form rv in" style="max-width:560px">
  <div style="display:flex;align-items:center;justify-content:space-between">
    <h2>Manage gathering</h2>
    <?php if ($ev['status'] === 'cancelled'): ?><span class="status-pill warn">Cancelled</span><?php endif; ?>
  </div>
  <?php if ($series): ?>
  <p style="color:var(--ink-soft)">🔁 Part of a <b><?= e($series['freq']) ?></b> series.
    <?php if ($siblings): ?>Other dates:
      <?php foreach ($siblings as $s): ?><a href="edit.php?id=<?= (int) $s['id'] ?>" style="white-space:nowrap"><?= e(date('j M', strtotime($s['event_at']))) ?></a> · <?php endforeach; ?>
    <?php endif; ?>
  </p>
  <?php endif; ?>
  <?php if ($err): ?><p style="color:#a33a1f"><?= e($err) ?></p><?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="save">
    <input type="hidden" name="id" value="<?= $id ?>">
    <div class="field"><label>Event name</label><input name="title" required maxlength="80" value="<?= e($ev['title']) ?>"></div>
    <div class="grid2">
      <div class="field"><label>Interest</label>
        <select name="category"><?php foreach (array_keys(categories()) as $cat): ?>
          <option<?= $ev['category'] === $cat ? ' selected' : '' ?>><?= e($cat) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Neighbourhood</label>
        <select name="area"><?php foreach (areas() as $a): ?>
          <option<?= $ev['area'] === $a ? ' selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="field"><label>Venue</label><input name="venue" required maxlength="80" list="venuelist" value="<?= e($ev['venue']) ?>">
      <datalist id="venuelist">
        <?php foreach (venues_all() as $v): ?><option value="<?= e($v['name']) ?>"><?= e($v['area']) ?></option><?php endforeach; ?>
      </datalist>
      <small style="color:var(--ink-soft)">Known spots autocomplete — or <a href="venue-edit.php">add a venue</a>.</small></div>
    <div class="grid2">
      <div class="field"><label>Date and time</label>
        <input name="date" type="datetime-local" required
               value="<?= e(date('Y-m-d\TH:i', strtotime($ev['event_at']))) ?>"></div>
      <div class="field"><label>Cost KSh</label>
        <input name="cost" type="number" min="0" value="<?= (int) $ev['cost'] ?>"></div>
    </div>
    <div class="grid2">
      <div class="field"><label>Capacity</label>
        <input name="capacity" type="number" min="1" value="<?= e($ev['capacity'] ?? '') ?>"></div>
      <div class="field"><label>Audience</label>
        <select name="audience">
          <option value="public"<?= $ev['audience'] === 'public' ? ' selected' : '' ?>>Discover feed</option>
          <option value="friends"<?= $ev['audience'] === 'friends' ? ' selected' : '' ?>>My friends only</option>
        </select></div>
    </div>
    <div class="field"><label style="display:flex;gap:8px;align-items:center;font-weight:400">
      <input type="checkbox" name="invite_only" value="1" style="width:auto"<?= $ev['invite_only'] ? ' checked' : '' ?>> Invite-only</label></div>
    <div class="field"><label>Details</label>
      <textarea name="description" maxlength="300" rows="3" required><?= e($ev['description']) ?></textarea></div>
    <div class="field"><label>Cover image<?= $ev['cover'] ? ' (replace)' : '' ?></label>
      <?php if ($ev['cover']): ?><img src="uploads/<?= e($ev['cover']) ?>" class="imgprev wide" alt=""><?php endif; ?>
      <img id="coverprev" class="imgprev wide" hidden alt="">
      <input name="cover" type="file" accept="image/jpeg,image/png,image/webp" data-preview="#coverprev"></div>
    <?php if ($series): ?>
    <div class="field"><label>Apply changes to</label>
      <label style="display:flex;gap:8px;align-items:center;font-weight:400">
        <input type="radio" name="scope" value="one" style="width:auto" checked> This date only</label>
      <label style="display:flex;gap:8px;align-items:center;font-weight:400;margin-top:6px">
        <input type="radio" name="scope" value="series" style="width:auto"> All upcoming dates — keeps each date's own time</label>
    </div>
    <?php endif; ?>
    <button class="btn-block" type="submit">Save changes</button>
  </form>
</div>

<section class="form rv in" style="max-width:560px" id="attendees">
  <h2>Attendees (<?= count($attendees) ?>)</h2>
  <?php if (!$attendees): ?><p style="color:var(--ink-soft)">Nobody has joined yet — share the link!</p><?php endif; ?>
  <?php foreach ($attendees as $a): ?>
  <div style="display:flex;gap:10px;align-items:center;padding:7px 0">
    <?= avatar_html($a) ?>
    <span><?= e($a['name'] ?? 'Guest (not signed up)') ?></span>
    <small style="margin-left:auto;color:var(--ink-soft)"><?= e(time_ago($a['created_at'])) ?></small>
  </div>
  <?php endforeach; ?>

  <?php if ($friends): ?>
  <h3 style="font-family:var(--serif);font-weight:400;margin-top:20px">Invite friends</h3>
  <?php foreach ($friends as $fr):
      $already = in_array((int) $fr['id'], $goingIds, true) || isset($invMap[$fr['id']]); ?>
  <div style="display:flex;gap:10px;align-items:center;padding:7px 0">
    <?= avatar_html($fr) ?>
    <span><?= e($fr['name']) ?></span>
    <?php if (in_array((int) $fr['id'], $goingIds, true)): ?>
      <span class="status-pill going" style="margin-left:auto">Going</span>
    <?php elseif (isset($invMap[$fr['id']])): ?>
      <span class="status-pill" style="margin-left:auto"><?= ucfirst($invMap[$fr['id']]) ?></span>
    <?php else: ?>
      <form method="post" style="margin-left:auto"><?= csrf_field() ?>
        <input type="hidden" name="do" value="invite"><input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="uid" value="<?= (int) $fr['id'] ?>">
        <button class="btn-mini solid" type="submit">Invite</button>
      </form>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <div class="share-row">
    <a class="btn-ghost" href="<?= e(whatsapp_share($ev['title'], absolute_url('event.php?id=' . $id))) ?>" target="_blank" rel="noopener">Share on WhatsApp</a>
    <a class="btn-ghost" href="event.php?id=<?= $id ?>">View public page</a>
  </div>
</section>

<?php if ($ev['status'] !== 'cancelled'): ?>
<div class="form rv in" style="max-width:560px;border-color:#eccfc8">
  <h2 style="color:#a33a1f">Cancel gathering</h2>
  <p style="color:var(--ink-soft)">Attendees get a notification. This can't be undone.</p>
  <form method="post" onsubmit="return confirm('Cancel this gathering? Attendees will be notified.')">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="cancel_event"><input type="hidden" name="id" value="<?= $id ?>">
    <?php if ($series): ?>
    <div class="field" style="margin-bottom:10px"><label>Cancel scope</label>
      <label style="display:flex;gap:8px;align-items:center;font-weight:400">
        <input type="radio" name="scope" value="one" style="width:auto" checked> This date only</label>
      <label style="display:flex;gap:8px;align-items:center;font-weight:400;margin-top:6px">
        <input type="radio" name="scope" value="series" style="width:auto"> All upcoming dates in the series</label>
    </div>
    <?php endif; ?>
    <button class="btn-danger" type="submit"><?= $series ? 'Cancel' : 'Cancel this gathering' ?></button>
  </form>
</div>
<?php endif; ?>
</main>
<?php bottom_nav('plans'); site_footer(); ?>
