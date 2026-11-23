<?php
// Institution typeahead + campus list for the institution picker.
// ?q=kenya        -> [{id,name,type,type_label,county,town}]
// ?id=42          -> {institution:{...}, campuses:[{id,name,county,town}]}
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $inst = institution_get((int) $_GET['id']);
    if (!$inst || $inst['status'] !== 'active') { echo json_encode(['error' => 'not found']); exit; }
    $camps = array_map(fn($c) => [
        'id' => (int) $c['id'], 'name' => $c['name'],
        'county' => $c['county'], 'town' => $c['town'],
    ], campuses_for((int) $inst['id']));
    echo json_encode([
        'institution' => [
            'id' => (int) $inst['id'], 'name' => $inst['name'],
            'type' => $inst['type'], 'type_label' => institution_type_label($inst['type']),
            'county' => $inst['county'], 'town' => $inst['town'],
        ],
        'campuses' => $camps,
    ]);
    exit;
}

$q = trim((string) ($_GET['q'] ?? ''));
$rows = institutions_search($q);
echo json_encode(array_map(fn($r) => [
    'id' => (int) $r['id'], 'name' => $r['name'],
    'type' => $r['type'], 'type_label' => institution_type_label($r['type']),
    'county' => $r['county'], 'town' => $r['town'],
], $rows));
