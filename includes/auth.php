<?php
// Session auth — real accounts layered on top of the anonymous key.

function user(): ?array {
    return $_SESSION['user'] ?? null;
}

function user_id(): int {
    return (int) (user()['id'] ?? 0);
}

function is_admin(): bool {
    return (user()['role'] ?? '') === 'admin';
}

// ---------- persistent "remember me" cookies ----------
// Cookie = selector.token → remember_tokens row (sha256 of token only).
// The token rotates on every use; a token that doesn't match its selector's
// stored hash is treated as a replayed/stolen one and the row is deleted.

const REMEMBER_DAYS = 90;

function remember_cookie(string $val, int $expires): void {
    setcookie('NIPREMEMBER', $val, [
        'expires'  => $expires,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
}

function remember_issue(int $uid): void {
    $selector = bin2hex(random_bytes(8));
    $token    = bin2hex(random_bytes(32));
    try {
        db()->prepare('INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at)
                       VALUES (:u, :s, :h, DATE_ADD(NOW(), INTERVAL ' . REMEMBER_DAYS . ' DAY))')
            ->execute([':u' => $uid, ':s' => $selector, ':h' => hash('sha256', $token)]);
        db()->exec('DELETE FROM remember_tokens WHERE expires_at < NOW()');
        remember_cookie("$selector.$token", time() + REMEMBER_DAYS * 86400);
    } catch (Throwable) {
        // table not migrated yet — session login still works
    }
}

function remember_clear(): void {
    $c = $_COOKIE['NIPREMEMBER'] ?? '';
    if (preg_match('/^([a-f0-9]{16})\.([a-f0-9]{64})$/', $c, $m)) {
        try {
            db()->prepare('DELETE FROM remember_tokens WHERE selector = :s')
                ->execute([':s' => $m[1]]);
        } catch (Throwable) {}
    }
    remember_cookie('', 1);
    unset($_COOKIE['NIPREMEMBER']);
}

// Validate the remember cookie → returns the user id and rotates the token,
// or 0. Only called when the session has no user.
function remember_login(): int {
    $c = $_COOKIE['NIPREMEMBER'] ?? '';
    if (!preg_match('/^([a-f0-9]{16})\.([a-f0-9]{64})$/', $c, $m)) return 0;
    try {
        $q = db()->prepare('SELECT * FROM remember_tokens WHERE selector = :s');
        $q->execute([':s' => $m[1]]);
        $t = $q->fetch();
    } catch (Throwable) {
        return 0;   // table not migrated yet
    }
    if (!$t) { remember_cookie('', 1); return 0; }
    if (strtotime((string) $t['expires_at']) < time()
        || !hash_equals((string) $t['token_hash'], hash('sha256', $m[2]))) {
        db()->prepare('DELETE FROM remember_tokens WHERE id = :i')->execute([':i' => $t['id']]);
        remember_cookie('', 1);
        return 0;
    }
    $new = bin2hex(random_bytes(32));
    db()->prepare('UPDATE remember_tokens SET token_hash = :h, last_used = NOW(),
                   expires_at = DATE_ADD(NOW(), INTERVAL ' . REMEMBER_DAYS . ' DAY) WHERE id = :i')
        ->execute([':h' => hash('sha256', $new), ':i' => $t['id']]);
    remember_cookie($m[1] . '.' . $new, time() + REMEMBER_DAYS * 86400);
    return (int) $t['user_id'];
}

// Load/refresh the session user; runs on every request via bootstrap.
function auth_refresh(): void {
    if (empty($_SESSION['uid'])) {
        unset($_SESSION['user']);
        $uid = remember_login();
        if (!$uid) return;
        session_regenerate_id(true);
        $_SESSION['uid'] = $uid;
        $_SESSION['key'] = SESSION_KEY;
        unset($_SESSION['claimed']);
    }
    $u = find_user((int) $_SESSION['uid']);
    if (!$u || $u['status'] !== 'active') {
        logout();
        return;
    }
    $_SESSION['user'] = $u;
    // One-time: fold anonymous RSVPs/plans into the account
    if (empty($_SESSION['claimed'])) {
        db()->prepare('UPDATE rsvps SET user_id = :u WHERE session_key = :k AND user_id IS NULL')
            ->execute([':u' => $u['id'], ':k' => SESSION_KEY]);
        db()->prepare('UPDATE gatherings SET creator_id = :u WHERE creator = :k AND creator_id IS NULL')
            ->execute([':u' => $u['id'], ':k' => SESSION_KEY]);
        $_SESSION['claimed'] = true;
    }
    // Required signup fields missing (Google sign-ups, pre-demographics
    // accounts) — force completion before anything else.
    if (PHP_SAPI !== 'cli' && profile_incomplete($u)) {
        $path  = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $script = basename($path);
        if (!in_array($script, ['complete-profile.php', 'logout.php', 'verify.php'], true)
            && !str_starts_with($script, 'migrate-')
            && !str_contains($path, '/api/')
            && !str_contains($path, '/admin/')
            && !str_contains($path, '/cron/')) {
            $next = $_SESSION['cp_next'] ?? ($_SERVER['REQUEST_URI'] ?? 'index.php');
            unset($_SESSION['cp_next']);
            $host = basename(parse_url((string) $next, PHP_URL_PATH) ?: '');
            if (!preg_match('~^[a-z0-9_./?=&#%-]+$~i', (string) $next) || str_starts_with((string) $next, '//')
                || in_array($host, ['auth.php', 'oauth-google.php', 'complete-profile.php'], true)) {
                $next = 'index.php';
            }
            flash('One more step — tell us a bit about you.');
            redirect('complete-profile.php?next=' . urlencode((string) $next));
        }
    }
}

function profile_incomplete(array $u): bool {
    return !in_array($u['gender'] ?? '', GENDERS, true)
        || !in_array($u['age_bracket'] ?? '', AGE_BRACKETS, true);
}

function attempt_login(string $email, string $password, bool $remember = false): ?string {
    // soft rate limit: 8 tries per 10 min per session
    $_SESSION['tries'] = ($_SESSION['tries'] ?? 0);
    if ($_SESSION['tries'] >= 8 && ($_SESSION['tries_at'] ?? 0) > time() - 600) {
        return 'Too many attempts — wait a few minutes and try again.';
    }
    $u = find_user_by_email($email);
    if (!$u || !password_verify($password, $u['pass_hash'])) {
        $_SESSION['tries']++;
        $_SESSION['tries_at'] = time();
        return 'Wrong email or password.';
    }
    if ($u['status'] !== 'active') return 'This account is suspended.';
    session_regenerate_id(true);
    $_SESSION['uid'] = $u['id'];
    $_SESSION['key'] = SESSION_KEY; // survives regenerate for claiming
    unset($_SESSION['tries'], $_SESSION['tries_at'], $_SESSION['claimed']);
    if ($remember) {
        remember_issue($u['id']);
    } else {
        // not remembered — downgrade the 30-day persistent cookie (set for
        // anonymous visitors) to a browser-session cookie
        setcookie(session_name(), session_id(), [
            'expires'  => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
    }
    auth_refresh();
    return null;
}

const GENDERS      = ['Male', 'Female', 'Prefer not to say'];
const AGE_BRACKETS = ['18-24', '25-29', '30-34', '35-39', '40-44', '45-49', '50+'];

function send_verification(string $email, string $name): void {
    $token = bin2hex(random_bytes(24));
    db()->prepare('UPDATE users SET verify_token=:t WHERE email=:e')
        ->execute([':t' => $token, ':e' => mb_strtolower($email)]);
    $link = absolute_url('verify.php?t=' . $token);
    smtp_send($email, 'Verify your Nipange email',
          "Hi $name,\n\n"
          . "Karibu to Nipange — one last step. Confirm this email is yours by opening the link below:\n\n$link\n\n"
          . "If you didn't create this account, just ignore this email.\n\n"
          . "— The Nipange team\nhttps://nipange.co.ke");
}

function register_user(string $email, string $password, string $name, string $area = '', string $estate = '', string $county = '', string $gender = '', string $age_bracket = '', string $fun_fact = ''): ?string {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return 'Enter a valid email.';
    if (mb_strlen($password) < 8) return 'Password needs at least 8 characters.';
    $name = trim(mb_substr($name, 0, 60));
    if ($name === '') return 'Tell us your name.';
    if (!in_array($gender, GENDERS, true)) return 'Pick your gender.';
    if ($age_bracket !== '' && !in_array($age_bracket, AGE_BRACKETS, true)) return 'Pick your age bracket.';
    if (find_user_by_email($email)) return 'That email already has an account — log in instead.';
    db()->prepare('INSERT INTO users (email, pass_hash, name, area, estate, county, gender, age_bracket, fun_fact)
                   VALUES (:e, :p, :n, :a, :es, :c, :g, :ab, :ff)')
        ->execute([
            ':e' => mb_strtolower($email),
            ':p' => password_hash($password, PASSWORD_DEFAULT),
            ':n' => $name,
            ':a' => area_valid($county, $area) ? $area : '',
            ':es' => estate_valid($county, $area, $estate) || $estate !== '' ? $estate : '',
            ':c' => in_array($county, counties(), true) ? $county : '',
            ':g' => $gender,
            ':ab' => $age_bracket !== '' ? $age_bracket : null,
            ':ff' => trim(mb_substr($fun_fact, 0, 160)) ?: null,
        ]);
    send_verification($email, $name);
    return attempt_login($email, $password, true);
}

function logout(): void {
    unset($_SESSION['uid'], $_SESSION['user'], $_SESSION['claimed']);
    remember_clear();
}

function require_login(string $back = ''): void {
    if (!user()) {
        flash('Log in to continue.');
        redirect('auth.php?next=' . urlencode($back ?: ($_SERVER['REQUEST_URI'] ?? 'index.php')));
    }
}

function require_admin(): void {
    if (!is_admin()) {
        http_response_code(403);
        exit('Forbidden.');
    }
}
