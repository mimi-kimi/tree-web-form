<?php
require_once 'config/db.php';
$pdo = db();

$tree_no = intval($_GET['tree_no'] ?? 0);
$upload_id = intval($_GET['upload_id'] ?? 0);

if (!$tree_no) { 
    header('Location: index.php'); 
    exit; 
}

// Load the tree details
$tree = $pdo->prepare('SELECT * FROM trees WHERE id = ? AND upload_id = ?');
$tree->execute([$tree_no, $upload_id]);
$tree = $tree->fetch();

if (!$tree) {
    header('Location: index.php');
    exit;
}

// Load the inspection details
$ins = $pdo->prepare('SELECT * FROM inspections WHERE tree_no = ? AND upload_id = ?');
$ins->execute([$tree_no, $upload_id]);
$d = $ins->fetch() ?: [];
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $p = $_POST;
    unset($p['tree_no']);
    unset($p['upload_id']);

    $integerFields = ['option_priority'];
    foreach ($integerFields as $intField) {
        if (isset($p[$intField]) && $p[$intField] === '') {
            $p[$intField] = null;
        }
    }

    $checkboxFields = [
        'topography_flat','topography_slope',
        'site_none','site_grade','site_clearing','site_hydrology','site_root_cuts',
        'soil_limited','soil_saturated','soil_shallow','soil_compacted','soil_pavement','soil_normal',
        'foliage_none','foliage_normal','foliage_chlorotic','foliage_necrotic',
        'sp_branches','sp_trunk','sp_roots','sp_none',
        'weather_strong','weather_rain','weather_normal',
        'prune_cleaned','prune_thinned','prune_raised','prune_reduced',
        'prune_topped','prune_lion','prune_flush','prune_none',
    ];
    foreach ($checkboxFields as $cb) {
        $p[$cb] = isset($_POST[$cb]) ? 1 : 0;
    }

    $mitigationFields = [
        'mitigation_No_Action','mitigation_Clear_Dead_Branches',
        'mitigation_Normal_Pruning','mitigation_Topping','mitigation_Tree_Cut',
    ];
    foreach ($mitigationFields as $mf) {
        $p[$mf] = isset($_POST[$mf]) ? intval($_POST[$mf]) : 0;
    }

    if (!empty($d)) {
        $cols = array_keys($p);
        $sets = implode(', ', array_map(fn($c) => "`$c` = ?", $cols));
        $vals = array_values($p);
        $vals[] = $d['insp_id'];
        $pdo->prepare("UPDATE inspections SET $sets WHERE insp_id = ?")->execute($vals);
    } else {
        $defaultNoFields = [
            'unbalanced_crown', 'dead_twigs', 'broken_hangers', 'over_extended',
            'crown_cracks', 'lightning_crown', 'codominant', 'included_bark_crown',
            'weak_attachment', 'cavity_crown', 'prev_branch_fail', 'dead_missing_bark_crown',
            'cankers_crown', 'sapwood_decay_crown', 'conks_crown', 'heartwood_crown',
            'trunk_dead_bark', 'abnormal_bark', 'codominant_stems', 'included_bark_trunk',
            'trunk_cracks', 'sapwood_trunk', 'cankers_trunk', 'sap_ooze',
            'lightning_trunk', 'heartwood_trunk', 'conks_trunk', 'cavity_trunk',
            'lean', 'response_growth', 'collar_buried', 'stem_girdling',
            'root_dead', 'root_decay', 'root_conks', 'root_ooze',
            'root_cracks', 'cavity_root', 'cut_damage_roots', 'root_plate_lifting',
            'soil_weakness', 'root_response'
        ];
        foreach ($defaultNoFields as $field) {
            if (!isset($p[$field]) || $p[$field] === '') {
                $p[$field] = 'NO';
            }
        }
        
        $p['tree_no'] = $tree_no;
        $p['upload_id'] = $upload_id;
        $cols = array_keys($p);
        $colsStr = implode(', ', array_map(fn($c) => "`$c`", $cols));
        $phs = implode(', ', array_fill(0, count($cols), '?'));
        $pdo->prepare("INSERT INTO inspections ($colsStr) VALUES ($phs)")->execute(array_values($p));
    }

    $saved = true;
    $insStmt2 = $pdo->prepare('SELECT * FROM inspections WHERE tree_no = ? AND upload_id = ?');
    $insStmt2->execute([$tree_no, $upload_id]);
    $d = $insStmt2->fetch() ?: [];
}

