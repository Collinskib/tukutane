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
    unset($p['event'], $p['partial']);
    foreach ($drop as $d) unset($p[$d]);
    $p = array_merge($p, $add);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null && $v !== 'All interests' && $v !== 'All Nairobi');
    return basename($_SERVER['PHP_SELF']) . ($p ? '?' . http_build_query($p) : '');
}

// Resized-derivative URL for an uploads/ file (see img.php). The endpoint
// falls back to the original when GD is missing or the source is small.
function img_url(string $f, int $s): string {
    return '/img.php?f=' . rawurlencode($f) . '&s=' . $s;
}

function avatar_html(?array $u, string $cls = '', bool $eager = false): string {
    $name = $u['name'] ?? 'Guest';
    if (!empty($u['photo'])) {
        [$s, $px] = $cls === 'lg' ? [160, 72] : ($cls === 'sm' ? [80, 34] : [80, 38]);
        return '<img class="avatar ' . $cls . '" src="' . img_url($u['photo'], $s)
             . '" width="' . $px . '" height="' . $px . '" alt="' . e($name)
             . '" decoding="async"' . ($eager ? '' : ' loading="lazy"') . '>';
    }
    $initial = mb_strtoupper(mb_substr($name, 0, 1));
    return '<span class="avatar ph ' . $cls . '" aria-label="' . e($name) . '">' . e($initial) . '</span>';
}

function cover_html(array $event, string $emoji): string {
    if (!empty($event['cover'])) {
        return '<div class="ribbon cover"><img src="' . img_url($event['cover'], 800) . '" alt="" loading="lazy" decoding="async"></div>';
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

// Private media dir (view-once files): denied to the web via .htaccess,
// streamed only through media.php / the api media route after auth checks.
function private_upload_dir(): string {
    $dir = dirname(__DIR__) . '/uploads-private';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        @file_put_contents("$dir/.htaccess", "Require all denied\nDeny from all\n");
    }
    return $dir;
}

// Sanitize an audio/video/file ext: keep it only if whitelisted, else pick by mime.
function media_safe_ext(string $ext, string $mime, string $kind): string {
    $ok = [
        'image' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'],
        'audio' => ['m4a', 'mp3', 'aac', 'ogg', 'opus', 'wav', 'webm', 'caf'],
        'video' => ['mp4', 'webm', 'mov', '3gp', 'mkv'],
        'file'  => ['pdf', 'txt', 'csv', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip'],
    ][$kind] ?? [];
    if (in_array($ext, $ok, true)) return $ext;
    $byMime = [
        'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a', 'audio/mpeg' => 'mp3',
        'audio/aac' => 'aac', 'audio/ogg' => 'ogg', 'application/ogg' => 'ogg',
        'audio/opus' => 'opus', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav',
        'audio/webm' => 'webm', 'audio/x-caf' => 'caf',
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
        'image/gif' => 'gif', 'image/avif' => 'avif',
        'video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov',
        'video/3gpp' => '3gp', 'video/x-matroska' => 'mkv',
    ];
    return $byMime[$mime] ?? 'bin';
}

// Returns [filename, display_name, kind] or "!<error>".
// kind: image | audio | video | file. $private stores outside web reach
// (view-once media — served only via the gated media route).
function upload_attachment(string $field, bool $private = false): array|string {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return '';
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) return '!Upload failed — try a smaller file.';
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $kind = str_starts_with($mime, 'image/') ? 'image'
        : (str_starts_with($mime, 'audio/') || $mime === 'application/ogg' ? 'audio'
        : (str_starts_with($mime, 'video/') ? 'video' : 'file'));
    $cap = ['image' => 10, 'audio' => 15, 'video' => 60, 'file' => 10][$kind] * 1024 * 1024;
    if ($f['size'] > $cap) return '!File must be under ' . intdiv($cap, 1048576) . ' MB.';
    if ($kind === 'image' && !$private) {
        $r = upload_image($field);
        if (str_starts_with($r, '!')) return $r;
        return [$r, basename($f['name']), 'image'];
    }
    $ext = media_safe_ext(strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)), $mime, $kind);
    if ($kind === 'file') {
        $allowed = ['pdf' => 'application/pdf', 'txt' => 'text/plain', 'csv' => 'text/csv',
                    'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    'zip' => 'application/zip'];
        $origExt = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        // office/pdf mimes can vary; accept the sniffed type OR octet-stream for office/zip
        $mimeOk = isset($allowed[$origExt]) && ($mime === $allowed[$origExt]
            || in_array($mime, ['application/octet-stream', 'application/x-zip-compressed',
                                'text/plain', 'application/zip', 'application/x-empty'], true)
            || str_starts_with($mime, 'application/vnd.'));
        if (!$mimeOk) return '!Only images, PDFs, Office documents, audio and video.';
        $ext = $origExt;
    }
    $name = bin2hex(random_bytes(10)) . '.' . $ext;
    $dir = $private ? private_upload_dir() : dirname(__DIR__) . '/uploads';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (!move_uploaded_file($f['tmp_name'], "$dir/$name")) return '!Could not store the file.';
    return [$name, mb_substr(basename($f['name']), 0, 180), $kind];
}

