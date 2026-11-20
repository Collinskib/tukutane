<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

page_head('Privacy policy');
site_header();
?>
<main class="ev-wrap">
  <h2>Privacy policy</h2>
  <p class="rv"><small>Last updated: November 2025</small></p>

  <div class="card rv" style="--d:60ms;margin-bottom:14px"><div class="body">
    <h3>What Nipange collects</h3>
    <p>Nipange helps you find friends and gatherings near you. To do that we store:</p>
    <ul>
      <li><b>Account details</b> — your name, email address, and password (stored only as a secure hash), or your Google name, email and profile photo if you sign in with Google.</li>
      <li><b>Profile info</b> — the neighbourhood, bio, interests and photo you choose to share.</li>
      <li><b>Activity</b> — gatherings you create, RSVPs, saves, waitlist entries, friend requests, messages and reports you send.</li>
    </ul>
    </div>
  </div>

  <div class="card rv" style="--d:120ms;margin-bottom:14px"><div class="body">
    <h3>What we show to other members</h3>
    <ul>
      <li>Your name, photo, neighbourhood, interests and bio are visible to members so they can find and connect with you.</li>
      <li>We never show your exact address — only the neighbourhood you picked.</li>
      <li>Your email address is never shown to other members.</li>
      <li>Private gatherings are visible only to invited members.</li>
    </ul>
    </div>
  </div>

  <div class="card rv" style="--d:180ms;margin-bottom:14px"><div class="body">
    <h3>What we never do</h3>
    <ul>
      <li>We do not sell your data.</li>
      <li>We do not read your private messages except where needed to investigate a report you filed.</li>
      <li>We do not share your information with advertisers.</li>
    </ul>
    </div>
  </div>

  <div class="card rv" style="--d:240ms;margin-bottom:14px"><div class="body">
    <h3>Sign in with Google</h3>
    <p>If you choose "Continue with Google", we receive your name, email address and profile photo from Google to create or sign in to your account. We do not get access to your emails, contacts, calendar or files.</p>
    </div>
  </div>

  <div class="card rv" style="--d:300ms;margin-bottom:14px"><div class="body">
    <h3>Your choices</h3>
    <ul>
      <li>You can edit or delete your profile details anytime on your profile page.</li>
      <li>You can set your profile visibility to control whether you appear in People nearby.</li>
      <li>To delete your account entirely, contact us at <a href="mailto:hello@nipange.co.ke">hello@nipange.co.ke</a>.</li>
    </ul>
    </div>
  </div>

  <div class="card rv" style="--d:360ms;margin-bottom:14px"><div class="body">
    <h3>Contact</h3>
    <p>Questions about this policy or your data: <a href="mailto:hello@nipange.co.ke">hello@nipange.co.ke</a></p>
    </div>
  </div>
</main>
<?php bottom_nav(); site_footer(); ?>
