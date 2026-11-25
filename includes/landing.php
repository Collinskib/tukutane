<?php
// Logged-out marketing landing — Nipange-branded interpretation of the
// Solid State layout language (HTML5 UP). Self-contained document: its own
// stylesheet, no shared layout helpers beyond escapers.

function landing_page(): void {
    $ev = '/index.php?view=discover';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0f2b20">
<title>Nipange — Life is happening. Don't miss out.</title>
<meta name="description" content="Discover businesses, events, campus activities, hangouts and new experiences around you. Nipange is Kenya's business, event & social discovery platform — free to join.">
<link rel="canonical" href="<?= e(absolute_url('/')) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Nipange">
<meta property="og:title" content="Nipange — Life is happening. Don't miss out.">
<meta property="og:description" content="From local businesses and campus experiences to hangouts, live events and new discoveries — find what's happening around you.">
<meta property="og:image" content="<?= e(absolute_url('/assets/landing/hero.jpg')) ?>">
<meta property="og:url" content="<?= e(absolute_url('/')) ?>">
<meta name="twitter:card" content="summary_large_image">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" href="/assets/icons/icon-192.png">
<link rel="apple-touch-icon" href="/assets/icons/icon-192.png">
<link rel="preload" as="image" href="/assets/landing/hero.jpg">
<link rel="stylesheet" href="/assets/landing.css?v=8">
</head>
<body class="lp">

<header class="lp-nav" id="lpnav">
  <a class="lp-logo" href="/index.php"><img src="/assets/icons/logo.png" alt="Nipange"><span>Nipange<i>.</i></span></a>
  <nav class="lp-links" aria-label="Main">
    <a href="<?= e($ev) ?>">Explore</a>
    <a href="/about.php">About</a>
    <a href="/auth.php">Log in</a>
    <a class="lp-join" href="/auth.php?mode=register">Get started</a>
  </nav>
  <button class="lp-burger" id="lpburger" aria-label="Open menu" aria-expanded="false" aria-controls="lpmenu"><span></span><span></span><span></span></button>
</header>
<div class="lp-menu" id="lpmenu" hidden>
  <a href="<?= e($ev) ?>">Explore events</a>
  <a href="/about.php">About</a>
  <a href="/auth.php">Log in</a>
  <a class="lp-join" href="/auth.php?mode=register">Get started</a>
</div>

<main>
  <!-- ============ hero ============ -->
  <section class="lp-hero">
    <img class="lp-hero-img" src="/assets/landing/hero.jpg" alt="" fetchpriority="high" decoding="async" aria-hidden="true">
    <img class="lp-hero-img" src="/assets/landing/students.jpg" alt="" decoding="async" aria-hidden="true">
    <img class="lp-hero-img" src="/assets/landing/spots.jpg" alt="" decoding="async" aria-hidden="true">
    <img class="lp-hero-img" src="/assets/landing/festival.jpg" alt="" decoding="async" aria-hidden="true">
    <img class="lp-hero-img" src="/assets/landing/stage.jpg" alt="" decoding="async" aria-hidden="true">
    <div class="lp-hero-veil" aria-hidden="true"></div>
    <div class="lp-hero-in lp-rev">
      <p class="lp-eyebrow">Kenya's business, event &amp; social discovery platform</p>
      <h1>Life is happening.<br>Don't miss out.</h1>
      <p class="lp-lead">From local businesses and campus experiences to hangouts and live events — find what's happening around you. Your next favourite spot could be just around the corner.</p>
      <form class="lp-search" action="/index.php" method="get" role="search">
        <input type="hidden" name="view" value="discover">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2.2"/><path d="M16.5 16.5 21 21" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
        <input name="q" type="search" placeholder="Search events, businesses, places…" aria-label="Search events and businesses" autocomplete="off">
        <button type="submit">Search</button>
      </form>
      <div class="lp-ctas">
        <a class="lp-btn lp-btn-hot" href="<?= e($ev) ?>">Explore what's happening</a>
        <a class="lp-btn lp-btn-ghost" href="/auth.php?mode=register">Join Nipange</a>
      </div>
      <p class="lp-tag">Discover. Connect. Experience more.</p>
    </div>
    <span class="lp-scrollhint" aria-hidden="true"></span>
  </section>

  <!-- ============ discovery categories ============ -->
  <section class="lp-sec lp-s1 lp-light">
    <div class="lp-wrap">
      <h2 class="lp-rev">There's always something <em>happening</em>.</h2>
      <p class="lp-sub lp-rev">Sometimes the best plans are the ones you didn't know existed. Discover businesses, events, activities and experiences happening around you.</p>
      <div class="lp-cards">
        <a class="lp-card lp-rev" href="<?= e($ev) ?>">
          <span class="lp-card-img"><img src="/assets/landing/laptops.jpg" alt="People walking along a busy Nairobi street" loading="lazy" decoding="async"></span>
          <span class="lp-card-body"><b>Around you</b><small>Find interesting happenings in your area.</small><i>Browse events →</i></span>
        </a>
        <a class="lp-card lp-rev" href="/venues.php" style="--d:80ms">
          <span class="lp-card-img"><img src="/assets/landing/spots.jpg" alt="A vendor's fruit stall at a local market" loading="lazy" decoding="async"></span>
          <span class="lp-card-body"><b>Local businesses</b><small>Shops, cafés and spots near you — find them, message the owners.</small><i>See businesses →</i></span>
        </a>
        <a class="lp-card lp-rev" href="/institutions.php" style="--d:160ms">
          <span class="lp-card-img"><img src="/assets/landing/students.jpg" alt="Two stylish young women outside a campus building" loading="lazy" decoding="async"></span>
          <span class="lp-card-body"><b>Campus life</b><small>Stay in the loop with activities and events around your institution.</small><i>See institutions →</i></span>
        </a>
        <a class="lp-card lp-rev" href="<?= e($ev) ?>&amp;sort=popular" style="--d:240ms">
          <span class="lp-card-img"><img src="/assets/landing/stage.jpg" alt="An excited crowd cheering at a live event" loading="lazy" decoding="async"></span>
          <span class="lp-card-body"><b>What's trending</b><small>Discover experiences people are talking about.</small><i>Popular now →</i></span>
        </a>
      </div>
    </div>
  </section>

  <!-- ============ campus ============ -->
  <section class="lp-sec lp-s2 lp-split-sec">
    <div class="lp-wrap lp-split">
      <figure class="lp-shot lp-rev"><img src="/assets/landing/crew.jpg" alt="Young people dancing together outdoors" loading="lazy" decoding="async"></figure>
      <div class="lp-split-txt lp-rev">
        <h2>Campus life deserves <em>more than lectures</em>.</h2>
        <p class="lp-sub">There's more to campus than classrooms. Discover activities, meet new people and stay connected to what's happening around your institution.</p>
        <ul class="lp-ticks">
          <li>Events and activities around tertiary institutions</li>
          <li>Find your people inside campus communities</li>
          <li>Share what's on — post events where you can</li>
        </ul>
        <a class="lp-btn lp-btn-line" href="/institutions.php">Explore institutions</a>
      </div>
    </div>
  </section>

  <!-- ============ experiences ============ -->
  <section class="lp-sec lp-s3 lp-split-sec lp-light">
    <div class="lp-wrap lp-split lp-flip">
      <div class="lp-split-txt lp-rev">
        <h2>Make room for <em>better experiences</em>.</h2>
        <p class="lp-sub">Find your next hangout, a new favourite spot or get involved in what's happening around you. Nipange makes discovering businesses and experiences easier.</p>
        <a class="lp-btn lp-btn-line" href="<?= e($ev) ?>">See what's on</a>
      </div>
      <div class="lp-duo lp-rev">
        <img class="lp-duo-a" src="/assets/landing/festival.jpg" alt="A colourful crowd celebrating at a festival" loading="lazy" decoding="async">
        <img class="lp-duo-b" src="/assets/landing/roof.jpg" alt="Friends talking together in the city at night" loading="lazy" decoding="async">
      </div>
    </div>
  </section>

  <!-- ============ how it works ============ -->
  <section class="lp-sec lp-s4">
    <div class="lp-wrap">
      <h2 class="lp-rev">Your next experience is <em>closer than you think</em>.</h2>
      <div class="lp-steps">
        <div class="lp-step lp-rev"><b>01</b><h3>Discover</h3><p>Explore businesses, events and activities around you.</p></div>
        <div class="lp-step lp-rev" style="--d:90ms"><b>02</b><h3>Find your vibe</h3><p>Browse experiences that interest you.</p></div>
        <div class="lp-step lp-rev" style="--d:180ms"><b>03</b><h3>Get involved</h3><p>Join in, participate and make the most of what's happening.</p></div>
      </div>
    </div>
  </section>

  <!-- ============ final CTA ============ -->
  <section class="lp-cta">
    <img class="lp-cta-img" src="/assets/landing/cta.jpg" alt="" loading="lazy" decoding="async" aria-hidden="true">
    <div class="lp-cta-veil" aria-hidden="true"></div>
    <div class="lp-cta-in lp-rev">
      <h2>Don't just hear about it <em>afterwards</em>.</h2>
      <p class="lp-sub">The best experiences start when you show up. Discover what's happening and find your next reason to get out there.</p>
      <div class="lp-ctas">
        <a class="lp-btn lp-btn-hot" href="/auth.php?mode=register">Get started — it's free</a>
        <a class="lp-btn lp-btn-ghost" href="<?= e($ev) ?>">Explore events</a>
      </div>
    </div>
  </section>
</main>

<footer class="lp-foot">
  <div class="lp-wrap lp-foot-in">
    <div class="lp-foot-brand">
      <a class="lp-logo" href="/index.php"><img src="/assets/icons/logo.png" alt=""><span>Nipange<i>.</i></span></a>
      <p>Find businesses, events, people and experiences around you — across Kenya.</p>
    </div>
    <nav class="lp-foot-links" aria-label="Footer">
      <div><strong>Explore</strong>
        <a href="<?= e($ev) ?>">Events</a>
        <a href="/venues.php">Businesses</a>
        <a href="/about.php">About Nipange</a>
        <a href="/institutions.php">Institutions</a>
      </div>
      <div><strong>Company</strong>
        <a href="/privacy.php">Contact &amp; privacy</a>
        <a href="/terms.php">Terms &amp; conditions</a>
        <a href="/guidelines.php">Community guidelines</a>
      </div>
      <div><strong>Account</strong>
        <a href="/auth.php">Log in</a>
        <a href="/auth.php?mode=register">Sign up</a>
      </div>
    </nav>
  </div>
  <p class="lp-foot-fine">Made for Kenya · Nipange <?= date('Y') ?> · Images: Unsplash · Inspired by HTML5 UP</p>
</footer>

<script>
(function(){
  var nav=document.getElementById('lpnav');
  var menu=document.getElementById('lpmenu');
  var burger=document.getElementById('lpburger');
  burger.addEventListener('click',function(){
    var open=menu.hasAttribute('hidden');
    if(open){menu.removeAttribute('hidden');burger.setAttribute('aria-expanded','true');document.body.classList.add('lp-menuopen');}
    else{menu.setAttribute('hidden','');burger.setAttribute('aria-expanded','false');document.body.classList.remove('lp-menuopen');}
  });
  menu.querySelectorAll('a').forEach(function(a){a.addEventListener('click',function(){menu.setAttribute('hidden','');document.body.classList.remove('lp-menuopen');burger.setAttribute('aria-expanded','false');});});
  var onScroll=function(){nav.classList.toggle('lp-solid',scrollY>40);};
  addEventListener('scroll',onScroll,{passive:true});onScroll();
  if('IntersectionObserver' in window){
    var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){e.target.classList.add('in');io.unobserve(e.target);}});},{threshold:.12});
    document.querySelectorAll('.lp-rev').forEach(function(el){io.observe(el);});
  }else{
    document.querySelectorAll('.lp-rev').forEach(function(el){el.classList.add('in');});
  }
})();
</script>
</body>
</html>
<?php }
