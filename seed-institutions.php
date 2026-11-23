<?php
// Institution seeder — imports the official directory (data/institutions.php)
// into institutions + campuses. Repeatable and non-destructive:
//   * institutions upsert by UNIQUE name with INSERT IGNORE — rows already in
//     the table (including manually corrected ones) are never overwritten.
//   * campuses insert "Main Campus" per institution, deduped by
//     UNIQUE(institution_id, name); lat/lng stay NULL (no invented coordinates).
// Run after migrate-institutions.php. Admin-gated; also runnable via CLI.
require_once __DIR__ . '/includes/bootstrap.php';
if (PHP_SAPI !== 'cli') {
    require_login('seed-institutions.php');
    if (!is_admin()) exit('admin only');
}

$rows = require __DIR__ . '/data/institutions.php';
$types = array_keys(INSTITUTION_TYPES);
$ins = db()->prepare('INSERT IGNORE INTO institutions (name, type, county, town, ref, source)
                      VALUES (:n, :t, :c, :tw, :r, :s)');
$find = db()->prepare('SELECT id FROM institutions WHERE name = :n');
$okTypes = array_flip($types);
$added = 0;
foreach ($rows as [$name, $type, $county, $town, $ref, $src]) {
    if (!isset($okTypes[$type]) || !in_array($src, ['cue', 'tveta'], true)) continue;
    $ins->execute([':n' => $name, ':t' => $type, ':c' => $county ?? '', ':tw' => $town,
                   ':r' => $ref, ':s' => $src]);
    $added += $ins->rowCount();
}

// seed each institution's main campus from the institution record
$camp = db()->prepare('INSERT IGNORE INTO campuses (institution_id, name, county, town)
                       VALUES (:i, "Main Campus", :c, :t)');
$camps = 0;
foreach (db()->query('SELECT id, county, town FROM institutions')->fetchAll(PDO::FETCH_ASSOC) as $i) {
    $camp->execute([':i' => $i['id'], ':c' => $i['county'], ':t' => $i['town']]);
    $camps += $camp->rowCount();
}

// satellite campuses for known multi-campus institutions (data/campuses.php)
$sat = db()->prepare('INSERT IGNORE INTO campuses (institution_id, name, county, town)
                      VALUES (:i, :n, :c, :t)');
$campusesExtra = require __DIR__ . '/data/campuses.php';
foreach ($campusesExtra as $instName => $list) {
    $find->execute([':n' => $instName]);
    $instId = $find->fetchColumn();
    if (!$instId) continue;
    foreach ($list as [$cn, $cc, $ct]) {
        $sat->execute([':i' => $instId, ':n' => $cn, ':c' => $cc, ':t' => $ct]);
        $camps += $sat->rowCount();
    }
}

$tot = db()->query('SELECT COUNT(*) FROM institutions')->fetchColumn();
$totC = db()->query('SELECT COUNT(*) FROM campuses')->fetchColumn();
echo "institutions: +$added new (total $tot) | campuses: +$camps new (total $totC)\n";
