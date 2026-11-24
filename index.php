<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/landing.php';

// marketing landing for logged-out visitors on the bare root URL —
// public event discovery stays available at /index.php?view=discover
if (!user() && $_GET === []) { landing_page(); exit; }

// legacy deep-link (?event=N opened the old modal) → event page
if (!empty($_GET['event'])) redirect('event.php?id=' . (int) $_GET['event']);

$view     = in_array($_GET['view'] ?? '', ['discover', 'mine'], true) ? $_GET['view'] : 'discover';
if ($view === 'mine' && !user()) $view = 'discover';
$scope    = in_array($_GET['scope'] ?? '', ['foryou', 'near', 'inst', 'all'], true) ? $_GET['scope'] : 'foryou';
$interest = $_GET['interest'] ?? 'All interests';
if ($interest !== 'All interests' && !isset(categories()[$interest])) $interest = 'All interests';
$county = in_array($_GET['county'] ?? '', counties(), true) ? $_GET['county'] : '';
$area  = in_array($_GET['area'] ?? '', areas(), true) ? $_GET['area'] : '';
$inst  = (int) ($_GET['inst'] ?? $_GET['institution_id'] ?? 0);
$query = trim($_GET['q'] ?? '');
$when  = in_array($_GET['when'] ?? '', ['today', 'tomorrow', 'weekend'], true) ? $_GET['when'] : '';
$cost  = in_array($_GET['cost'] ?? '', ['free', 'paid'], true) ? $_GET['cost'] : '';
$sort  = in_array($_GET['sort'] ?? '', ['popular'], true) ? $_GET['sort'] : 'soon';
$page  = max(1, (int) ($_GET['page'] ?? 1));
$me    = user();
$interests = [];

// scope resolution — shortcuts on top of the same filter machinery.
// "near" defaults geo filters to the profile location; "inst" defaults the
// institution filter to the profile preference; "foryou" matches interests.
$scopeNote = '';
if ($view === 'discover') {
    if ($scope === 'near') {
        if ($me && $me['county']) {
            $county = $county ?: $me['county'];
            $area   = $area ?: $me['area'];
        } else $scopeNote = $me ? 'Add your location in your profile to use Near Me.' : 'Log in and set your location to use Near Me.';
    } elseif ($scope === 'inst') {
        $inst = $inst ?: (int) ($me['institution_id'] ?? 0);
        if (!$inst) $scopeNote = 'Pick an institution below — or set yours in your profile.';
    } elseif ($scope === 'foryou' && $me) {
        $interests = array_values(array_filter(
            array_map('trim', explode(',', $me['interests'] ?? '')),
            fn($c) => isset(categories()[$c])
        ));
    }
}
$instRow = $inst ? institution_get($inst) : null;
if ($inst && !$instRow) $inst = 0;

$events = gatherings(compact('view', 'interest', 'area', 'county') + [
    'q' => $query, 'when' => $when, 'cost' => $cost, 'sort' => $sort,
    'interests' => $interests, 'institution' => $inst ?: null,
    'page' => $page, 'per' => 12,
]);
$hasMore = count($events) === 12;

$partial = !empty($_GET['partial']);
if (!$partial):
page_head($view === 'mine' ? 'My plans' : 'Discover events',
          '<link rel="preload" as="image" href="/assets/hero.webp" fetchpriority="high">');
site_header('discover');
?>
<main>
  <nav class="homepills" aria-label="Explore">
    <a href="/people.php">🧑‍🤝‍🧑 People</a>
    <a class="on" href="/create.php">📅 Create Event</a>
    <a href="/venues.php">🏪 Business</a>
  </nav>
  <section class="hero">
    <div>
      <small>Across Kenya</small>
      <h1>Find your people.</h1>
      <p>Find a game to watch, a place to dance, or a group to spend your Saturday with.</p>
    </div>
    <div class="scene" role="img" aria-label="Friends at a Nairobi café"></div>
  </section>