function v($d, $k, $def = '') { return htmlspecialchars($d[$k] ?? $def); }
function chk($d, $k) { return !empty($d[$k]) ? 'checked' : ''; }
function yn($d, $k, $val) { 
    $defaultNoFields = [
        'unbalanced_crown', 'dead_twigs', 'broken_hangers', 'over_extended',
        'crown_cracks', 'lightning_crown', 'codominant', 'included_bark_crown',
        'weak_attachment', 'cavity_crown', 'prev_branch_fail', 'dead_missing_bark_crown',
        'cankers_crown', 'sapwood_decay_crown', 'conks_crown', 'heartwood_crown',
        'trunk_dead_bark', 'abnormal_bark', 'codominant_stems', 'included_bark_trunk',
        'trunk_cracks', 'sapwood_trunk', 'cankers_trunk', 'sap_ooze',
        'lightning_trunk', 'heartwood_trunk', 'conks_trunk', 'cavity_trunk',
        'lean', 'response_growth', 'collar_buried', 'stem_girdling',
        'root_dead', 'root_decay', 'root_conks', 'root_ooze',
        'root_cracks', 'cavity_root', 'cut_damage_roots', 'root_plate_lifting',
        'soil_weakness', 'root_response'
    ];
    $current = $d[$k] ?? null;
    if ($current === null && in_array($k, $defaultNoFields)) {
        $current = 'NO';
    }
    return ($current === $val) ? 'checked' : ''; 
}
// Function to check if inspection is complete
function isInspectionComplete($data) {
    // Required fields for completion
    $requiredFields = [
        'tree_id', 'tree_location', 'tree_species', 'client',
        'dbh', 'height', 'crown_spread_dia'
    ];
    
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            return false;
        }
    }
    return true;
}

$pre_dbh = v($d, 'dbh') ?: ($tree['dbh'] ?? '');
$pre_height = v($d, 'height') ?: ($tree['tree_height'] ?? '');
$pre_crown = v($d, 'crown_spread_dia') ?: ($tree['crown_diameter'] ?? '');
$pre_client = v($d, 'client');
$pre_tree_id = v($d, 'tree_id');
$pre_location = v($d, 'tree_location');
$pre_species = v($d, 'tree_species');
$pre_circumf = v($d, 'tree_circumference');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Inspection — Tree #<?= $tree_no ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            line-height: 1.5;
        }

        .app-header {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            flex-wrap: wrap;
            gap: 12px;
        }

        .app-header h1 {
            font-size: 18px;
            font-weight: 600;
            color: #0f172a;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px 24px;
        }

        .tree-info-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .tree-stats {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .tree-stat {
            display: flex;
            flex-direction: column;
        }

        .tree-stat-label {
            font-size: 10px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .tree-stat-value {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
        }

        .form-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .form-card h2 {
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-card h2 i {
            color: #b91c1c;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .field label {
            font-size: 12px;
            font-weight: 600;
            color: #475569;
        }

        .field input, .field select, .field textarea {
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
            width: 100%;
        }

        .field input:focus, .field select:focus, .field textarea:focus {
            outline: none;
            border-color: #b91c1c;
            box-shadow: 0 0 0 3px rgba(185, 28, 28, 0.1);
        }

        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: center;
        }

        .checkbox-group label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: normal;
            cursor: pointer;
            font-size: 13px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin: 0;
            cursor: pointer;
            accent-color: #b91c1c;
        }

        .radio-group {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
        }

        .radio-group label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 13px;
        }

        .radio-group input[type="radio"] {
            width: 18px;
            height: 18px;
            margin: 0;
            cursor: pointer;
            accent-color: #b91c1c;
        }

        .section-title {
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            margin: 16px 0 12px 0;
            padding-left: 8px;
            border-left: 3px solid #b91c1c;
        }

        .defect-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 12px;
        }

        .defect-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 12px;
            background: #f8fafc;
            border-radius: 8px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .defect-item span {
            font-size: 13px;
            font-weight: 500;
            color: #334155;
        }

        .defect-item-with-pct {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 12px;
            background: #f8fafc;
            border-radius: 8px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .defect-item-with-pct span {
            font-size: 13px;
            font-weight: 500;
            color: #334155;
        }

        .pct-field {
            min-width: 100px;
        }
        .pct-field input {
            width: 80px;
            padding: 6px 8px;
        }

        .mitigation-table {
            width: 100%;
            border-collapse: collapse;
        }

        .mitigation-table th, .mitigation-table td {
            border: 1px solid #e2e8f0;
            padding: 10px;
            text-align: center;
            font-size: 12px;
        }

        .mitigation-table td:first-child {
            text-align: left;
        }

        .priority-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 2px solid #cbd5e1;
            background: white;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: bold;
            font-size: 12px;
        }

        .priority-btn:hover {
            transform: scale(1.05);
            border-color: #b91c1c;
        }

        .priority-btn.active {
            background: #b91c1c;
            border-color: #b91c1c;
            color: white;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            font-family: 'Inter', sans-serif;
            text-decoration: none;
        }

        .btn-primary {
            background: #b91c1c;
            color: white;
        }

        .btn-primary:hover {
            background: #991b1b;
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #cbd5e1;
            color: #475569;
        }

        .btn-outline:hover {
            background: #f8fafc;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        @media (max-width: 768px) {
            .app-header {
                padding: 12px 16px;
            }
            .app-header h1 {
                font-size: 16px;
            }
            .container {
                padding: 12px 16px;
            }
            .tree-info-card {
                flex-direction: column;
                align-items: flex-start;
            }
            .tree-stats {
                width: 100%;
                justify-content: space-between;
            }
            .form-card {
                padding: 16px;
            }
            .form-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            .defect-grid {
                grid-template-columns: 1fr;
            }
            .action-buttons {
                flex-direction: column;
            }
            .action-buttons .btn {
                justify-content: center;
                width: 100%;
            }
            .mitigation-table th, .mitigation-table td {
                padding: 6px 4px;
            }
            .priority-btn {
                width: 28px;
                height: 28px;
            }
        }
    </style>
