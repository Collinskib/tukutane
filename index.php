<?php
require_once __DIR__ . '/db.php';

$view     = ($_GET['view'] ?? '') === 'mine' ? 'mine' : 'discover';
$interest = $_GET['interest'] ?? 'All interests';
if ($interest !== 'All interests' && !isset(CATEGORIES[$interest])) $interest = 'All interests';
$area  = $_GET['area'] ?? 'All Nairobi';
if ($area !== 'All Nairobi' && !in_array($area, AREAS, true)) $area = 'All Nairobi';
$query = trim($_GET['q'] ?? '');
$eventId = (int) ($_GET['event'] ?? 0);

$events  = gatherings($view, $interest, $area, $query);
$detail  = $eventId ? find_gathering($eventId) : null;
$flashMsg = flash();

// Build a URL that keeps the current filters, minus keys in $drop.
function keep(array $drop = [], array $add = []): string {
    global $view, $interest, $area, $query;
    $p = ['view' => $view, 'interest' => $interest, 'area' => $area, 'q' => $query];
    $p = array_merge($p, $add);
    foreach ($drop as $d) unset($p[$d]);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== 'All interests' && $v !== 'All Nairobi' && $v !== 'discover');
    return 'index.php' . ($p ? '?' . http_build_query($p) : '');
}
?>
<!doctype html>
<html lang="en">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Nipange · Nairobi gatherings</title>
<link rel="stylesheet" href="assets/style.css">
<header>
  <nav>
    <span class="brand">Nipange<i>.</i></span>
    <a class="btn <?= $view === 'discover' ? 'active' : '' ?>" href="index.php">Discover</a>
    <a class="btn <?= $view === 'mine' ? 'active' : '' ?>" href="?view=mine">My plans</a>
    <button id="open" class="primary" type="button">+ Create plan</button>
  </nav>
</header>
<main>
  <section class="hero">
    <div>
      <small>NAIROBI, KENYA</small>
      <h1>Find your people.</h1>
      <p>Find a game to watch, a place to dance, or a group to spend your Saturday with.</p>
    </div>
    <div class="scene" role="img" aria-label="Friends gathering in Nairobi"></div>
  </section>

  <form method="get" action="index.php" id="filter-form">
    <?php if ($view === 'mine'): ?><input type="hidden" name="view" value="mine"><?php endif; ?>
    <div class="toolbar">
      <h2 id="heading"><?= $view === 'mine' ? 'My plans' : 'Gatherings nearby' ?></h2>
      <input id="search" name="q" type="search" aria-label="Search gatherings"
             placeholder="Search gatherings" value="<?= e($query) ?>">
      <select id="area" name="area" aria-label="Neighbourhood">
        <option<?= $area === 'All Nairobi' ? ' selected' : '' ?>>All Nairobi</option>
        <?php foreach (AREAS as $a): ?>
        <option<?= $a === $area ? ' selected' : '' ?>><?= e($a) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="filters" id="filters" aria-label="Interests">
      <?php foreach (['All interests', ...array_keys(CATEGORIES)] as $cat): ?>
      <button type="submit" name="interest" value="<?= e($cat) ?>"
              class="<?= $cat === $interest ? 'active' : '' ?>"
              aria-pressed="<?= $cat === $interest ? 'true' : 'false' ?>"><?= e($cat) ?></button>
      <?php endforeach; ?>
    </div>
  </form>

  <div id="status" role="status"><?= e($flashMsg) ?></div>

  <div class="grid" id="cards">
    <?php if (!$events): ?>
      <p>No gatherings match these filters.</p>
    <?php endif; ?>
    <?php foreach ($events as $event):
      [$emoji, $ribbon] = CATEGORIES[$event['category']] ?? ['📅', '#e4e6db'];
      $joined = (bool) $event['joined'];
      $mine   = (bool) $event['mine']; ?>
    <article class="card">
      <div class="ribbon" style="background:<?= e($ribbon) ?>">
        <span><?= e($event['category']) ?></span><span><?= $emoji ?></span>
      </div>
      <div class="body">
        <h3><?= e($event['title']) ?></h3>
        <p><?= e(when($event['event_at'])) ?></p>
        <p><?= e($event['area']) ?> · <?= e(price((int) $event['cost'])) ?></p>
        <p><?= $mine
            ? ($event['audience'] === 'friends' ? 'My plans only' : 'Hosted by you')
            : e(going_count($event) . ' going' . ($event['sample'] ? ' · Sample' : '')) ?></p>
        <div class="actions">
          <a class="btn" href="<?= e(keep([], ['event' => $event['id']])) ?>">View details</a>
          <?php if (!$mine): ?>
          <form method="post" action="rsvp.php">
            <input type="hidden" name="id" value="<?= (int) $event['id'] ?>">
            <input type="hidden" name="back" value="<?= e(keep()) ?>">
            <input type="hidden" name="action" value="<?= $joined ? 'cancel' : 'join' ?>">
            <button class="primary" type="submit"><?= $joined ? 'Cancel RSVP' : 'Join' ?></button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    </article>
    <?php endforeach; ?>
  </div>

  <footer>Explore sample gatherings. Your RSVPs and plans are saved on this server.</footer>
