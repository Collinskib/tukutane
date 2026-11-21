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

// Load/refresh the session user; runs on every request via bootstrap.
function auth_refresh(): void {
    if (empty($_SESSION['uid'])) {
        unset($_SESSION['user']);
        return;
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
}

function attempt_login(string $email, string $password): ?string {
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

function register_user(string $email, string $password, string $name, string $area = '', string $estate = '', string $county = '', string $gender = '', string $age_bracket = ''): ?string {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return 'Enter a valid email.';
    if (mb_strlen($password) < 8) return 'Password needs at least 8 characters.';
    $name = trim(mb_substr($name, 0, 60));
    if ($name === '') return 'Tell us your name.';
    if (!in_array($gender, GENDERS, true)) return 'Pick your gender.';
    if (!in_array($age_bracket, AGE_BRACKETS, true)) return 'Pick your age bracket.';
    if (find_user_by_email($email)) return 'That email already has an account — log in instead.';
    db()->prepare('INSERT INTO users (email, pass_hash, name, area, estate, county, gender, age_bracket)
                   VALUES (:e, :p, :n, :a, :es, :c, :g, :ab)')
        ->execute([
            ':e' => mb_strtolower($email),
            ':p' => password_hash($password, PASSWORD_DEFAULT),
            ':n' => $name,
            ':a' => area_valid($county, $area) ? $area : '',
            ':es' => estate_valid($county, $area, $estate) || $estate !== '' ? $estate : '',
            ':c' => in_array($county, counties(), true) ? $county : '',
            ':g' => $gender,
            ':ab' => $age_bracket,
        ]);
    send_verification($email, $name);
    return attempt_login($email, $password);
}

function logout(): void {
    unset($_SESSION['uid'], $_SESSION['user'], $_SESSION['claimed']);
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