</head>
<body>

<div class="app-header">
    <h1><i class="fas fa-clipboard-list" style="color: #b91c1c;"></i> Tree Inspection — Tree #<?= $tree_no ?></h1>
    <div>
        <a href="index.php?upload_id=<?= $upload_id ?>" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
</div>

<div class="container">
    <?php if ($saved): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> Inspection saved successfully!</div>
    <?php endif; ?>

    <div class="tree-info-card">
        <div class="tree-stats">
            <div class="tree-stat"><span class="tree-stat-label">Height</span><span class="tree-stat-value"><?= round($tree['tree_height'] ?? 0, 2) ?> m</span></div>
            <div class="tree-stat"><span class="tree-stat-label">Crown Dia</span><span class="tree-stat-value"><?= round($tree['crown_diameter'] ?? 0, 2) ?> m</span></div>
            <div class="tree-stat"><span class="tree-stat-label">DBH</span><span class="tree-stat-value"><?= round($tree['dbh'] ?? 0, 2) ?> cm</span></div>
            <div class="tree-stat"><span class="tree-stat-label">Biomass</span><span class="tree-stat-value"><?= round($tree['total_tree_biomass'] ?? 0, 2) ?> kg</span></div>
            <div class="tree-stat"><span class="tree-stat-label">Carbon</span><span class="tree-stat-value"><?= round($tree['carbon_stock'] ?? 0, 2) ?> kg</span></div>
        </div>
        <?php if (!empty($d)): ?>
        <a href="print.php?id=<?= $d['insp_id'] ?>" class="btn btn-secondary" target="_blank"><i class="fas fa-print"></i> Print</a>
        <?php endif; ?>
    </div>

    <form method="POST" id="inspectionForm">
        <!-- TREE INFO -->
        <div class="form-card">
            <h2><i class="fas fa-tree"></i> Tree Information</h2>
            <div class="form-grid">
                <div class="field">
                    <label>Client Name</label>
                    <input type="text" name="client" value="<?= htmlspecialchars($pre_client) ?>" placeholder="Client name">
                </div>
                <div class="field">
                    <label>Tree ID (Inspection Reference)</label>
                    <input type="text" name="tree_id" value="<?= htmlspecialchars($pre_tree_id) ?>" placeholder="Enter tree ID for this inspection">
                </div>
                <div class="field">
                    <label>Tree Location</label>
                    <input type="text" name="tree_location" value="<?= htmlspecialchars($pre_location) ?>" placeholder="e.g., Backyard, near fence">
                </div>
                <div class="field">
                    <label>Tree Species</label>
                    <input type="text" name="tree_species" value="<?= htmlspecialchars($pre_species) ?>">
                </div>
            </div>
        </div>

        <!-- TREE MEASUREMENTS -->
        <div class="form-card">
            <h2><i class="fas fa-ruler"></i> Tree Measurements</h2>
            <div class="form-grid">
                <div class="field">
                    <label>DBH (cm)</label>
                    <input type="text" name="dbh" value="<?= htmlspecialchars($pre_dbh) ?>">
                </div>
                <div class="field">
                    <label>Height (m)</label>
                    <input type="text" name="height" value="<?= htmlspecialchars($pre_height) ?>">
                </div>
                <div class="field">
                    <label>Crown Spread DIA (m)</label>
                    <input type="text" name="crown_spread_dia" value="<?= htmlspecialchars($pre_crown) ?>">
                </div>
                <div class="field">
                    <label>Tree Circumference</label>
                    <input type="text" name="tree_circumference" value="<?= htmlspecialchars($pre_circumf) ?>">
                </div>
            </div>
        </div>

        <!-- SITE FACTORS -->
        <div class="form-card">
            <h2><i class="fas fa-mountain"></i> Site Factors</h2>
            
            <div class="section-title">Topography</div>
            <div class="checkbox-group">
                <label><input type="checkbox" name="topography_flat" value="1" <?= chk($d,'topography_flat') ?>> Flat</label>
                <label><input type="checkbox" name="topography_slope" value="1" <?= chk($d,'topography_slope') ?>> Slope</label>
            </div>

            <div class="section-title">Site Changes</div>
            <div class="checkbox-group">
                <label><input type="checkbox" name="site_none" value="1" <?= chk($d,'site_none') ?>> None</label>
                <label><input type="checkbox" name="site_grade" value="1" <?= chk($d,'site_grade') ?>> Grade Change</label>
                <label><input type="checkbox" name="site_clearing" value="1" <?= chk($d,'site_clearing') ?>> Site Clearing</label>
                <label><input type="checkbox" name="site_hydrology" value="1" <?= chk($d,'site_hydrology') ?>> Change Soil Hydrology</label>
                <label><input type="checkbox" name="site_root_cuts" value="1" <?= chk($d,'site_root_cuts') ?>> Root Cuts</label>
            </div>

            <div class="section-title">Soil Conditions</div>
            <div class="checkbox-group">
                <label><input type="checkbox" name="soil_limited" value="1" <?= chk($d,'soil_limited') ?>> Limited Volume</label>
                <label><input type="checkbox" name="soil_saturated" value="1" <?= chk($d,'soil_saturated') ?>> Saturated</label>
                <label><input type="checkbox" name="soil_shallow" value="1" <?= chk($d,'soil_shallow') ?>> Shallow</label>
                <label><input type="checkbox" name="soil_compacted" value="1" <?= chk($d,'soil_compacted') ?>> Compacted</label>
                <label><input type="checkbox" name="soil_pavement" value="1" <?= chk($d,'soil_pavement') ?>> Pavement Over Roots</label>
                <label><input type="checkbox" name="soil_normal" value="1" <?= chk($d,'soil_normal') ?>> Normal</label>
            </div>

            <div class="section-title">Prevailing Wind Direction</div>
            <div class="radio-group">
                <label><input type="radio" name="prevailing_wind" value="YES" <?= yn($d,'prevailing_wind','YES') ?>> YES</label>
                <label><input type="radio" name="prevailing_wind" value="NO" <?= yn($d,'prevailing_wind','NO') ?>> NO</label>
            </div>

            <div class="section-title">Common Weather</div>
            <div class="checkbox-group">
                <label><input type="checkbox" name="weather_strong" value="1" <?= chk($d,'weather_strong') ?>> Strong Winds</label>
                <label><input type="checkbox" name="weather_rain" value="1" <?= chk($d,'weather_rain') ?>> Heavy Rain</label>
                <label><input type="checkbox" name="weather_normal" value="1" <?= chk($d,'weather_normal') ?>> Normal</label>
            </div>
        </div>

        <!-- TREE HEALTH -->
        <div class="form-card">
            <h2><i class="fas fa-heartbeat"></i> Tree Health</h2>
            
            <div class="section-title">Vigor</div>
            <div class="radio-group">
                <label><input type="radio" name="vigor" value="Low" <?= yn($d,'vigor','Low') ?>> Low</label>
                <label><input type="radio" name="vigor" value="Normal" <?= yn($d,'vigor','Normal') ?>> Normal</label>
                <label><input type="radio" name="vigor" value="High" <?= yn($d,'vigor','High') ?>> High</label>
            </div>

            <div class="section-title">Foliage</div>
            <div class="form-grid">
                <div class="checkbox-group">
                    <label><input type="checkbox" name="foliage_none" value="1" <?= chk($d,'foliage_none') ?>> None</label>
                    <label><input type="checkbox" name="foliage_normal" value="1" <?= chk($d,'foliage_normal') ?>> Normal</label>
                    <label><input type="checkbox" name="foliage_chlorotic" value="1" <?= chk($d,'foliage_chlorotic') ?>> Chlorotic</label>
                    <label><input type="checkbox" name="foliage_necrotic" value="1" <?= chk($d,'foliage_necrotic') ?>> Necrotic</label>
                </div>
                <div class="field">
                    <label>Chlorotic %</label>
                    <input type="text" name="foliage_chlorotic_pct" value="<?= v($d,'foliage_chlorotic_pct') ?>">
                </div>
                <div class="field">
                    <label>Necrotic %</label>
                    <input type="text" name="foliage_necrotic_pct" value="<?= v($d,'foliage_necrotic_pct') ?>">
                </div>
            </div>

            <div class="section-title">Species Failure Profile</div>
            <div class="checkbox-group">
                <label><input type="checkbox" name="sp_branches" value="1" <?= chk($d,'sp_branches') ?>> Branches</label>
                <label><input type="checkbox" name="sp_trunk" value="1" <?= chk($d,'sp_trunk') ?>> Trunk</label>
                <label><input type="checkbox" name="sp_roots" value="1" <?= chk($d,'sp_roots') ?>> Roots</label>
                <label><input type="checkbox" name="sp_none" value="1" <?= chk($d,'sp_none') ?>> None</label>
            </div>
            <div class="field" style="margin-top: 12px;">
                <label>Describe</label>
                <input type="text" name="sp_describe" value="<?= v($d,'sp_describe') ?>">
            </div>
        </div>

        <!-- LOAD FACTORS -->
        <div class="form-card">
            <h2><i class="fas fa-weight-hanging"></i> Load Factors</h2>
            
            <div class="section-title">Wind Exposure</div>
            <div class="radio-group">
                <?php foreach (['Protected','Partial','Full','Wind Funneling','None'] as $o): ?>
                <label><input type="radio" name="wind_exposure" value="<?= $o ?>" <?= yn($d,'wind_exposure',$o) ?>> <?= $o ?></label>
                <?php endforeach; ?>
            </div>
            
            <div class="section-title">Relative Crown Size</div>
            <div class="radio-group">
                <?php foreach (['Small','Medium','Large'] as $o): ?>
                <label><input type="radio" name="relative_crown_size" value="<?= $o ?>" <?= yn($d,'relative_crown_size',$o) ?>> <?= $o ?></label>
                <?php endforeach; ?>
            </div>
            
            <div class="section-title">Crown Density</div>
            <div class="radio-group">
                <?php foreach (['Sparse','Normal','Dense'] as $o): ?>
                <label><input type="radio" name="crown_density" value="<?= $o ?>" <?= yn($d,'crown_density',$o) ?>> <?= $o ?></label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- CROWN & BRANCHES DEFECTS -->
        <div class="form-card">
            <h2><i class="fas fa-tree"></i> Crown & Branches Defects</h2>
            
            <div class="defect-grid">
                <div class="defect-item">
                    <span>Unbalanced Crown:</span>
                    <div class="radio-group">
                        <label><input type="radio" name="unbalanced_crown" value="YES" <?= yn($d,'unbalanced_crown','YES') ?>> Yes</label>
                        <label><input type="radio" name="unbalanced_crown" value="NO" <?= yn($d,'unbalanced_crown','NO') ?>> No</label>
                    </div>
                </div>
                <div class="defect-item">
                    <span>Dead Twigs/Branches:</span>
                    <div class="radio-group">
                        <label><input type="radio" name="dead_twigs" value="YES" <?= yn($d,'dead_twigs','YES') ?>> Yes</label>
                        <label><input type="radio" name="dead_twigs" value="NO" <?= yn($d,'dead_twigs','NO') ?>> No</label>
                    </div>
                </div>
                <div class="defect-item">
                    <span>Broken Hangers:</span>
                    <div class="radio-group">
                        <label><input type="radio" name="broken_hangers" value="YES" <?= yn($d,'broken_hangers','YES') ?>> Yes</label>
                        <label><input type="radio" name="broken_hangers" value="NO" <?= yn($d,'broken_hangers','NO') ?>> No</label>
                    </div>
                </div>
                <div class="defect-item">
                    <span>Over-extended Branches:</span>
                    <div class="radio-group">
                        <label><input type="radio" name="over_extended" value="YES" <?= yn($d,'over_extended','YES') ?>> Yes</label>
                        <label><input type="radio" name="over_extended" value="NO" <?= yn($d,'over_extended','NO') ?>> No</label>
                    </div>
                </div>
            </div>

            <div class="section-title">Dead Twigs Details</div>
            <div class="form-grid">
                <div class="field"><label>Dead Twigs % Overall</label><input type="text" name="dead_twigs_pct" value="<?= v($d,'dead_twigs_pct') ?>"></div>
                <div class="field"><label>Dead Twigs Max DIA</label><input type="text" name="dead_twigs_dia" value="<?= v($d,'dead_twigs_dia') ?>"></div>
                <div class="field"><label>Broken Number</label><input type="text" name="broken_num" value="<?= v($d,'broken_num') ?>"></div>
                <div class="field"><label>Broken Max DIA</label><input type="text" name="broken_dia" value="<?= v($d,'broken_dia') ?>"></div>
            </div>

            <div class="section-title">Pruning History</div>
            <div class="checkbox-group">
                <label><input type="checkbox" name="prune_cleaned" value="1" <?= chk($d,'prune_cleaned') ?>> Crown Cleaned</label>
                <label><input type="checkbox" name="prune_thinned" value="1" <?= chk($d,'prune_thinned') ?>> Thinned</label>
                <label><input type="checkbox" name="prune_raised" value="1" <?= chk($d,'prune_raised') ?>> Raised</label>
                <label><input type="checkbox" name="prune_reduced" value="1" <?= chk($d,'prune_reduced') ?>> Reduced</label>
                <label><input type="checkbox" name="prune_topped" value="1" <?= chk($d,'prune_topped') ?>> Topped</label>
                <label><input type="checkbox" name="prune_lion" value="1" <?= chk($d,'prune_lion') ?>> Lion-Tailed</label>
                <label><input type="checkbox" name="prune_flush" value="1" <?= chk($d,'prune_flush') ?>> Flush Cuts</label>
                <label><input type="checkbox" name="prune_none" value="1" <?= chk($d,'prune_none') ?>> None</label>
            </div>
            <div class="field"><label>Other Pruning</label><input type="text" name="prune_other" value="<?= v($d,'prune_other') ?>"></div>

            <div class="section-title">Crown Conditions</div>
            <div class="defect-grid">
                <?php
                $crownYN = [
                    'crown_cracks' => 'Cracks',
                    'lightning_crown' => 'Lightning Damage',
                    'codominant' => 'Codominant',
                    'included_bark_crown' => 'Included Bark',
                    'weak_attachment' => 'Weak Attachment',
                    'prev_branch_fail' => 'Previous Branch Failures',
                    'dead_missing_bark_crown' => 'Dead/Missing Bark',
                    'cankers_crown' => 'Cankers/Galls/Burls',
                    'sapwood_decay_crown' => 'Sapwood Damage/Decay',
                    'conks_crown' => 'Conks',
                    'heartwood_crown' => 'Heartwood Decay',
                ];
                foreach ($crownYN as $k => $lbl): ?>
                <div class="defect-item">
                    <span><?= $lbl ?>:</span>
                    <div class="radio-group">
                        <label><input type="radio" name="<?= $k ?>" value="YES" <?= yn($d,$k,'YES') ?>> Yes</label>
                        <label><input type="radio" name="<?= $k ?>" value="NO" <?= yn($d,$k,'NO') ?>> No</label>
                    </div>
                </div>
                <?php endforeach; ?>
                <div class="defect-item">
                    <span>Cavity/Nest Hole:</span>
                    <div class="radio-group">
                        <label><input type="radio" name="cavity_crown" value="YES" <?= yn($d,'cavity_crown','YES') ?>> Yes</label>
                        <label><input type="radio" name="cavity_crown" value="NO" <?= yn($d,'cavity_crown','NO') ?>> No</label>
                    </div>
                    <div class="field pct-field">
                        <label>% CIRC.</label>
                        <input type="text" name="cavity_crown_pct" value="<?= v($d,'cavity_crown_pct') ?>" placeholder="%">
                    </div>
                </div>
            </div>
        </div>

        <!-- TRUNK DEFECTS -->
        <div class="form-card">
            <h2><i class="fas fa-arrows-alt"></i> Trunk Defects</h2>
            <div class="defect-grid">
                <?php
                $trunkYN = [
                    'trunk_dead_bark' => 'Dead/Missing Bark',
                    'abnormal_bark' => 'Abnormal Bark Texture',
                    'codominant_stems' => 'Codominant Stems',
                    'included_bark_trunk' => 'Included Bark',
                    'trunk_cracks' => 'Cracks',
                    'sapwood_trunk' => 'Sapwood Damage/Decay',
                    'cankers_trunk' => 'Cankers/Galls/Burls',
                    'sap_ooze' => 'Sap Ooze',
                    'lightning_trunk' => 'Lightning Damage',
                    'heartwood_trunk' => 'Heartwood Decay',
                    'conks_trunk' => 'Conks',
                    'lean' => 'Lean',
                    'response_growth' => 'Response Growth',
                ];
                foreach ($trunkYN as $k => $lbl): ?>
                <div class="defect-item">
                    <span><?= $lbl ?>:</span>
                    <div class="radio-group">
                        <label><input type="radio" name="<?= $k ?>" value="YES" <?= yn($d,$k,'YES') ?>> Yes</label>
                        <label><input type="radio" name="<?= $k ?>" value="NO" <?= yn($d,$k,'NO') ?>> No</label>
                    </div>
                </div>
                <?php endforeach; ?>
                <div class="defect-item">
                    <span>Cavity/Nest Hole:</span>
                    <div class="radio-group">
                        <label><input type="radio" name="cavity_trunk" value="YES" <?= yn($d,'cavity_trunk','YES') ?>> Yes</label>
                        <label><input type="radio" name="cavity_trunk" value="NO" <?= yn($d,'cavity_trunk','NO') ?>> No</label>
                    </div>
                    <div class="field pct-field">
                        <label>% CIRC.</label>
                        <input type="text" name="cavity_trunk_pct" value="<?= v($d,'cavity_trunk_pct') ?>" placeholder="%">
                    </div>
                </div>
            </div>
            <div class="field"><label>Other Trunk Issues</label><input type="text" name="trunk_other" value="<?= v($d,'trunk_other') ?>"></div>
        </div>

        <!-- ROOTS DEFECTS -->
        <div class="form-card">
            <h2><i class="fas fa-seedling"></i> Roots & Root Collar Defects</h2>
            
            <div class="defect-item" style="margin-bottom: 16px;">
                <span>Collar Buried/Not Visible:</span>
                <div class="radio-group">
                    <label><input type="radio" name="collar_buried" value="YES" <?= yn($d,'collar_buried','YES') ?>> Yes</label>
                    <label><input type="radio" name="collar_buried" value="NO" <?= yn($d,'collar_buried','NO') ?>> No</label>
                </div>
            </div>
            <div class="field"><label>Collar Depth</label><input type="text" name="collar_depth" value="<?= v($d,'collar_depth') ?>"></div>
            
            <div class="defect-grid">
                <?php
                $rootYN = [
                    'stem_girdling' => 'Stem Girdling',
                    'root_dead' => 'Dead',
                    'root_decay' => 'Decay',
                    'root_conks' => 'Conks',
                    'root_ooze' => 'Ooze',
                    'root_cracks' => 'Cracks',
                    'cut_damage_roots' => 'Cut/Damage Roots',
                    'root_plate_lifting' => 'Root Plate Lifting',
                    'soil_weakness' => 'Soil Weakness',
                    'root_response' => 'Response Growth',
                ];
                foreach ($rootYN as $k => $lbl): ?>
                <div class="defect-item">
                    <span><?= $lbl ?>:</span>
                    <div class="radio-group">
                        <label><input type="radio" name="<?= $k ?>" value="YES" <?= yn($d,$k,'YES') ?>> Yes</label>
                        <label><input type="radio" name="<?= $k ?>" value="NO" <?= yn($d,$k,'NO') ?>> No</label>
                    </div>
                </div>
                <?php endforeach; ?>
                <div class="defect-item">
                    <span>Cavity/Nest Hole:</span>
                    <div class="radio-group">
                        <label><input type="radio" name="cavity_root" value="YES" <?= yn($d,'cavity_root','YES') ?>> Yes</label>
                        <label><input type="radio" name="cavity_root" value="NO" <?= yn($d,'cavity_root','NO') ?>> No</label>
                    </div>
                    <div class="field pct-field">
                        <label>% CIRC.</label>
                        <input type="text" name="cavity_root_pct" value="<?= v($d,'cavity_root_pct') ?>" placeholder="%">
                    </div>
                </div>
            </div>
            <div class="field"><label>Other Root Issues</label><input type="text" name="root_other" value="<?= v($d,'root_other') ?>"></div>
        </div>

        <!-- MITIGATION OPTIONS -->
        <div class="form-card">
            <h2><i class="fas fa-tasks"></i> Mitigation Options & Priority</h2>
            <div class="table-wrapper" style="overflow-x: auto;">
                <table class="mitigation-table">
                    <thead>
                        <tr><th style="text-align:left">Option</th><th>1</th><th>2</th><th>3</th><th>4</th><th>5</th></tr>
                    </thead>
                    <tbody>
                        <?php
                        $mitigationOptions = [
                            'No Action' => '+ NO ACTION',
                            'Clear Dead Branches' => '+ CLEAR DEAD',
                            'Normal Pruning' => '+ NORMAL PRUNING',
                            'Topping' => '+ TOPPING',
                            'Tree Cut' => '+ TREE CUT'
                        ];
                        foreach ($mitigationOptions as $value => $label): 
                            $optionKey = 'mitigation_' . preg_replace('/[^a-zA-Z0-9]/', '_', $value);
                            $savedPriority = isset($d[$optionKey]) ? intval($d[$optionKey]) : 0;
                        ?>
                        <tr>
                            <td style="text-align:left"><?= $label ?></td>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <td style="text-align:center">
                                <button type="button" class="priority-btn <?= ($savedPriority === $i) ? 'active' : '' ?>" data-option="<?= htmlspecialchars($value) ?>" data-priority="<?= $i ?>"><?= $i ?></button>
                            </td>
                            <?php endfor; ?>
                            <input type="hidden" name="<?= $optionKey ?>" value="<?= $savedPriority ?>" id="hidden_<?= $optionKey ?>">
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- COMMENTS -->
        <div class="form-card">
            <h2><i class="fas fa-comment"></i> Overall Comments</h2>
            <div class="field">
                <label>Notes</label>
                <textarea name="notes" rows="4" style="width: 100%;"><?= v($d,'notes') ?></textarea>
            </div>
        </div>

        <!-- PREPARED BY -->
        <div class="form-card">
            <h2><i class="fas fa-signature"></i> Prepared By</h2>
            <div class="form-grid">
                <div class="field">
                    <label>Signature / Title</label>
                    <input type="text" name="prepared_by" value="<?= v($d,'prepared_by') ?>" placeholder="e.g., Certified Arborist">
                </div>
                <div class="field">
                    <label>Name</label>
                    <input type="text" name="preparer_name" value="<?= v($d,'preparer_name') ?>" placeholder="Full name">
                </div>
            </div>
        </div>

        <!-- ACTION BUTTONS -->
        <div class="action-buttons">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Inspection</button>
            <?php if (!empty($d)): ?>
            <a href="print.php?id=<?= $d['insp_id'] ?>" class="btn btn-secondary" target="_blank"><i class="fas fa-print"></i> Print Form</a>
            <?php endif; ?>
            <a href="index.php?upload_id=<?= $upload_id ?>" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to List</a>
        </div>
    </form>