// Stream a view-once attachment to $uid, marking it consumed on first
// authorized fetch. Sends its own headers and exits.
function serve_view_once(int $mid, int $uid): never {
    $m = $mid ? message_media($mid, $uid) : null;
    if (!$m) { http_response_code(404); exit('gone'); }
    $isSender = (int) $m['sender_id'] === $uid;
    if ($isSender || $m['consumed_at']) { http_response_code(410); exit('gone'); }
    $path = private_upload_dir() . '/' . basename((string) $m['attachment']);
    if (!is_file($path)) { http_response_code(410); exit('gone'); }
    db()->prepare('UPDATE messages SET consumed_at = NOW() WHERE id = :i AND consumed_at IS NULL')
        ->execute([':i' => $m['id']]);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-store');
    header('Content-Disposition: inline; filename="' . basename($path) . '"');
    readfile($path);
    exit;
}

// Presence: stamp last_seen_at, throttled to one write per ~45s per user.
function touch_presence(int $uid): void {
    try {
        db()->prepare('UPDATE users SET last_seen_at = NOW() WHERE id = :i
                       AND (last_seen_at IS NULL OR last_seen_at < NOW() - INTERVAL 45 SECOND)')
            ->execute([':i' => $uid]);
    } catch (Throwable) { /* column may not exist on un-migrated dev DBs */ }
}

// Presence payload for a user row: online if seen in the last 90s.
function presence_out(?array $u): array {
    $seen = $u['last_seen_at'] ?? null;
    return ['online' => $seen ? (time() - strtotime($seen) < 90) : false, 'lastSeen' => $seen];
}

// Human label: "online" | "last seen 8:15 PM" | "last seen yesterday 8:15 PM" | "last seen Mon 8:15 PM"
function last_seen_label(?string $seen): string {
    if (!$seen) return '';
    $t = strtotime($seen);
    if (time() - $t < 90) return 'online';
    $day = date('Y-m-d') === date('Y-m-d', $t) ? '' : (date('Y-m-d', strtotime('-1 day')) === date('Y-m-d', $t) ? 'yesterday ' : date('D ', $t));
    return 'last seen ' . $day . date('g:i A', $t);
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

// Cascading county → sub-county → estate picker. Emits the shared JS once per page.
// 'filter' mode shows only county + sub-county with "All" options.
function location_picker(string $county = '', string $area = '', string $estate = '', string $mode = 'required'): void {
    static $jsDone = false;
    $filter = $mode === 'filter';
    echo '<div class="locpick">';
    echo '<select name="county" class="loccounty"' . ($mode === 'required' ? ' required' : '') . '>';
    echo '<option value="">' . ($filter ? 'All counties' : 'County…') . '</option>';
    foreach (counties() as $c)
        echo '<option' . ($county === $c ? ' selected' : '') . '>' . e($c) . '</option>';
    echo '</select>';
    echo '<select name="area" class="locarea" data-current="' . e($area) . '"' . ($mode === 'required' ? ' required' : '') . '>';
    echo '<option value="">' . ($filter ? 'All areas' : 'Sub-county…') . '</option></select>';
    if (!$filter) {
        echo '<select name="estate" class="locest" data-current="' . e($estate) . '">';
        echo '<option value="">Estate / neighbourhood (optional)</option></select>';
        echo '<input class="locnew" name="estate_new" maxlength="60" placeholder="Type your estate / neighbourhood" hidden>';
    }
    echo '</div>';
    if ($jsDone) return;
    $jsDone = true;
    echo '<script>var LOC_MAP=' . json_encode(areas_map(), JSON_UNESCAPED_UNICODE) . ';
(function(){
  function setOpts(sel,list,first,cur,add){
    sel.innerHTML="";var d=document.createElement("option");d.value="";d.textContent=first;sel.appendChild(d);
    list.forEach(function(n){var o=document.createElement("option");o.value=n;o.textContent=n;if(n===cur)o.selected=true;sel.appendChild(o);});
    if(add){var o=document.createElement("option");o.value="__new";o.textContent="＋ Add my estate…";sel.appendChild(o);}
  }
  function fillEst(w){
    var c=w.querySelector(".loccounty"),a=w.querySelector(".locarea"),
        e=w.querySelector(".locest");if(!e)return;
    var es=(LOC_MAP[c.value]||{})[a.value]||[];
    setOpts(e,es,e.dataset.first||"Estate / neighbourhood (optional)",e.dataset.current,true);
    e.dataset.current="";
    var inp=w.querySelector(".locnew");if(inp)inp.hidden=e.value!=="__new";
  }
  function fillSub(sel){
    var w=sel.closest(".locpick"),a=w.querySelector(".locarea"),
        cur=a.dataset.current,subs=Object.keys(LOC_MAP[sel.value]||{}),
        first=a.options[0]?a.options[0].textContent:"";
    setOpts(a,subs,first,cur,false);
    a.dataset.current="";
    fillEst(w);
  }
  document.addEventListener("change",function(ev){
    var t=ev.target;if(!t.classList)return;
    if(t.classList.contains("loccounty"))fillSub(t);
    if(t.classList.contains("locarea"))fillEst(t.closest(".locpick"));
    if(t.classList.contains("locest")){
      var inp=t.closest(".locpick").querySelector(".locnew");
      if(inp){inp.hidden=t.value!=="__new";if(!inp.hidden)inp.focus();}
    }
  });
  document.querySelectorAll(".loccounty").forEach(function(s){if(s.value)fillSub(s);});
  window.LOC_FILL=fillSub;
})();</script>';
}

// Reads POSTed county+area+estate, handling the "add my estate" custom path.
// Estates are stored under (county, sub) and deduped by the composite PK.
// Returns [area, estate] — each falls back when invalid/absent.
function resolve_location(string $county, string $areaFallback = '', string $estateFallback = ''): array {
    $a = trim(mb_substr($_POST['area'] ?? '', 0, 60));
    $area = area_valid($county, $a) ? $a : $areaFallback;
    $e = trim(mb_substr($_POST['estate'] ?? '', 0, 60));
    if ($e === '__new') {
        $e = trim(mb_substr($_POST['estate_new'] ?? '', 0, 60));
        $e = preg_replace('/[<>{}\\[\\]\\\\]/', '', $e);
        if ($e !== '' && $area !== '' && in_array($county, counties(), true)) {
            db()->prepare('INSERT IGNORE INTO areas (county, sub, name, sort, custom) VALUES (:c, :s, :n, 50, 1)')
                ->execute([':c' => $county, ':s' => $area, ':n' => $e]);
            return [$area, $e];
        }
        return [$area, ''];
    }
    if ($e === '' && array_key_exists('estate', $_POST)) return [$area, ''];
    return [$area, estate_valid($county, $area, $e) ? $e : $estateFallback];
}
