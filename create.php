<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_login('create.php');

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $title    = trim(mb_substr($_POST['title'] ?? '', 0, 80));
    $category = $_POST['category'] ?? '';
    $area     = $_POST['area'] ?? '';
    $venue    = trim(mb_substr($_POST['venue'] ?? '', 0, 80));
    $date     = trim($_POST['date'] ?? '');
    $cost     = max(0, min(1000000, (int) ($_POST['cost'] ?? 0)));
    $capacity = ($_POST['capacity'] ?? '') !== '' ? max(1, min(100000, (int) $_POST['capacity'])) : null;
    $audience = in_array($_POST['audience'] ?? '', ['public', 'friends'], true) ? $_POST['audience'] : 'public';
    $invite   = !empty($_POST['invite_only']) ? 1 : 0;
    $desc     = trim(mb_substr($_POST['description'] ?? '', 0, 300));
    $repeat   = in_array($_POST['repeat'] ?? '', ['weekly', 'monthly'], true) ? $_POST['repeat'] : '';
    $venue_id = venue_by_name($venue);

    if ($title === '' || $venue === '' || $desc === '')            $err = 'Fill in the name, venue and details.';
    elseif (!isset(categories()[$category]))                        $err = 'Pick a category.';
    elseif (!in_array($area, areas(), true))                        $err = 'Pick a neighbourhood.';
    elseif (!strtotime($date) || strtotime($date) < time())         $err = 'Pick a future date and time.';
    else {
        $cover = upload_image('cover');
        if (str_starts_with($cover, '!')) {
            $err = substr($cover, 1);
        } else {
            $sid = null;
            $dates = [date('Y-m-d H:i:s', strtotime($date))];
            if ($repeat) {
                db()->prepare('INSERT INTO gathering_series (freq, creator_id) VALUES (:f, :u)')
                    ->execute([':f' => $repeat, ':u' => user_id()]);
                $sid = (int) db()->lastInsertId();
                $n   = $repeat === 'weekly' ? 7 : 3; // total: 8 weekly / 4 monthly
                $iv  = $repeat === 'weekly' ? 'week' : 'month';
                for ($i = 1; $i <= $n; $i++) {
                    $dates[] = date('Y-m-d H:i:s', strtotime('+' . $i . ' ' . $iv, strtotime($date)));
                }
            }
            $ins = db()->prepare('INSERT INTO gatherings
                (title, category, area, venue, event_at, cost, audience, description,
                 sample, creator, creator_id, capacity, invite_only, cover, series_id, venue_id)
                VALUES (:t, :c, :a, :v, :d, :k, :au, :ds, 0, :sk, :uid, :cap, :io, :cv, :sid, :vid)');
            $firstId = null;
            foreach ($dates as $d) {
                $ins->execute([
                    ':t' => $title, ':c' => $category, ':a' => $area, ':v' => $venue,
                    ':d' => $d, ':k' => $cost, ':au' => $audience, ':ds' => $desc,
                    ':sk' => SESSION_KEY, ':uid' => user_id(), ':cap' => $capacity,
                    ':io' => $invite, ':cv' => $cover ?: null, ':sid' => $sid, ':vid' => $venue_id,
                ]);
                $firstId ??= (int) db()->lastInsertId();
            }
            foreach (follower_ids(user_id()) as $fid) {
                notify($fid, 'host_event',
                    user()['name'] . ' posted "' . $title . '" — ' . date('D j M · g:i A', strtotime($date)) . ' at ' . $venue . ', ' . $area . '.',
                    'event.php?id=' . $firstId);
            }
            flash($repeat ? 'Series created — ' . count($dates) . ' dates are live!' : 'Your plan is live! Share it with friends.');
            redirect('event.php?id=' . $firstId);
        }
    }
}

