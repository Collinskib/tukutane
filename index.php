<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

// legacy deep-link (?event=N opened the old modal) → event page
if (!empty($_GET['event'])) redirect('event.php?id=' . (int) $_GET['event']);

$view     = in_array($_GET['view'] ?? '', ['discover', 'mine'], true) ? $_GET['view'] : 'discover';
if ($view === 'mine' && !user()) $view = 'discover';
$interest = $_GET['interest'] ?? 'All interests';
if ($interest !== 'All interests' && !isset(categories()[$interest])) $interest = 'All interests';
$area  = $_GET['area'] ?? 'All Nairobi';
if ($area !== 'All Nairobi' && !in_array($area, areas(), true)) $area = 'All Nairobi';
$query = trim($_GET['q'] ?? '');
$when  = in_array($_GET['when'] ?? '', ['today', 'tomorrow', 'weekend'], true) ? $_GET['when'] : '';
$cost  = in_array($_GET['cost'] ?? '', ['free', 'paid'], true) ? $_GET['cost'] : '';
$sort  = in_array($_GET['sort'] ?? '', ['popular'], true) ? $_GET['sort'] : 'soon';
$page  = max(1, (int) ($_GET['page'] ?? 1));

$events = gatherings(compact('view', 'interest', 'area') + [
    'q' => $query, 'when' => $when, 'cost' => $cost, 'sort' => $sort,
    'page' => $page, 'per' => 12,
]);
$hasMore = count($events) === 12;

