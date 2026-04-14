<?php
require_once 'config/db.php';
require_once 'lib/xlsx.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['excel'])) {
    header('Location: index.php'); exit;
}

$file = $_FILES['excel'];
$ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$tmp  = $file['tmp_name'];
$rows = [];

if ($ext === 'xlsx') {
    $rows = readXlsx($tmp);
} elseif ($ext === 'csv') {
    if (($fh = fopen($tmp, 'r')) !== false) {
        while (($row = fgetcsv($fh)) !== false) $rows[] = $row;
        fclose($fh);
    }
}

if (count($rows) < 2) {
    header('Location: index.php?msg=error'); exit;
}

// Map headers from row 0
$headers = array_map('trim', $rows[0]);
$map = [
    'ID'                            => 'id',
    'Position_X(m)'                 => 'position_x',
    'Position_Y(m)'                 => 'position_y',
    'Position_Z(m)'                 => 'position_z',
    'Tree_height(m)'                => 'tree_height',
    'Crown_diameter(m)'             => 'crown_diameter',
    'Crown_area(m2)'                => 'crown_area',
    'Crown_length(m)'               => 'crown_length',
    'Crown_surface_area(m2)'        => 'crown_surface_area',
    'Crown_volume(m3)'              => 'crown_volume',
    'Diameter_at_breast_height(cm)' => 'dbh',
    'Coniferous_tree_volume(m3)'    => 'coniferous_tree_volume',
    'Broad_leaved_tree_volume(m3)'  => 'broad_leaved_tree_volume',
    'Stem Biomass(kg)'              => 'stem_biomass',
    'Branch Biomass(kg)'            => 'branch_biomass',
    'Leaf Biomass(kg)'              => 'leaf_biomass',
    'Fruit Biomass(kg)'             => 'fruit_biomass',
    'Root Biomass(kg)'              => 'root_biomass',
    'Total Tree Biomass(kg)'        => 'total_tree_biomass',
    'Carbon Stock(kg)'              => 'carbon_stock',
];

// Build column index from headers
$colIndex = [];
foreach ($headers as $i => $h) {
    if (isset($map[$h])) $colIndex[$map[$h]] = $i;
}

$db = db();

// Get upload name from POST or generate from filename
// Get upload name from POST (now required)
$upload_name = trim($_POST['upload_name'] ?? '');

// Validate required field
if (empty($upload_name)) {
    header('Location: index.php?msg=missing_name');
    exit;
}

$description = $_POST['description'] ?? '';

// Create new upload record
$uploadStmt = $db->prepare("INSERT INTO uploads (upload_name, description, file_name, row_count, is_active) VALUES (?, ?, ?, ?, 0)");
$uploadStmt->execute([$upload_name, $description, $file['name'], count($rows) - 1]);
$upload_id = $db->lastInsertId();

// SQL for trees table
// SQL for trees table - REMOVED ON DUPLICATE KEY UPDATE
$treeSql = "INSERT INTO trees (
            id, position_x, position_y, position_z,
            tree_height, crown_diameter, crown_area, crown_length,
            crown_surface_area, crown_volume, dbh,
            coniferous_tree_volume, broad_leaved_tree_volume,
            stem_biomass, branch_biomass, leaf_biomass,
            fruit_biomass, root_biomass, total_tree_biomass, carbon_stock,
            upload_id
        )
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

$treeStmt = $db->prepare($treeSql);

$cols = [
    'id', 'position_x', 'position_y', 'position_z',
    'tree_height', 'crown_diameter', 'crown_area', 'crown_length',
    'crown_surface_area', 'crown_volume', 'dbh',
    'coniferous_tree_volume', 'broad_leaved_tree_volume',
    'stem_biomass', 'branch_biomass', 'leaf_biomass',
    'fruit_biomass', 'root_biomass', 'total_tree_biomass', 'carbon_stock'
];

$imported = 0;
$errors   = 0;

foreach (array_slice($rows, 1) as $row) {
    if (empty($row[0])) continue;
    
    $vals = [];
    foreach ($cols as $col) {
        $idx    = $colIndex[$col] ?? null;
        $vals[] = ($idx !== null && isset($row[$idx]) && $row[$idx] !== '')
                  ? $row[$idx]
                  : null;
    }
    $vals[] = $upload_id; // Add upload_id
    
    try {
        $treeStmt->execute($vals);
        $imported++;
    } catch (Exception $e) {
        $errors++;
        continue;
    }
}

// Set this upload as active (or keep previous active based on user choice)
$setActive = $_POST['set_active'] ?? '0';
if ($setActive == '1') {
    $db->prepare("UPDATE uploads SET is_active = 0")->execute();
    $db->prepare("UPDATE uploads SET is_active = 1 WHERE upload_id = ?")->execute([$upload_id]);
}

if ($imported > 0) {
    header("Location: index.php?msg=uploaded&count=$imported&upload_id=$upload_id");
} else {
    header("Location: index.php?msg=error");
}