<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_login('create.php');

// "Plan something here" from a business page — prefill venue + its location.
$pref = null;
if (isset($_GET['venue'])) {
    $pref = venue_get((int) $_GET['venue']) ?: null;
}
$preInst = $pref && $pref['institution_id'] ? institution_get((int) $pref['institution_id']) : null;

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $title    = trim(mb_substr($_POST['title'] ?? '', 0, 80));
    $category = $_POST['category'] ?? '';
    [$area, $estate] = resolve_location($_POST['county'] ?? '');
    $county   = $_POST['county'] ?? '';
    $venue    = trim(mb_substr($_POST['venue'] ?? '', 0, 80));
    $date     = trim($_POST['date'] ?? '');
    $cost     = max(0, min(1000000, (int) ($_POST['cost'] ?? 0)));
    $capacity = ($_POST['capacity'] ?? '') !== '' ? max(1, min(100000, (int) $_POST['capacity'])) : null;
    $audience = in_array($_POST['audience'] ?? '', ['public', 'friends'], true) ? $_POST['audience'] : 'public';
    $invite   = !empty($_POST['invite_only']) ? 1 : 0;
    $desc     = trim(mb_substr($_POST['description'] ?? '', 0, 300));
    $repeat   = in_array($_POST['repeat'] ?? '', ['weekly', 'monthly'], true) ? $_POST['repeat'] : '';
    $venue_id = venue_by_name($venue);
    // optional institution association — independent of the event's own geography
    [$inst_id, $camp_id] = institution_pair(
        (int) ($_POST['institution_id'] ?? 0), (int) ($_POST['campus_id'] ?? 0)
    ) ?: [null, null];

    if ($title === '' || $venue === '' || $desc === '')            $err = 'Fill in the name, venue and details.';
    elseif (!isset(categories()[$category]))                        $err = 'Pick a category.';
    elseif (!area_valid($county, $area))                            $err = 'Pick your county and area.';
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
                (title, category, area, county, estate, venue, event_at, cost, audience, description,
                 sample, creator, creator_id, capacity, invite_only, cover, series_id, venue_id,
                 institution_id, campus_id)
                VALUES (:t, :c, :a, :cy, :es, :v, :d, :k, :au, :ds, 0, :sk, :uid, :cap, :io, :cv, :sid, :vid,
                        :inst, :camp)');
            $firstId = null;
            foreach ($dates as $d) {
                $ins->execute([
                    ':t' => $title, ':c' => $category, ':a' => $area, ':cy' => $county, ':es' => $estate, ':v' => $venue,
                    ':d' => $d, ':k' => $cost, ':au' => $audience, ':ds' => $desc,
                    ':sk' => SESSION_KEY, ':uid' => user_id(), ':cap' => $capacity,
                    ':io' => $invite, ':cv' => $cover ?: null, ':sid' => $sid, ':vid' => $venue_id,
                    ':inst' => $inst_id, ':camp' => $camp_id,
                ]);
                $firstId ??= (int) db()->lastInsertId();
            }
            foreach (follower_ids(user_id()) as $fid) {
                notify($fid, 'host_event',
                    user()['name'] . ' posted "' . $title . '" — ' . date('D j M · g:i A', strtotime($date)) . ' at ' . $venue . ', ' . $area . '.',
                    'event.php?id=' . $firstId);
            }
            // heads-up to the business owner — they can decline the plan
            // from the event page, which detaches it from their listing.
            if ($venue_id) {
                $ov = venue_get($venue_id);
                if ($ov && $ov['creator_id'] && (int) $ov['creator_id'] !== user_id()) {
                    notify((int) $ov['creator_id'], 'venue_plan',
                        user()['name'] . ' planned "' . $title . '" at your business ' . $ov['name'] . ' — '
                            . date('D j M · g:i A', strtotime($date)) . '.',
                        'event.php?id=' . $firstId);
                }
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
<div class="create-layout">
<div class="form rv in" style="max-width:560px">
  <h2>Create a plan</h2>
  <?php if ($pref): ?><p style="margin:-6px 0 12px;color:var(--ink-soft);font-size:14px">🏪 Planning at <b><?= e($pref['name']) ?></b> — the business owner will be notified and can decline it.</p><?php endif; ?>
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
      <div class="field"><label>Where</label>
        <?php location_picker($_POST['county'] ?? ($pref['county'] ?? ''), $_POST['area'] ?? ($pref['area'] ?? ''), $_POST['estate'] ?? ($pref['estate'] ?? '')); ?></div>
    </div>

    <div class="field"><label for="venue">Venue</label>
      <input id="venue" name="venue" required maxlength="80" list="venuelist" value="<?= e($_POST['venue'] ?? ($pref['name'] ?? '')) ?>"
             placeholder="e.g. rooftop lounge, sports café">
      <datalist id="venuelist">
        <?php foreach (venues_all() as $v): ?><option value="<?= e($v['name']) ?>"><?= e(place_label($v)) ?></option><?php endforeach; ?>
      </datalist>
      <small style="color:var(--ink-soft)">Known spots autocomplete — or <a href="venue-edit.php">add a venue</a>.</small></div>

    <div class="field"><label>Associate with an institution (optional)</label>
      <?php institution_picker((int) ($preInst['id'] ?? 0), (string) ($preInst['name'] ?? '')); ?>
      <small style="color:var(--ink-soft)">Tag the event to a university or college community — anyone can still see it. The venue above stays the real location.</small></div>

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
        <option value=""<?= ($_POST['repeat'] ?? '') === '' ? ' selected' : '' ?>>One-time event</option>
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
<aside class="tips">
  <div class="card"><div class="body">
    <h3>A great plan has…</h3>
    <ul>
      <li>📍 A venue people can actually find</li>
      <li>🕖 A specific date and start time</li>
      <li>🖼️ A cover photo — events with covers get more joins</li>
      <li>💬 A line about what to expect or bring</li>
    </ul>
  </div></div>
  <div class="card" style="margin-top:14px"><div class="body">
    <h3>Heads up</h3>
    <ul>
      <li>Plans are public by default — pick "My friends only" to keep it to people you know</li>
      <li>Repeating plans create future dates automatically</li>
      <li>By posting you agree to the <a href="guidelines.php">community guidelines</a></li>
    </ul>
  </div></div>
</aside>
</div>
</main>
<?php bottom_nav('plans'); site_footer(); ?>