<?php endif; ?>
<?php if (!$partial): ?><div id="filterzone"><?php endif; ?>
  <?php if ($view === 'discover'): ?>
  <nav class="chips scoperow" aria-label="Feed">
    <?php foreach ([
        'foryou' => '✨ For You',
        'near'   => '📍 Near Me',
        'inst'   => '🎓 My Institution',
        'all'    => '🌍 All Events',
      ] as $s => $label): ?>
    <a class="chip <?= $scope === $s ? 'on' : '' ?>" href="index.php?scope=<?= $s ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </nav>
  <?php endif; ?>
  <form method="get" action="index.php" id="filter-form">
    <?php if ($view === 'mine'): ?><input type="hidden" name="view" value="mine"><?php endif; ?>
    <?php if ($view === 'discover' && $scope !== 'foryou'): ?><input type="hidden" name="scope" value="<?= e($scope) ?>"><?php endif; ?>
    <?php if ($inst && $scope !== 'inst'): ?><input type="hidden" name="inst" value="<?= (int) $inst ?>"><?php endif; ?>
    <?php if ($interest !== 'All interests'): ?><input type="hidden" name="interest" value="<?= e($interest) ?>"><?php endif; ?>
    <?php if ($when): ?><input type="hidden" name="when" value="<?= e($when) ?>"><?php endif; ?>
    <?php if ($cost): ?><input type="hidden" name="cost" value="<?= e($cost) ?>"><?php endif; ?>
    <?php if ($sort === 'popular'): ?><input type="hidden" name="sort" value="popular"><?php endif; ?>
    <div class="toolbar">
      <h2 id="heading"><?= $view === 'mine' ? 'My plans'
          : ($scope === 'inst' && $instRow ? e($instRow['name'])
          : ['foryou' => 'Events for you', 'near' => 'Events near you', 'inst' => 'My institution', 'all' => 'All events'][$scope]) ?></h2>
      <span class="search-wrap">
      <input id="search" name="q" type="search" aria-label="Search events"
             placeholder="Search events" value="<?= e($query) ?>" autocomplete="off">
      <div id="suggest" class="suggest" hidden></div>
      </span>
      <?php location_picker($county, $area, '', 'filter'); ?>
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

  <?php if ($scopeNote): ?><p class="scopenote"><?= e($scopeNote) ?></p><?php endif; ?>
  <?php if ($scope === 'inst' && !$inst): ?>
  <form method="get" action="index.php" class="instjump">
    <input type="hidden" name="scope" value="inst">
    <?php institution_picker(); ?>
    <button class="btn-mini" type="submit">Show events</button>
  </form>
  <?php endif; ?>

  <?php if ($query || $interest !== 'All interests' || $county || $area || $when || $cost || $inst): ?>
  <div class="chips">
    <?php
      $active = array_filter([
        $query ? '“' . $query . '”' : null,
        $interest !== 'All interests' ? $interest : null,
        $county ? ($area ? "$area, $county" : $county) : null,
        $when ? ucfirst($when === 'weekend' ? 'This weekend' : $when) : null,
        $cost ? ucfirst($cost) . ' events' : null,
        $instRow ? '🎓 ' . $instRow['name'] : null,
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
        <p>No events match these filters<?= $view === 'mine' ? ' — join or create one!' : '' ?>.</p>
        <a class="primary btn" href="create.php">+ Create a plan</a>
      </div>
    <?php endif; ?>
    <?php foreach ($events as $i => $event):
      [$emoji, $ribbon] = categories()[$event['category']] ?? ['📅', '#e4e6db'];
      $joined = (bool) $event['joined'];
      $mine   = (bool) $event['mine']; ?>
    <article class="card rv" style="--d:<?= min($i * 45, 400) ?>ms">
      <?php if (!empty($event['cover'])): ?>
      <div class="ribbon cover"><img src="<?= img_url($event['cover'], 800) ?>" alt="" loading="lazy" decoding="async"></div>
      <?php else: ?>
      <div class="ribbon" style="background:<?= e($ribbon) ?>">
        <span><?= e($event['category']) ?></span><span><?= $emoji ?></span>
      </div>
      <?php endif; ?>
      <div class="body">
        <h3><a href="event.php?id=<?= (int) $event['id'] ?>" style="color:inherit;text-decoration:none"><?= e($event['title']) ?></a><?= !empty($event['series_id']) ? ' <span title="Repeating series">🔁</span>' : '' ?></h3>
        <p><?= e(when($event['event_at'])) ?></p>
        <p><?= e(place_label($event)) ?> · <?= e(price((int) $event['cost'])) ?></p>
        <?php if (!empty($event['institution_name'])): ?>
        <p class="insttag">🎓 <a href="institution.php?id=<?= (int) $event['institution_id'] ?>"><?= e($event['institution_name']) ?></a></p>
        <?php endif; ?>
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
<?php if ($partial) exit; ?>
</div>
</main>
<script>
let _t, _st, _seq = 0;
const _zone = document.getElementById('filterzone');

function bindSearch() {
  const _s = document.getElementById('search'), _sg = document.getElementById('suggest');
  if (!_s) return;
  _s.oninput = () => {
    clearTimeout(_t); clearTimeout(_st);
    _t = setTimeout(refreshFeed, 500);
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
}
bindSearch();

// Swap the filter+feed zone in place — no full page reload.
async function refreshFeed(extra) {
  const form = document.getElementById('filter-form');
  if (!form) return;
  const params = new URLSearchParams(new FormData(form));
  if (extra) for (const k in extra) params.set(k, extra[k]);
  params.set('partial', '1');
  const my = ++_seq;
  _zone.style.opacity = '.55';
  try {
    const r = await fetch('index.php?' + params.toString());
    if (my !== _seq) return;
    if (!r.ok) throw 0;
    _zone.innerHTML = await r.text();
    params.delete('partial');
    const qs = params.toString();
    history.replaceState(null, '', 'index.php' + (qs ? '?' + qs : ''));
    _zone.querySelectorAll('.rv').forEach(el => el.classList.add('in'));
    _zone.querySelectorAll('.loccounty').forEach(s => { if (s.value && window.LOC_FILL) LOC_FILL(s); });
    bindSearch();
  } catch {} finally { _zone.style.opacity = ''; }
}

document.addEventListener('submit', e => {
  if (e.target.id !== 'filter-form') return;
  e.preventDefault();
  const s = e.submitter;
  refreshFeed(s && s.name ? { [s.name]: s.value } : null);
});

document.addEventListener('change', e => {
  const t = e.target;
  if (!e.isTrusted || !t.classList) return;
  if ((t.classList.contains('loccounty') || t.classList.contains('locarea'))
      && t.closest('#filter-form')) refreshFeed();
});

document.addEventListener('click', e => {
  const a = e.target.closest && e.target.closest('.pager a');
  if (!a || a.classList.contains('dis')) return;
  e.preventDefault();
  fetch(a.href + (a.href.includes('?') ? '&' : '?') + 'partial=1')
    .then(r => r.text()).then(async html => {
      _zone.innerHTML = html;
      history.replaceState(null, '', a.href);
      _zone.querySelectorAll('.rv').forEach(el => el.classList.add('in'));
      _zone.querySelectorAll('.loccounty').forEach(s => { if (s.value && window.LOC_FILL) LOC_FILL(s); });
      bindSearch();
      _zone.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }).catch(() => location.href = a.href);
});
</script>
<?php bottom_nav('discover'); site_footer(); ?>