page_head($view === 'mine' ? 'My plans' : 'Discover gatherings');
site_header('discover');
?>
<main>
  <section class="hero">
    <div>
      <small>Nairobi, Kenya</small>
      <h1>Find your people.</h1>
      <p>Find a game to watch, a place to dance, or a group to spend your Saturday with.</p>
    </div>
    <div class="scene" role="img" aria-label="Friends gathering in Nairobi"></div>
  </section>

  <form method="get" action="index.php" id="filter-form">
    <?php if ($view === 'mine'): ?><input type="hidden" name="view" value="mine"><?php endif; ?>
    <?php if ($interest !== 'All interests'): ?><input type="hidden" name="interest" value="<?= e($interest) ?>"><?php endif; ?>
    <?php if ($when): ?><input type="hidden" name="when" value="<?= e($when) ?>"><?php endif; ?>
    <?php if ($cost): ?><input type="hidden" name="cost" value="<?= e($cost) ?>"><?php endif; ?>
    <?php if ($sort === 'popular'): ?><input type="hidden" name="sort" value="popular"><?php endif; ?>
    <div class="toolbar">
      <h2 id="heading"><?= $view === 'mine' ? 'My plans' : 'Gatherings nearby' ?></h2>
      <span class="search-wrap">
      <input id="search" name="q" type="search" aria-label="Search gatherings"
             placeholder="Search gatherings" value="<?= e($query) ?>" autocomplete="off">
      <div id="suggest" class="suggest" hidden></div>
      </span>
      <select id="area" name="area" aria-label="Neighbourhood" onchange="this.form.submit()">
        <option<?= $area === 'All Nairobi' ? ' selected' : '' ?>>All Nairobi</option>
        <?php foreach (areas() as $a): ?>
        <option<?= $a === $area ? ' selected' : '' ?>><?= e($a) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="filters" id="filters" aria-label="Interests">
      <?php foreach (['All interests', ...array_keys(categories())] as $cat): ?>
      <button type="submit" name="interest" value="<?= e($cat) ?>"
              class="<?= $cat === $interest ? 'active' : '' ?>"
              aria-pressed="<?= $cat === $interest ? 'true' : 'false' ?>"><?= e($cat) ?></button>
      <?php endforeach; ?>
    </div>
    <div class="chips" role="group" aria-label="Quick filters">
      <?php foreach (['' => 'Upcoming', 'today' => 'Today', 'tomorrow' => 'Tomorrow', 'weekend' => 'This weekend'] as $w => $label): ?>
      <button type="submit" name="when" value="<?= $w ?>" class="chip <?= $when === $w ? 'on' : '' ?>"><?= $label ?></button>
      <?php endforeach; ?>
      <span style="width:6px"></span>
      <button type="submit" name="cost" value=""    class="chip <?= $cost === '' ? 'on' : '' ?>">Any price</button>
      <button type="submit" name="cost" value="free" class="chip <?= $cost === 'free' ? 'on' : '' ?>">Free</button>
      <button type="submit" name="cost" value="paid" class="chip <?= $cost === 'paid' ? 'on' : '' ?>">Paid</button>
      <span style="width:6px"></span>
      <button type="submit" name="sort" value="<?= $sort === 'popular' ? 'soon' : 'popular' ?>"
              class="chip <?= $sort === 'popular' ? 'on' : '' ?>">🔥 Popular</button>
    </div>
  </form>

  <?php if ($query || $interest !== 'All interests' || $area !== 'All Nairobi' || $when || $cost): ?>
  <div class="chips">
    <?php
      $active = array_filter([
        $query ? '“' . $query . '”' : null,
        $interest !== 'All interests' ? $interest : null,
        $area !== 'All Nairobi' ? $area : null,
        $when ? ucfirst($when === 'weekend' ? 'This weekend' : $when) : null,
        $cost ? ucfirst($cost) . ' events' : null,
      ]);
      foreach ($active as $a): ?>
    <span class="chip on rv in"><?= e($a) ?></span>
    <?php endforeach; ?>
    <a class="chip" href="index.php<?= $view === 'mine' ? '?view=mine' : '' ?>">✕ Clear all</a>
  </div>
  <?php endif; ?>

  <div class="grid" id="cards">
    <?php if (!$events): ?>
      <div class="empty rv in" style="grid-column:1/-1">
        <span class="big">🫂</span>
        <p>No gatherings match these filters<?= $view === 'mine' ? ' — join or create one!' : '' ?>.</p>
        <a class="primary btn" href="create.php">+ Create a plan</a>
      </div>
    <?php endif; ?>
    <?php foreach ($events as $i => $event):
      [$emoji, $ribbon] = categories()[$event['category']] ?? ['📅', '#e4e6db'];
      $joined = (bool) $event['joined'];
      $mine   = (bool) $event['mine']; ?>
    <article class="card rv" style="--d:<?= min($i * 45, 400) ?>ms">
      <?php if (!empty($event['cover'])): ?>
      <div class="ribbon cover"><img src="uploads/<?= e($event['cover']) ?>" alt="" loading="lazy"></div>
      <?php else: ?>
      <div class="ribbon" style="background:<?= e($ribbon) ?>">
        <span><?= e($event['category']) ?></span><span><?= $emoji ?></span>
      </div>
      <?php endif; ?>
      <div class="body">
        <h3><a href="event.php?id=<?= (int) $event['id'] ?>" style="color:inherit;text-decoration:none"><?= e($event['title']) ?></a><?= !empty($event['series_id']) ? ' <span title="Repeating series">🔁</span>' : '' ?></h3>
        <p><?= e(when($event['event_at'])) ?></p>
        <p><?= e($event['area']) ?> · <?= e(price((int) $event['cost'])) ?></p>
        <p><?= $mine
            ? '<span class="status-pill going">Hosted by you</span>'
            : e(going_count($event) . ' going' . ($event['sample'] ? ' · Sample' : '')) ?></p>
        <div class="actions">
          <a class="btn" href="event.php?id=<?= (int) $event['id'] ?>">View details</a>
          <?php if (!$mine): ?>
            <?php if (user()): ?>
            <form method="post" action="rsvp.php">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $event['id'] ?>">
              <input type="hidden" name="back" value="<?= e(keep()) ?>">
              <input type="hidden" name="action" value="<?= $joined ? 'cancel' : 'join' ?>">
              <button class="primary" type="submit"><?= $joined ? 'Cancel RSVP' : 'Join' ?></button>
            </form>
            <?php else: ?>
            <a class="primary" href="auth.php?next=<?= urlencode('event.php?id=' . $event['id']) ?>">Join</a>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </article>
    <?php endforeach; ?>
  </div>

  <?php if ($page > 1 || $hasMore): ?>
  <nav class="pager" aria-label="Pages">
    <a class="<?= $page <= 1 ? 'dis' : '' ?>" href="<?= e(keep([], ['page' => $page - 1])) ?>">← Prev</a>
    <span class="on">Page <?= $page ?></span>
    <a class="<?= !$hasMore ? 'dis' : '' ?>" href="<?= e(keep([], ['page' => $page + 1])) ?>">Next →</a>
  </nav>
  <?php endif; ?>
</main>
<script>
let _t, _st;
const _s = document.getElementById('search'), _sg = document.getElementById('suggest');
_s.oninput = () => {
  clearTimeout(_t); clearTimeout(_st);
  _t = setTimeout(() => document.getElementById('filter-form').submit(), 500);
  const q = _s.value.trim();
  if (q.length < 2) { _sg.hidden = true; return; }
  _st = setTimeout(async () => {
    try {
      const r = await fetch('api/suggest.php?q=' + encodeURIComponent(q));
      if (!r.ok) { _sg.hidden = true; return; }
      const items = await r.json();
      if (!items.length) { _sg.hidden = true; return; }
      const esc = s => s.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
      _sg.innerHTML = items.map(i =>
        `<a class="sg-item" href="event.php?id=${i.id}"><b>${esc(i.title)}</b><small>${esc(i.meta)}</small></a>`).join('');
      _sg.hidden = false;
    } catch { _sg.hidden = true; }
  }, 180);
};
_s.onblur = () => setTimeout(() => _sg.hidden = true, 150);
</script>
<?php bottom_nav('discover'); site_footer(); ?>