page_head('Create a plan');
site_header();
?>
<main>
<div class="form rv in" style="max-width:520px">
  <h2>Create a plan</h2>
  <?php if ($err): ?><p style="color:#a33a1f"><?= e($err) ?></p><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="field"><label for="title">Event name</label>
      <input id="title" name="title" required maxlength="80" value="<?= e($_POST['title'] ?? '') ?>"></div>

    <div class="grid2">
      <div class="field"><label for="category">Interest</label>
        <select id="category" name="category">
          <?php foreach (array_keys(categories()) as $cat): ?>
          <option<?= ($_POST['category'] ?? '') === $cat ? ' selected' : '' ?>><?= e($cat) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="field"><label for="area">Neighbourhood</label>
        <select id="area" name="area">
          <?php foreach (areas() as $a): ?>
          <option<?= ($_POST['area'] ?? '') === $a ? ' selected' : '' ?>><?= e($a) ?></option>
          <?php endforeach; ?>
        </select></div>
    </div>

    <div class="field"><label for="venue">Venue</label>
      <input id="venue" name="venue" required maxlength="80" list="venuelist" value="<?= e($_POST['venue'] ?? '') ?>"
             placeholder="e.g. rooftop lounge, sports café">
      <datalist id="venuelist">
        <?php foreach (venues_all() as $v): ?><option value="<?= e($v['name']) ?>"><?= e($v['area']) ?></option><?php endforeach; ?>
      </datalist>
      <small style="color:var(--ink-soft)">Known spots autocomplete — or <a href="venue-edit.php">add a venue</a>.</small></div>

    <div class="grid2">
      <div class="field"><label for="date">Date and time (Kenya)</label>
        <input id="date" name="date" type="datetime-local" required
               value="<?= e($_POST['date'] ?? '') ?>"></div>
      <div class="field"><label for="cost">Cost in KSh (0 = free)</label>
        <input id="cost" name="cost" type="number" min="0" max="1000000" value="<?= e($_POST['cost'] ?? '0') ?>"></div>
    </div>

    <div class="grid2">
      <div class="field"><label for="capacity">Capacity (blank = unlimited)</label>
        <input id="capacity" name="capacity" type="number" min="1" max="100000"
               value="<?= e($_POST['capacity'] ?? '') ?>" placeholder="e.g. 20"></div>
      <div class="field"><label for="audience">Who can see it</label>
        <select id="audience" name="audience">
          <option value="public"<?= ($_POST['audience'] ?? '') === 'public' ? ' selected' : '' ?>>Discover feed</option>
          <option value="friends"<?= ($_POST['audience'] ?? '') === 'friends' ? ' selected' : '' ?>>My friends only</option>
        </select></div>
    </div>

    <div class="field">
      <label style="display:flex;gap:8px;align-items:center;cursor:pointer;font-weight:400">
        <input type="checkbox" name="invite_only" value="1" style="width:auto"<?= !empty($_POST['invite_only']) ? ' checked' : '' ?>>
        Invite-only — people join by invitation, not the public feed
      </label>
    </div>

    <div class="field"><label for="repeat">Repeat</label>
      <select id="repeat" name="repeat">
        <option value=""<?= ($_POST['repeat'] ?? '') === '' ? ' selected' : '' ?>>One-time gathering</option>
        <option value="weekly"<?= ($_POST['repeat'] ?? '') === 'weekly' ? ' selected' : '' ?>>Weekly — creates the next 8 dates</option>
        <option value="monthly"<?= ($_POST['repeat'] ?? '') === 'monthly' ? ' selected' : '' ?>>Monthly — creates the next 4 dates</option>
      </select></div>

    <div class="field"><label for="desc">Details</label>
      <textarea id="desc" name="description" maxlength="300" rows="3" required
                placeholder="What should people expect?"><?= e($_POST['description'] ?? '') ?></textarea></div>

    <div class="field"><label for="cover">Cover image (optional, max 4 MB)</label>
      <img id="coverprev" class="imgprev wide" hidden alt="">
      <input id="cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp" data-preview="#coverprev"></div>

    <button class="btn-block" type="submit">Publish plan</button>
  </form>
</div>
</main>
<?php bottom_nav('plans'); site_footer(); ?>
