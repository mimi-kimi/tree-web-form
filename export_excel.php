<?php
require_once 'config/db.php';
$pdo = db();

$upload_id = intval($_GET['upload_id'] ?? 0);
if (!$upload_id) {
    header('Location: index.php');
    exit;
}

// Get upload info
$uploadStmt = $pdo->prepare('SELECT upload_name FROM uploads WHERE upload_id = ?');
$uploadStmt->execute([$upload_id]);
$upload = $uploadStmt->fetch();

if (!$upload) {
    header('Location: index.php');
    exit;
}

// Get all trees with inspections
$stmt = $pdo->prepare('
    SELECT 
        t.*,
        i.*
    FROM trees t 
    LEFT JOIN inspections i ON i.tree_id = t.id AND i.upload_id = t.upload_id
    WHERE t.upload_id = ?
    ORDER BY t.id ASC
');
$stmt->execute([$upload_id]);
$data = $stmt->fetchAll();

// Set headers for Excel download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $upload['upload_name']) . '_inspections_' . date('Y-m-d') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Define headers
$headers = [
    'Tree ID', 'Inspection Tree ID', 'Height (m)', 'Crown Diameter (m)', 'DBH (cm)', 
    'Total Biomass (kg)', 'Carbon Stock (kg)', 'Inspection Status',
    'Client Name', 'Tree Location', 'Tree Species',
    'Topography Flat', 'Topography Slope',
    'Site None', 'Site Grade Change', 'Site Clearing', 'Site Hydrology Change', 'Site Root Cuts',
    'Soil Limited', 'Soil Saturated', 'Soil Shallow', 'Soil Compacted', 'Soil Pavement', 'Soil Normal',
    'Prevailing Wind', 'Weather Strong', 'Weather Rain', 'Weather Normal',
    'Vigor', 'Foliage None', 'Foliage Normal', 'Foliage Chlorotic', 'Foliage Chlorotic %', 
    'Foliage Necrotic', 'Foliage Necrotic %',
    'Species Failure Branches', 'Species Failure Trunk', 'Species Failure Roots', 'Species Failure Describe', 'Species Failure None',
    'Wind Exposure', 'Relative Crown Size', 'Crown Density',
    'Unbalanced Crown', 'Dead Twigs', 'Dead Twigs %', 'Dead Twigs Max DIA',
    'Broken Hangers', 'Broken Number', 'Broken Max DIA', 'Over Extended Branches',
    'Pruning Crown Cleaned', 'Pruning Thinned', 'Pruning Raised', 'Pruning Reduced',
    'Pruning Topped', 'Pruning Lion Tailed', 'Pruning Flush Cuts', 'Pruning Other', 'Pruning None',
    'Crown Cracks', 'Lightning Crown', 'Codominant', 'Included Bark Crown',
    'Weak Attachment', 'Cavity Crown', 'Cavity Crown %', 'Previous Branch Failures',
    'Dead Missing Bark Crown', 'Cankers Crown', 'Sapwood Decay Crown', 'Conks Crown', 'Heartwood Crown',
    'Trunk Dead Bark', 'Abnormal Bark', 'Codominant Stems', 'Included Bark Trunk',
    'Trunk Cracks', 'Sapwood Trunk', 'Cankers Trunk', 'Sap Ooze',
    'Lightning Trunk', 'Heartwood Trunk', 'Conks Trunk', 'Cavity Trunk', 'Cavity Trunk %',
    'Lean', 'Response Growth', 'Other Trunk Issues',
    'Collar Buried', 'Collar Depth', 'Stem Girdling',
    'Root Dead', 'Root Decay', 'Root Conks', 'Root Ooze',
    'Root Cracks', 'Cavity Root', 'Cavity Root %', 'Cut Damage Roots',
    'Root Plate Lifting', 'Soil Weakness', 'Root Response', 'Other Root Issues',
    'Mitigation No Action', 'Mitigation Clear Dead Branches', 'Mitigation Normal Pruning',
    'Mitigation Topping', 'Mitigation Tree Cut',
    'Notes', 'Prepared By (Signature/Title)', 'Preparer Name'
];

// Write headers
fputcsv($output, $headers);

// Write data rows
foreach ($data as $row) {
    $csvRow = [
        $row['id'],
        $row['tree_id'] ?? '',  // New field
        $row['tree_height'],
        $row['crown_diameter'],
        $row['dbh'],
        $row['total_tree_biomass'],
        $row['carbon_stock'],
        !empty($row['prepared_by']) ? 'Completed' : 'Incomplete',
        $row['client'] ?? '',
        $row['tree_location'] ?? '',
        $row['tree_species'] ?? '',
        $row['topography_flat'] ?? 0,
        $row['topography_slope'] ?? 0,
        $row['site_none'] ?? 0,
        $row['site_grade'] ?? 0,
        $row['site_clearing'] ?? 0,
        $row['site_hydrology'] ?? 0,
        $row['site_root_cuts'] ?? 0,
        $row['soil_limited'] ?? 0,
        $row['soil_saturated'] ?? 0,
        $row['soil_shallow'] ?? 0,
        $row['soil_compacted'] ?? 0,
        $row['soil_pavement'] ?? 0,
        $row['soil_normal'] ?? 0,
        $row['prevailing_wind'] ?? '',
        $row['weather_strong'] ?? 0,
        $row['weather_rain'] ?? 0,
        $row['weather_normal'] ?? 0,
        $row['vigor'] ?? '',
        $row['foliage_none'] ?? 0,
        $row['foliage_normal'] ?? 0,
        $row['foliage_chlorotic'] ?? 0,
        $row['foliage_chlorotic_pct'] ?? '',
        $row['foliage_necrotic'] ?? 0,
        $row['foliage_necrotic_pct'] ?? '',
        $row['sp_branches'] ?? 0,
        $row['sp_trunk'] ?? 0,
        $row['sp_roots'] ?? 0,
        $row['sp_describe'] ?? '',
        $row['sp_none'] ?? 0,
        $row['wind_exposure'] ?? '',
        $row['relative_crown_size'] ?? '',
        $row['crown_density'] ?? '',
        $row['unbalanced_crown'] ?? 'NO',
        $row['dead_twigs'] ?? 'NO',
        $row['dead_twigs_pct'] ?? '',
        $row['dead_twigs_dia'] ?? '',
        $row['broken_hangers'] ?? 'NO',
        $row['broken_num'] ?? '',
        $row['broken_dia'] ?? '',
        $row['over_extended'] ?? 'NO',
        $row['prune_cleaned'] ?? 0,
        $row['prune_thinned'] ?? 0,
        $row['prune_raised'] ?? 0,
        $row['prune_reduced'] ?? 0,
        $row['prune_topped'] ?? 0,
        $row['prune_lion'] ?? 0,
        $row['prune_flush'] ?? 0,
        $row['prune_other'] ?? '',
        $row['prune_none'] ?? 0,
        $row['crown_cracks'] ?? 'NO',
        $row['lightning_crown'] ?? 'NO',
        $row['codominant'] ?? 'NO',
        $row['included_bark_crown'] ?? 'NO',
        $row['weak_attachment'] ?? 'NO',
        $row['cavity_crown'] ?? 'NO',
        $row['cavity_crown_pct'] ?? '',
        $row['prev_branch_fail'] ?? 'NO',
        $row['dead_missing_bark_crown'] ?? 'NO',
        $row['cankers_crown'] ?? 'NO',
        $row['sapwood_decay_crown'] ?? 'NO',
        $row['conks_crown'] ?? 'NO',
        $row['heartwood_crown'] ?? 'NO',
        $row['trunk_dead_bark'] ?? 'NO',
        $row['abnormal_bark'] ?? 'NO',
        $row['codominant_stems'] ?? 'NO',
        $row['included_bark_trunk'] ?? 'NO',
        $row['trunk_cracks'] ?? 'NO',
        $row['sapwood_trunk'] ?? 'NO',
        $row['cankers_trunk'] ?? 'NO',
        $row['sap_ooze'] ?? 'NO',
        $row['lightning_trunk'] ?? 'NO',
        $row['heartwood_trunk'] ?? 'NO',
        $row['conks_trunk'] ?? 'NO',
        $row['cavity_trunk'] ?? 'NO',
        $row['cavity_trunk_pct'] ?? '',
        $row['lean'] ?? 'NO',
        $row['response_growth'] ?? 'NO',
        $row['trunk_other'] ?? '',
        $row['collar_buried'] ?? 'NO',
        $row['collar_depth'] ?? '',
        $row['stem_girdling'] ?? 'NO',
        $row['root_dead'] ?? 'NO',
        $row['root_decay'] ?? 'NO',
        $row['root_conks'] ?? 'NO',
        $row['root_ooze'] ?? 'NO',
        $row['root_cracks'] ?? 'NO',
        $row['cavity_root'] ?? 'NO',
        $row['cavity_root_pct'] ?? '',
        $row['cut_damage_roots'] ?? 'NO',
        $row['root_plate_lifting'] ?? 'NO',
        $row['soil_weakness'] ?? 'NO',
        $row['root_response'] ?? 'NO',
        $row['root_other'] ?? '',
        $row['mitigation_No_Action'] ?? 0,
        $row['mitigation_Clear_Dead_Branches'] ?? 0,
        $row['mitigation_Normal_Pruning'] ?? 0,
        $row['mitigation_Topping'] ?? 0,
        $row['mitigation_Tree_Cut'] ?? 0,
        $row['notes'] ?? '',
        $row['prepared_by'] ?? '',
        $row['preparer_name'] ?? ''
    ];
    
    fputcsv($output, $csvRow);
}

fclose($output);
exit;
?>