<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

page_head('Privacy policy');
site_header();
?>
<main>
  <div class="card"><div class="body" style="max-width:70ch">
    <h1>Privacy policy</h1>
    <p class="muted">Last updated: 21 November 2026</p>

    <p>Nipange ("we", "us") runs nipange.co.ke and the Nipange mobile app — a service that helps people discover events, meet new friends, and join groups across Kenya. This policy explains what we collect, why, and the choices you have. It is written to meet the Kenya Data Protection Act, 2019.</p>

    <h2>What we collect</h2>
    <ul>
      <li><b>Account details</b> — name, email address, password (stored only as a hash), and — if you sign in with Google — your Google account ID and basic profile.</li>
      <li><b>Profile information</b> — gender, age bracket, bio, interests, and photos you choose to upload.</li>
      <li><b>Location choices</b> — the county, sub-county and estate/neighbourhood you select. We never collect precise GPS coordinates.</li>
      <li><b>Activity</b> — events you create, join or save, RSVPs and waitlists, friend requests, group memberships, venue listings, invites, and reports.</li>
      <li><b>Messages</b> — direct messages and group/event chats, including attachments you send.</li>
      <li><b>Technical data</b> — IP address, device/browser type, and cookies needed to keep you signed in.</li>
    </ul>

    <h2>How we use it</h2>
    <ul>
      <li>Run the service: show you relevant people, events, groups and venues near your chosen location.</li>
      <li>Authenticate you and keep your account secure, including email verification and password resets.</li>
      <li>Send notifications you asked for — invites, messages, RSVP updates, event reminders.</li>
      <li>Moderate the platform — investigating reports, abuse, and safety issues.</li>
      <li>Improve the product through aggregate, non-identifying usage analysis.</li>
    </ul>

    <h2>What other users can see</h2>
    <p>Your name, photo, bio, interests, age bracket, gender, and location (county/sub-county level, and estate if you added one) are visible to other signed-in members as part of discovery. You can limit discoverability in your profile settings. Your email address is never shown publicly.</p>

    <h2>Sharing</h2>
    <p>We do not sell your data. We share it only with:</p>
    <ul>
      <li><b>Service providers</b> who run Nipange — our web host and our email provider (for verification and notification emails).</li>
      <li><b>Google</b>, when you choose "Continue with Google", subject to Google's own privacy policy.</li>
      <li><b>Authorities</b> where required by law or to protect members' safety.</li>
    </ul>

    <h2>Cookies</h2>
    <p>We use a session cookie to keep you signed in and a service-worker cache so the app loads fast. We do not run advertising or cross-site tracking cookies.</p>

    <h2>Your rights</h2>
    <p>You can view and edit your profile data any time in the app. Under the Data Protection Act you may also request a copy of your data, correction, or deletion of your account — email <a href="mailto:hello@nipange.co.ke">hello@nipange.co.ke</a> and we'll respond within 30 days. Deleting your account removes your profile, messages and event history; content others saved (e.g. screenshots) is outside our control.</p>

    <h2>Retention & security</h2>
    <p>We keep your data while your account is active, and for up to 12 months after closure for safety and legal obligations, after which it is deleted or anonymised. We use TLS encryption in transit and hash passwords; no system is perfectly secure, so please use a unique password.</p>

    <h2>Children</h2>
    <p>Nipange is for adults 18 and over. We do not knowingly collect data from minors and will delete any such account we find.</p>

    <h2>Changes & contact</h2>
    <p>If we change this policy we'll post the new version here with an updated date. Questions or complaints: <a href="mailto:hello@nipange.co.ke">hello@nipange.co.ke</a>. You may also lodge a complaint with the Office of the Data Protection Commissioner, Kenya.</p>
  </div></div>
</main>
<?php site_footer(); ?>
