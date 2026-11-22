<?php
// Resized, disk-cached derivatives of uploads/.
//   /img.php?f=<upload filename>&s=<max px>
// Thumbs are generated once per (file, size) into uploads/thumbs/<s>/ and
// served with far-future immutable caching. Falls back to the original file
// when GD is missing or the source is already smaller than requested.

const IMG_SIZES = [80, 160, 480, 800];

$f = $_GET['f'] ?? '';
$s = (int) ($_GET['s'] ?? 0);

if (!in_array($s, IMG_SIZES, true)
    || !preg_match('/^[a-f0-9]{20,64}\.(jpe?g|png|webp)$/', $f)) {
    http_response_code(404);
    exit;
}

$dir = __DIR__ . '/uploads';
$src = "$dir/$f";
if (!is_file($src)) { http_response_code(404); exit; }

$thumb = "$dir/thumbs/$s/$f";

if (!is_file($thumb) && extension_loaded('gd')) {
    $info = @getimagesize($src);
    if ($info && ($info[0] > $s || $info[1] > $s)) {
        $scale = min($s / $info[0], $s / $info[1]);
        $nw = max(1, (int) round($info[0] * $scale));
        $nh = max(1, (int) round($info[1] * $scale));
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        $srcImg = match ($ext) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($src),
            'png'         => @imagecreatefrompng($src),
            'webp'        => @imagecreatefromwebp($src),
            default       => false,
        };
        if ($srcImg) {
            $dst = imagecreatetruecolor($nw, $nh);
            if ($ext !== 'jpg' && $ext !== 'jpeg') {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
            }
            imagecopyresampled($dst, $srcImg, 0, 0, 0, 0, $nw, $nh, $info[0], $info[1]);
            if (!is_dir(dirname($thumb))) mkdir(dirname($thumb), 0755, true);
            // write-then-rename so concurrent first hits never serve a partial file
            $tmp = $thumb . '.' . getmypid() . '.tmp';
            $ok = match ($ext) {
                'jpg', 'jpeg' => imagejpeg($dst, $tmp, 82),
                'png'         => imagepng($dst, $tmp, 6),
                'webp'        => imagewebp($dst, $tmp, 82),
                default       => false,
            };
            imagedestroy($srcImg);
            imagedestroy($dst);
            if ($ok && !@rename($tmp, $thumb)) { @unlink($tmp); }
            elseif (!$ok) @unlink($tmp);
        }
    }
}

$path = is_file($thumb) ? $thumb : $src;
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = match ($ext) {
    'jpg', 'jpeg' => 'image/jpeg',
    'png'         => 'image/png',
    'webp'        => 'image/webp',
    default       => 'application/octet-stream',
};

$etag = '"' . $f . '-' . $s . '-' . filemtime($path) . '-' . filesize($path) . '"';
if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}
header("Content-Type: $mime");
header('Content-Length: ' . filesize($path));
header("ETag: $etag");
header('Cache-Control: public, max-age=31536000, immutable');
header('X-Content-Type-Options: nosniff');
readfile($path);
