<?php
// Mobile-app API bootstrap: Bearer-token auth on top of the site's session
// auth + JSON body merged into $_POST so shared helpers (resolve_location,
// rsvp, send_message, …) work unchanged.

require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$API_IN = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
    $raw = file_get_contents('php://input');
    if ($raw && ($j = json_decode($raw, true)) && is_array($j)) {
        $API_IN = $j;
        $_POST = array_merge($_POST, $j);
    }
}

function pin(string $k, $d = '') {
    global $API_IN;
    return $API_IN[$k] ?? ($_GET[$k] ?? ($_POST[$k] ?? $d));
}

function jout(array $d): never { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function jerr(string $m, int $code = 400): never { http_response_code($code); jout(['error' => $m]); }

// ---------- token auth ----------

function api_user(): ?array {
    if (user()) return user();
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (!$h && function_exists('apache_request_headers')) {
        $hdrs = array_change_key_case(apache_request_headers());
        $h = $hdrs['authorization'] ?? '';
    }
    if (!preg_match('/Bearer\s+([a-f0-9]{64})/i', $h, $m)) return null;
    $hash = hash('sha256', strtolower($m[1]));
    $t = db()->prepare('SELECT user_id FROM api_tokens WHERE token_hash = :h AND expires_at > NOW()');
    $t->execute([':h' => $hash]);
    $uid = (int) $t->fetchColumn();
    if (!$uid) return null;
    $u = find_user($uid);
    if (!$u || $u['status'] !== 'active') return null;
    $_SESSION['uid'] = $uid;
    $_SESSION['user'] = $u;
    $_SESSION['claimed'] = true;
    db()->prepare('UPDATE api_tokens SET last_used = NOW() WHERE token_hash = :h')->execute([':h' => $hash]);
    return $u;
}

function api_need(): array {
    $u = api_user();
    if (!$u) jerr('Login required.', 401);
    touch_presence((int) $u['id']);
    return $u;
}

function api_issue_token(int $uid): string {
    $token = bin2hex(random_bytes(32));
    db()->prepare('INSERT INTO api_tokens (user_id, token_hash, created_at, expires_at)
                   VALUES (:u, :h, NOW(), DATE_ADD(NOW(), INTERVAL 180 DAY))')
        ->execute([':u' => $uid, ':h' => hash('sha256', $token)]);
    return $token;
}

// ---------- uploads from base64 (mirror upload_image/upload_attachment) ----------

function api_store_image(string $b64): string {
    if ($b64 === '') return '';
    if (str_contains($b64, ',')) $b64 = substr($b64, strrpos($b64, ',') + 1); // data-url safe
    $bin = base64_decode($b64, true);
    if ($bin === false) return '!Bad image data.';
    if (strlen($bin) > 4 * 1024 * 1024) return '!Image must be under 4 MB.';
    $tmp = tempnam(sys_get_temp_dir(), 'up');
    file_put_contents($tmp, $bin);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $ext = match ($mime) { 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', default => null };
    if (!$ext || !@getimagesize($tmp)) { unlink($tmp); return '!Only JPG, PNG or WebP images.'; }
    $dir = dirname(__DIR__) . '/uploads';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    if (extension_loaded('gd')) {
        [$w, $h] = getimagesize($tmp);
        $src = match ($ext) {
            'jpg'  => imagecreatefromjpeg($tmp),
            'png'  => imagecreatefrompng($tmp),
            'webp' => imagecreatefromwebp($tmp),
        };
        if ($src) {
            $scale = $w > 1600 ? 1600 / $w : 1;
            $nw = (int) round($w * $scale); $nh = (int) round($h * $scale);
            $dst = imagecreatetruecolor($nw, $nh);
            if ($ext !== 'jpg') { imagealphablending($dst, false); imagesavealpha($dst, true); }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            $name = bin2hex(random_bytes(10)) . '.' . $ext;
            $ok = match ($ext) {
                'jpg'  => imagejpeg($dst, "$dir/$name", 82),
                'png'  => imagepng($dst, "$dir/$name", 6),
                'webp' => imagewebp($dst, "$dir/$name", 82),
            };
            imagedestroy($src); imagedestroy($dst); unlink($tmp);
            if ($ok) return $name;
        }
    }

    $name = bin2hex(random_bytes(10)) . '.' . $ext;
    if (!rename($tmp, "$dir/$name")) { unlink($tmp); return '!Could not store the image.'; }
    return $name;
}

// Returns [filename, display_name, kind] or "!<error>".
// $private stores the file outside web reach (view-once media).
function api_store_attachment(string $b64, string $name, bool $private = false): array|string {
    if ($b64 === '') return '';
    if (str_contains($b64, ',')) $b64 = substr($b64, strrpos($b64, ',') + 1);
    $bin = base64_decode($b64, true);
    if ($bin === false) return '!Bad file data.';
    if (strlen($bin) > 10 * 1024 * 1024) return '!File must be under 10 MB.';
    $tmp = tempnam(sys_get_temp_dir(), 'up');
    file_put_contents($tmp, $bin);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    if (str_starts_with($mime, 'image/') && !$private) {
        $r = api_store_image($b64);
        unlink($tmp);
        if (str_starts_with($r, '!')) return $r;
        return [$r, mb_substr(basename($name) ?: 'photo', 0, 180), 'image'];
    }
    $kind = str_starts_with($mime, 'image/') ? 'image'
        : (str_starts_with($mime, 'audio/') || $mime === 'application/ogg' ? 'audio'
        : (str_starts_with($mime, 'video/') ? 'video' : 'file'));
    if ($kind !== 'file') {
        $fname = bin2hex(random_bytes(10)) . '.' . media_safe_ext(strtolower(pathinfo($name, PATHINFO_EXTENSION)), $mime, $kind);
        $dir = $private ? private_upload_dir() : dirname(__DIR__) . '/uploads';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        if (!rename($tmp, "$dir/$fname")) { unlink($tmp); return '!Could not store the file.'; }
        return [$fname, mb_substr(basename($name), 0, 180), $kind];
    }
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowed = ['pdf' => 'application/pdf', 'txt' => 'text/plain', 'csv' => 'text/csv',
                'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'zip' => 'application/zip'];
    $mimeOk = isset($allowed[$ext]) && ($mime === $allowed[$ext]
        || in_array($mime, ['application/octet-stream', 'application/x-zip-compressed',
                            'text/plain', 'application/zip', 'application/x-empty'], true)
        || str_starts_with($mime, 'application/vnd.'));
    if (!$mimeOk) { unlink($tmp); return '!Only images, PDFs and Office documents.'; }
    $fname = bin2hex(random_bytes(10)) . '.' . $ext;
    $dir = dirname(__DIR__) . '/uploads';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (!rename($tmp, "$dir/$fname")) { unlink($tmp); return '!Could not store the file.'; }
    return [$fname, mb_substr(basename($name), 0, 180), 'file'];
}

// ---------- output shaping ----------

function pub_user(?array $u): ?array {
    if (!$u) return null;
    return [
        'id' => (int) $u['id'], 'name' => $u['name'], 'photo' => $u['photo'] ?? '',
        'place' => place_label($u), 'bio' => $u['bio'] ?? '', 'funFact' => $u['fun_fact'] ?? '',
        'interests' => $u['interests'] ?? '',
        'gender' => in_array($u['gender'] ?? '', ['Male', 'Female'], true) ? $u['gender'] : '',
        'age' => $u['age_bracket'] ?? '',
    ];
}

function ev_out(array $e): array {
    return [
        'id' => (int) $e['id'], 'title' => $e['title'], 'category' => $e['category'],
        'place' => place_label($e), 'venue' => $e['venue'],
        'area' => $e['area'] ?? '', 'county' => $e['county'] ?? '', 'estate' => $e['estate'] ?? '',
        'at' => $e['event_at'], 'atLabel' => date('D j M · g:i A', strtotime($e['event_at'])),
        'cost' => (int) $e['cost'],
        'going' => (isset($e['going_base']) || isset($e['real_rsvps']))
            ? (int) ($e['going_base'] ?? 0) + (int) ($e['real_rsvps'] ?? 0)
            : (int) ($e['going'] ?? 0),
        'capacity' => $e['capacity'] !== null ? (int) $e['capacity'] : null,
        'cover' => $e['cover'] ?? '', 'desc' => $e['description'] ?? '',
        'audience' => $e['audience'] ?? 'public',
        'inviteOnly' => (bool) ($e['invite_only'] ?? 0),
        'status' => $e['status'] ?? 'active',
        'mine' => (bool) ($e['mine'] ?? 0), 'joined' => (bool) ($e['joined'] ?? 0),
        'saved' => (bool) ($e['saved'] ?? 0),
        'hostId' => (int) ($e['creator_id'] ?? 0),
        'seriesId' => (int) ($e['series_id'] ?? 0),
        'seriesFreq' => $e['series_freq'] ?? '',
        'venueId' => (int) ($e['venue_id'] ?? 0),
        'featured' => (bool) ($e['featured'] ?? 0),
    ];
}

function msg_out(array $m, int $me): array {
    return [
        'id' => (int) $m['id'], 'conv' => (int) ($m['conversation_id'] ?? 0),
        'mine' => (int) $m['sender_id'] === $me,
        'body' => $m['body'], 'at' => $m['created_at'] ?? '',
        'time' => isset($m['created_at']) ? date('g:i A', strtotime($m['created_at'])) : '',
        'name' => $m['name'] ?? ($m['sender_name'] ?? ''),
        'photo' => $m['photo'] ?? ($m['sender_photo'] ?? ''),
        'att' => $m['attachment'] ?? null, 'attName' => $m['attachment_name'] ?? null,
        'attKind' => $m['attachment_kind'] ?? null,
        'viewOnce' => (bool) ($m['view_once'] ?? 0),
        'opened' => !empty($m['consumed_at']),
        'pinned' => (bool) ($m['pinned'] ?? 0),
        'read' => !empty($m['read_at']),
        'status' => !empty($m['read_at']) ? 'read' : (!empty($m['delivered_at'] ?? null) ? 'delivered' : 'sent'),
    ];
}
