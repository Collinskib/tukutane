<?php
// View + utility helpers shared by every page.

function e(?string $s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function when(string $eventAt): string {
    return (new DateTimeImmutable($eventAt, new DateTimeZone('Africa/Nairobi')))
            ->format('D, M j, g:i A') . ' EAT';
}

function time_ago(string $ts): string {
    $d = time() - strtotime($ts);
    if ($d < 60) return 'just now';
    if ($d < 3600) return intdiv($d, 60) . 'm ago';
    if ($d < 86400) return intdiv($d, 3600) . 'h ago';
    if ($d < 86400 * 7) return intdiv($d, 86400) . 'd ago';
    return date('M j', strtotime($ts));
}

function price(int $cost): string {
    return $cost > 0 ? 'KSh ' . number_format($cost) : 'Free entry';
}

function flash(?string $msg = null, string $kind = 'ok'): ?array {
    if ($msg !== null) {
        $_SESSION['flash'] = [$msg, $kind];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function redirect(string $to): never {
    header('Location: ' . $to);
    exit;
}

// Rebuild a URL that keeps current filters minus $drop, plus $add.
function keep(array $drop = [], array $add = []): string {
    $p = $_GET;
    unset($p['event']);
    foreach ($drop as $d) unset($p[$d]);
    $p = array_merge($p, $add);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null && $v !== 'All interests' && $v !== 'All Nairobi');
    return basename($_SERVER['PHP_SELF']) . ($p ? '?' . http_build_query($p) : '');
}

function avatar_html(?array $u, string $cls = ''): string {
    $name = $u['name'] ?? 'Guest';
    if (!empty($u['photo'])) {
        return '<img class="avatar ' . $cls . '" src="/uploads/' . e($u['photo']) . '" alt="' . e($name) . '">';
    }
    $initial = mb_strtoupper(mb_substr($name, 0, 1));
    return '<span class="avatar ph ' . $cls . '" aria-label="' . e($name) . '">' . e($initial) . '</span>';
}

function cover_html(array $event, string $emoji): string {
    if (!empty($event['cover'])) {
        return '<div class="ribbon cover"><img src="/uploads/' . e($event['cover']) . '" alt="" loading="lazy"></div>';
    }
    [, $ribbon] = categories()[$event['category']] ?? ['📅', '#e4e6db'];
    return '<div class="ribbon" style="background:' . e($ribbon) . '"><span>'
         . e($event['category']) . '</span><span>' . ($categories_emoji = (categories()[$event['category']] ?? ['📅'])[0]) . '</span></div>';
}

// Image upload → returns filename in uploads/, or error string prefixed '!'
function upload_image(string $field): string {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return '';
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) return '!Upload failed — try a smaller image.';
    if ($f['size'] > 4 * 1024 * 1024) return '!Image must be under 4 MB.';
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        default      => null,
    };
    if (!$ext || !@getimagesize($f['tmp_name'])) return '!Only JPG, PNG or WebP images.';
    $name = bin2hex(random_bytes(10)) . '.' . $ext;
    $dir = dirname(__DIR__) . '/uploads';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (!move_uploaded_file($f['tmp_name'], "$dir/$name")) return '!Could not store the image.';
    return $name;
}

function whatsapp_share(string $title, string $url): string {
    return 'https://wa.me/?text=' . rawurlencode($title . ' — ' . $url);
}

function absolute_url(string $path): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'nipange.co.ke') . '/' . ltrim($path, '/');
}