</div>

<script>
// Radio button toggle functionality (allow unchecking)
document.querySelectorAll('input[type="radio"]').forEach(radio => {
    if (radio.checked) {
        radio.setAttribute('data-checked', 'true');
    }
    
    radio.addEventListener('click', function(e) {
        const wasChecked = this.hasAttribute('data-checked');
        const name = this.name;
        
        if (!wasChecked) {
            document.querySelectorAll(`input[name="${name}"]`).forEach(r => {
                r.removeAttribute('data-checked');
                r.checked = false;
            });
            this.checked = true;
            this.setAttribute('data-checked', 'true');
        } else {
            this.checked = false;
            this.removeAttribute('data-checked');
        }
    });
    
    if (radio.checked) {
        radio.setAttribute('data-checked', 'true');
    }
});

// Priority buttons functionality
document.querySelectorAll('.priority-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const option = this.dataset.option;
        const priority = this.dataset.priority;
        const optionKey = 'mitigation_' + option.replace(/[^a-zA-Z0-9]/g, '_');
        const hiddenInput = document.getElementById('hidden_' + optionKey);
        const allBtnsInRow = this.closest('tr').querySelectorAll('.priority-btn');
        
        const isSelected = this.classList.contains('active');
        
        if (isSelected) {
            this.classList.remove('active');
            if (hiddenInput) hiddenInput.value = '';
        } else {
            allBtnsInRow.forEach(btn => {   
                btn.classList.remove('active');
            });
            this.classList.add('active');
            if (hiddenInput) hiddenInput.value = priority;
        }
    });
});
</script>
</body>
</html>