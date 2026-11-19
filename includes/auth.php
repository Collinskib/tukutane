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

function register_user(string $email, string $password, string $name, string $area = ''): ?string {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return 'Enter a valid email.';
    if (mb_strlen($password) < 8) return 'Password needs at least 8 characters.';
    $name = trim(mb_substr($name, 0, 60));
    if ($name === '') return 'Tell us your name.';
    if (find_user_by_email($email)) return 'That email already has an account — log in instead.';
    db()->prepare('INSERT INTO users (email, pass_hash, name, area) VALUES (:e, :p, :n, :a)')
        ->execute([
            ':e' => mb_strtolower($email),
            ':p' => password_hash($password, PASSWORD_DEFAULT),
            ':n' => $name,
            ':a' => in_array($area, areas(), true) ? $area : '',
        ]);
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
