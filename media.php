<?php
// View-once media gate (web session). Streams a private attachment exactly
// once to the recipient, then it is gone for everyone. The sender never
// gets the bytes — they already have the original.
require_once __DIR__ . '/includes/bootstrap.php';

if (!user()) { http_response_code(401); exit('login'); }
serve_view_once((int) ($_GET['m'] ?? 0), user_id());
