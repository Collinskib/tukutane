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

// Minimal HTTP helpers for the Google OAuth round-trip (no composer on shared hosting).
function http_request(string $url, ?array $post = null, array $headers = []): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw = curl_exec($ch);
    $err = curl_errno($ch);
    curl_close($ch);
    if ($err || $raw === false) return null;
    $j = json_decode($raw, true);
    return is_array($j) ? $j : null;
}

// Download bytes (for a remote avatar) with a hard size cap.
function http_download(string $url, int $maxBytes): string|false {
    $ch = curl_init($url);
    $buf = '';
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) use (&$buf, $maxBytes) {
            $buf .= $chunk;
            return strlen($buf) > $maxBytes ? 0 : strlen($chunk);
        },
    ]);
    $ok = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($ok && $code === 200 && strlen($buf) <= $maxBytes) ? $buf : false;
}

function oauth_redirect_uri(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/oauth-google.php';
}

// Fetch a remote avatar image and store it in uploads/ like upload_image does.
function oauth_avatar(string $url): string {
    if ($url === '' || !preg_match('~^https://~', $url)) return '';
    $raw = http_download($url, 4 * 1024 * 1024);
    if ($raw === false || $raw === '') return '';
    $tmp = tempnam(sys_get_temp_dir(), 'av');
    if (!$tmp || file_put_contents($tmp, $raw) === false) return '';
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        default      => null,
    };
    if (!$ext || !@getimagesize($tmp)) { unlink($tmp); return ''; }
    $name = bin2hex(random_bytes(10)) . '.' . $ext;
    $dir = dirname(__DIR__) . '/uploads';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (!rename($tmp, "$dir/$name")) { unlink($tmp); return ''; }
    return $name;
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
    $dir = dirname(__DIR__) . '/uploads';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    // server-side compress when GD is available: cap at 1600px, re-encode ~82%
    if (extension_loaded('gd')) {
        [$w, $h] = getimagesize($f['tmp_name']);
        $src = match ($ext) {
            'jpg'  => imagecreatefromjpeg($f['tmp_name']),
            'png'  => imagecreatefrompng($f['tmp_name']),
            'webp' => imagecreatefromwebp($f['tmp_name']),
        };
        if ($src) {
            $scale = $w > 1600 ? 1600 / $w : 1;
            $nw = (int) round($w * $scale); $nh = (int) round($h * $scale);
            $dst = imagecreatetruecolor($nw, $nh);
            if ($ext !== 'jpg') { // keep alpha for png/webp
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
            }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            $name = bin2hex(random_bytes(10)) . '.' . $ext;
            $ok = match ($ext) {
                'jpg'  => imagejpeg($dst, "$dir/$name", 82),
                'png'  => imagepng($dst, "$dir/$name", 6),
                'webp' => imagewebp($dst, "$dir/$name", 82),
            };
            imagedestroy($src); imagedestroy($dst);
            if ($ok) return $name;
        }
    }

    $name = bin2hex(random_bytes(10)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], "$dir/$name")) return '!Could not store the image.';
    return $name;
}

// chat attachment: images go through upload_image (compresses when GD is
// present); documents are whitelisted by extension + sniffed MIME, stored as-is.
// Returns [filename, display_name, kind] or "!<error>".
function upload_attachment(string $field): array|string {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return '';
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) return '!Upload failed — try a smaller file.';
    if ($f['size'] > 10 * 1024 * 1024) return '!File must be under 10 MB.';
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (str_starts_with($mime, 'image/')) {
        $r = upload_image($field);
        if (str_starts_with($r, '!')) return $r;
        return [$r, basename($f['name']), 'image'];
    }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf' => 'application/pdf', 'txt' => 'text/plain', 'csv' => 'text/csv',
                'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'zip' => 'application/zip'];
    // office/pdf mimes can vary; accept the sniffed type OR octet-stream for office/zip
    $mimeOk = isset($allowed[$ext]) && ($mime === $allowed[$ext]
        || in_array($mime, ['application/octet-stream', 'application/x-zip-compressed',
                            'text/plain', 'application/zip', 'application/x-empty'], true)
        || str_starts_with($mime, 'application/vnd.'));
    if (!$mimeOk) return '!Only images, PDFs and Office documents.';
    $name = bin2hex(random_bytes(10)) . '.' . $ext;
    $dir = dirname(__DIR__) . '/uploads';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (!move_uploaded_file($f['tmp_name'], "$dir/$name")) return '!Could not store the file.';
    return [$name, mb_substr(basename($f['name']), 0, 180), 'file'];
}

function whatsapp_share(string $title, string $url): string {
    return 'https://wa.me/?text=' . rawurlencode($title . ' — ' . $url);
}

function absolute_url(string $path): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'nipange.co.ke') . '/' . ltrim($path, '/');
}

// ---------- SMTP send (no composer; plain socket AUTH LOGIN) ----------

function smtp_send(string $to, string $subject, string $body): bool {
    if (!defined('SMTP_USER') || !defined('SMTP_PASS')) return false;
    $host = defined('SMTP_HOST') ? SMTP_HOST : 'mail.nipange.co.ke';
    $port = defined('SMTP_PORT') ? SMTP_PORT : 465;
    $from = SMTP_USER;

    $fp = @fsockopen(($port === 465 ? 'ssl://' : '') . $host, $port, $en, $es, 15);
    if (!$fp) return false;
    $read = fn() => fgets($fp, 515);
    $say  = function(string $s) use ($fp, $read): string {
        fwrite($fp, $s . "\r\n");
        $resp = '';
        while (($line = fgets($fp, 515)) !== false) {
            $resp .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') break; // last line of reply
        }
        return $resp;
    };
    $read();                                   // greeting
    $say('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'nipange.co.ke'));
    if (strpos($say('AUTH LOGIN'), '334') !== 0) { fclose($fp); return false; }
    $say(base64_encode($from));
    if (strpos($say(base64_encode(SMTP_PASS)), '235') !== 0) { fclose($fp); return false; }
    $say("MAIL FROM:<$from>");
    $say("RCPT TO:<$to>");
    if (strpos($say('DATA'), '354') !== 0) { fclose($fp); return false; }
    // full RFC headers — missing Date/Message-ID are heavy outbound spam flags
    $domain = $_SERVER['HTTP_HOST'] ?? 'nipange.co.ke';
    $headers = "Date: " . date('r') . "\r\n"
             . "Message-ID: <" . bin2hex(random_bytes(12)) . "@$domain>\r\n"
             . 'From: Nipange <' . $from . ">\r\n"
             . "Reply-To: no-reply@$domain\r\n"
             . "To: <$to>\r\n"
             . "Subject: $subject\r\n"
             . "MIME-Version: 1.0\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n"
             . "Content-Transfer-Encoding: 8bit\r\n";
    $say($headers . "\r\n" . $body . "\r\n.");
    $ok = strpos($say('QUIT'), '') !== false;
    fclose($fp);
    return true;
}
