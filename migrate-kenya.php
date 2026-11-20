<?php
// One-off migration: Kenya-wide locations. Admin-gated; delete after use.
require_once __DIR__ . '/includes/bootstrap.php';
require_login('migrate-kenya.php');
if (!is_admin()) exit('admin only');

// 1) areas gains a county column + composite key
try {
    db()->exec("ALTER TABLE areas
        ADD COLUMN county VARCHAR(40) NOT NULL DEFAULT 'Nairobi' FIRST,
        DROP PRIMARY KEY, ADD PRIMARY KEY (county, name)");
    echo "OK   areas.county + composite key\n";
} catch (Throwable $e) {
    echo (str_contains($e->getMessage(), 'Duplicate') ? "SKIP areas.county\n" : 'FAIL areas: ' . $e->getMessage() . "\n");
}

// 2) county columns on entities
foreach (['users', 'gatherings', 'grps', 'venues'] as $t) {
    try {
        db()->exec("ALTER TABLE $t ADD COLUMN county VARCHAR(40) NULL AFTER area");
        echo "OK   $t.county\n";
    } catch (Throwable $e) {
        echo (str_contains($e->getMessage(), 'Duplicate') ? "SKIP $t.county\n" : "FAIL $t: " . $e->getMessage() . "\n");
    }
}
foreach (['users', 'gatherings', 'grps', 'venues'] as $t) {
    db()->exec("UPDATE $t SET county = 'Nairobi' WHERE county IS NULL");
}

// 3) seed all 47 counties + sub-counties (Nairobi neighbourhoods preserved above)
$map = array(
        "Uasin Gishu" => ["Ainabkoi", "Kapseret", "Kesses", "Moiben", "Soy", "Turbo"],
        "Kericho" => ["Ainamoi", "Belgut", "Buret", "Kipkelion East", "Kipkelion West", "Sigowet/Soin"],
        "Nandi" => ["Aldai", "Chesumei", "Emgwen", "Mosop", "Nandi Hills", "Tinderet"],
        "Siaya" => ["Alego Usonga", "Bondo", "Gem", "Rarieda", "Ugenya", "Ugunja"],
        "Migori" => ["Awendo", "Kuria East", "Kuria West", "Nyatike", "Rongo", "Suna East", "Suna West", "Uriri"],
        "Nakuru" => ["Bahati", "Gilgil", "Kuresoi North", "Kuresoi South", "Molo", "Naivasha", "Nakuru Town East", "Nakuru Town West", "Njoro", "Rongai", "Subukia"],
        "Garissa" => ["Balambala", "Dadaab", "Dujis", "Fafi", "Ijara", "Lagdera"],
        "Mandera" => ["Banissa", "Lafey", "Mandera East", "Mandera North", "Mandera South", "Mandera West"],
        "Baringo" => ["Baringo Central", "Baringo North", "Baringo South", "Eldama Ravine", "Mogotio", "Tiaty"],
        "Kisii" => ["Bobasi", "Bomachoge Borabu", "Bomachoge Chache", "Bonchari", "Kitutu Chache North", "Kitutu Chache South", "Nyaribari Chache", "Nyaribari Masaba", "South Mugirango"],
        "Bomet" => ["Bomet Central", "Bomet East", "Chepalungu", "Konoin", "Sotik"],
        "Nyamira" => ["Borabu", "Kitutu Masaba", "North Mugirango", "West Mugirango"],
        "Busia" => ["Budalangi", "Butula", "Funyula", "Matayos", "Nambale", "Teso North", "Teso South"],
        "Bungoma" => ["Bumula", "Kabuchai", "Kanduyi", "Kimilili", "Mt. Elgon", "Sirisia", "Tongaren", "Webuye East", "Webuye West"],
        "Tana River" => ["Bura", "Galole", "Garsen"],
        "Kakamega" => ["Butere", "Ikolomani", "Khwisero", "Likuyani", "Lugari", "Lurambi", "Malava", "Matungu", "Mumias East", "Mumias West", "Navakholo", "Shinyalu"],
        "Meru" => ["Buuri", "Cental Imenti", "Igembe Central", "Igembe North", "Igembe South", "North Imenti", "South Imenti", "Tigania East", "Tigania West"],
        "Mombasa" => ["Changamwe", "Jomvu", "Kisauni", "Likoni", "Mvita", "Nyali"],
        "Trans Nzoia" => ["Cherangany", "Endebess", "Kiminini", "Kwanza", "Saboti"],
        "Nairobi" => ["Dagoretti", "Embakasi Central", "Embakasi East", "Embakasi North", "Embakasi South", "Embakasi West", "Kamukunji", "Kasarani", "Kibra", "Kilimani", "Langata", "Makadara", "Mathare", "Roysambu", "Ruaraka", "Starehe", "Westlands"],
        "Wajir" => ["Eldas", "Tarbaj", "Wajir East", "Wajir North", "Wajir South", "Wajir West"],
        "Vihiga" => ["Emuhaya", "Hamisi", "Luanda", "Sabatia", "Vihiga"],
        "Narok" => ["Emurua Dikirr", "Kilgoris", "Narok East", "Narok North", "Narok South", "Narok West"],
        "Embu" => ["Gachoka", "Manyatta", "Runyenjes", "Siakago"],
        "Kilifi" => ["Ganze", "Kaloleni", "Kilifi North", "Kilifi South", "Magarini", "Malindi", "Rabai"],
        "Murang'a" => ["Gatanga", "Kandara", "Kangema", "Kigumo", "Kiharu", "Maragwa", "Mathioya"],
        "Kiambu" => ["Gatundu North", "Gatundu South", "Githunguri", "Juja", "Kabete", "Kiambaa", "Kiambu", "Kikuyu", "Lari", "Limuru", "Ruiru", "Thika Town"],
        "Kirinyaga" => ["Gichugu", "Kirinyaga Central", "Mwea", "Ndia"],
        "Homa Bay" => ["Homa Bay", "Kabondo Kasipul", "Karachuonyo", "Kasipul", "Ndhiwa", "Rangwe", "Suba North", "Suba South"],
        "Isiolo" => ["Isiolo North", "Isiolo South"],
        "West Pokot" => ["Kacheliba", "Kapenguria", "Pokot South", "Sigor"],
        "Makueni" => ["Kaiti", "Kibwezi East", "Kibwezi West", "Kilome", "Makueni", "Mbooni"],
        "Kajiado" => ["Kajiado Central", "Kajiado East", "Kajiado North", "Kajiado South", "Kajiado West"],
        "Machakos" => ["Kangundo", "Kathiani", "Machakos Town", "Masinga", "Matungulu", "Mavoko", "Mwala", "Yatta"],
        "Elgeyo-Marakwet" => ["Keiyo North", "Keiyo South", "Marakwet East", "Marakwet West"],
        "Nyeri" => ["Kieni", "Mathira", "Mukurweni", "Nyeri Town", "Othaya", "Tetu"],
        "Kwale" => ["Kinango", "Lunga Lunga", "Matuga", "Msambweni"],
        "Nyandarua" => ["Kinangop", "Kipipiri", "Ndaragwa", "Ol Jorok", "Ol Kalou"],
        "Kisumu" => ["Kisumu Central", "Kisumu East", "Kisumu West", "Muhoroni", "Nyakach", "Nyando", "Seme"],
        "Kitui" => ["Kitui Central", "Kitui East", "Kitui Rural", "Kitui South", "Kitui West", "Mwingi East", "Mwingi North", "Mwingi West"],
        "Laikipia" => ["Laikipia East", "Laikipia North", "Laikipia West"],
        "Marsabit" => ["Laisamis", "Moyale", "North Horr", "Saku"],
        "Lamu" => ["Lamu East", "Lamu West"],
        "Turkana" => ["Loima", "Turkana Central", "Turkana East", "Turkana North", "Turkana South", "Turkana West"],
        "Tharaka-Nithi" => ["Maara", "Nithi", "Tharaka"],
        "Taita Taveta" => ["Mwatate", "Taveta", "Voi", "Wundanyi"],
        "Samburu" => ["Samburu East", "Samburu North", "Samburu West"]
    );
$ins = db()->prepare('INSERT IGNORE INTO areas (county, name, sort) VALUES (:c, :n, 0)');
$n = 0;
foreach ($map as $county => $areas)
    foreach ($areas as $a) { $ins->execute([':c' => $county, ':n' => $a]); $n++; }
echo "seeded ~$n area rows\n";
echo "done\n";
