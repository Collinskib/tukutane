<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

page_head('About', '<link rel="preload" as="image" href="/assets/about/hero.webp" fetchpriority="high">');
site_header('about');
?>
<main class="about">
  <section class="a-hero rv">
    <small>About Nipange</small>
    <h1>Find your people.<br>Discover your world.</h1>
    <p>Nipange brings people, local businesses and event organizers together.
       Discover what's happening around you, connect with your community and
       turn ordinary days into memorable experiences.</p>
    <nav class="a-toc" aria-label="Who Nipange is for">
      <a href="#people">🧑‍🤝‍🧑 People</a>
      <a href="#business">🏪 Businesses</a>
      <a href="#events">📅 Event organizers</a>
    </nav>
    <img class="a-hero-img" src="/assets/about/hero.webp" width="1200" height="1500"
         alt="Friends dancing and celebrating together on a green lawn" fetchpriority="high">
  </section>

  <section class="a-sec a-spot rv">
    <div class="a-spot-img">
      <img src="/assets/about/story.webp" width="1080" height="1080" loading="lazy" decoding="async"
           alt="Two young friends sharing pizza and laughing in a Nairobi café">
    </div>
    <div>
      <span class="a-eyebrow">Our story</span>
      <h2>Bringing communities closer, one connection at a time.</h2>
      <p>We believe there is more happening around us than we often realize.
         There are interesting people to meet, local businesses worth discovering,
         events waiting to be experienced and communities waiting to be built.</p>
      <p>Nipange exists to make those connections easier. Whether you're looking
         for something to do this weekend, discovering a business in your
         neighbourhood, finding people who share your interests or organizing
         your own gathering, Nipange gives you a place to make it happen.</p>
    </div>
  </section>

  <section class="a-sec a-feats rv">
    <span class="a-eyebrow">How it works</span>
    <h2>How Nipange brings people together</h2>
    <div class="a-feat-grid">
      <div class="a-feat">
        <span class="a-ic" aria-hidden="true">🧑‍🤝‍🧑</span>
        <h3>Discover people</h3>
        <p>Meet and connect with people around you. Discover communities,
           find shared interests and build meaningful connections.</p>
      </div>
      <div class="a-feat">
        <span class="a-ic" aria-hidden="true">🏪</span>
        <h3>Discover businesses</h3>
        <p>Find businesses around your area, explore what they offer and
           connect with the people behind them.</p>
      </div>
      <div class="a-feat">
        <span class="a-ic" aria-hidden="true">📅</span>
        <h3>Discover &amp; create events</h3>
        <p>Find interesting events happening around you — or create your own
           gathering and invite people nearby to join.</p>
      </div>
    </div>
  </section>

  <div class="a-sechead rv">
    <span class="a-eyebrow">For everyone</span>
    <h2>A platform for everyone.</h2>
  </div>

  <section class="a-sec a-spot rv" id="people">
    <div class="a-spot-img">
      <img src="/assets/about/people.webp" width="1080" height="1080" loading="lazy" decoding="async"
           alt="Two friends laughing together outdoors">
    </div>
    <div>
      <span class="a-eyebrow">For individuals</span>
      <h2>Find something — and someone — worth showing up for.</h2>
      <p>Looking for something to do? Discover people, activities and
         experiences around you. Join a hangout, find people who share your
         interests, and message the ones you click with.</p>
      <p>You can even create your own event — a Saturday hangout, a game night,
         a football meetup — and bring people together.</p>
    </div>
  </section>

  <section class="a-sec a-spot flip rv" id="business">
    <div class="a-spot-img">
      <img src="/assets/about/business.webp" width="1080" height="1080" loading="lazy" decoding="async"
           alt="A confident young businesswoman in Lagos">
    </div>
    <div>
      <span class="a-eyebrow">For businesses</span>
      <h2>Let your neighbourhood find you.</h2>
      <p>Give your business more visibility. Create a listing that shows what
         you offer, help people around your area discover you and make it
         easier for potential customers to reach you.</p>
    </div>
  </section>

  <section class="a-sec a-spot rv" id="events">
    <div class="a-spot-img">
      <img src="/assets/about/events.webp" width="1080" height="1080" loading="lazy" decoding="async"
           alt="A large crowd celebrating at an outdoor festival">
    </div>
    <div>
      <span class="a-eyebrow">For event organizers</span>
      <h2>Bring your events closer to the people who matter.</h2>
      <p>Publish your activities with all the details — photos, dates, location
         and how to join — and help interested people discover and take part.</p>
    </div>
  </section>

  <section class="a-vision rv">
    <span class="a-eyebrow" style="color:var(--accent-soft)">Our vision</span>
    <h2>Making every neighbourhood more connected.</h2>
    <p>We envision a world where discovering what's happening around you is
       effortless. Where local businesses are easier to find, people can meet
       others who share their interests, and anyone with an idea can bring
       people together.</p>
    <p>We want Nipange to become the place where local discovery turns into
       real-world connections and experiences.</p>
  </section>

  <section class="a-cta rv">
    <h2>Your next connection starts here.</h2>
    <p>Discover people, explore businesses and find something happening
       around you.</p>
    <div class="a-cta-btns">
      <a class="a-btn" href="/auth.php?mode=register">Join Nipange</a>
      <a class="a-btn ghost" href="/index.php">Explore events</a>
    </div>
  </section>
</main>
<?php bottom_nav(); site_footer(); ?>