</main>

<dialog id="create">
  <form id="plan" method="post" action="create.php">
    <h2>Create a plan</h2>
    <input type="hidden" name="back" value="<?= e(keep()) ?>">
    <label>Event name<input name="title" required maxlength="80"></label>
    <label>Interest<select name="category">
      <?php foreach (array_keys(CATEGORIES) as $cat): ?>
      <option><?= e($cat) ?></option>
      <?php endforeach; ?>
    </select></label>
    <label>Neighbourhood<select name="area">
      <?php foreach (AREAS as $a): ?>
      <option><?= e($a) ?></option>
      <?php endforeach; ?>
    </select></label>
    <label>Venue<input name="venue" required maxlength="80"></label>
    <label>Date and time in Kenya<input type="datetime-local" name="date" required></label>
    <label>Expected cost in KSh<input name="cost" type="number" min="0" max="1000000" value="0" required></label>
    <label>Who can see it?<select name="audience">
      <option value="public">Discover feed</option>
      <option value="friends">My plans only</option>
    </select></label>
    <label>Details<textarea name="description" maxlength="300" required></textarea></label>
    <div class="actions">
      <button type="button" id="cancel">Cancel</button>
      <button class="primary" type="submit">Save plan</button>
    </div>
  </form>
</dialog>

<?php if ($detail): ?>
<dialog id="details">
  <a class="btn close" href="<?= e(keep(['event'])) ?>">Close</a>
  <div id="detail">
    <h2><?= e($detail['title']) ?></h2>
    <p><?= e(when($detail['event_at']) . ' · ' . $detail['venue'] . ', ' . $detail['area']) ?></p>
    <p><?= e(price((int) $detail['cost'])) ?></p>
    <p><?= e($detail['description']) ?></p>
    <p><?= $detail['sample'] ? 'Sample gathering'
        : ($detail['audience'] === 'friends' ? 'Only in My plans' : 'Created on Nipange') ?></p>
    <?php if (!$detail['mine']): ?>
    <form method="post" action="rsvp.php">
      <input type="hidden" name="id" value="<?= (int) $detail['id'] ?>">
      <input type="hidden" name="back" value="<?= e(keep()) ?>">
      <input type="hidden" name="action" value="<?= $detail['joined'] ? 'cancel' : 'join' ?>">
      <button class="primary" type="submit"><?= $detail['joined'] ? 'Cancel RSVP' : 'Join gathering' ?></button>
    </form>
    <?php endif; ?>
  </div>
</dialog>
<?php endif; ?>

<script>
document.getElementById('open').onclick = () => document.getElementById('create').showModal();
const _details = document.getElementById('details');
if (_details) _details.showModal();
document.getElementById('cancel').onclick = () => document.getElementById('create').close();
document.getElementById('area').onchange = () => document.getElementById('filter-form').submit();
let t;
document.getElementById('search').oninput = () => {
  clearTimeout(t);
  t = setTimeout(() => document.getElementById('filter-form').submit(), 500);
};
</script>
</html>
