<?php
require_once 'config/db.php';
$pdo = db(); // Returns PDO connection as $pdo

$tree_id = intval($_GET['tree_id'] ?? 0);
$upload_id = intval($_GET['upload_id'] ?? 0);

if (!$tree_id) { 
    header('Location: index.php'); 
    exit; 
}

// Load the tree details
$tree = $pdo->prepare('SELECT * FROM trees WHERE id = ? AND upload_id = ?');
$tree->execute([$tree_id, $upload_id]);
$tree = $tree->fetch();

if (!$tree) {
    header('Location: index.php');
    exit;
}

// Load the inspection details using tree_id and upload_id
$ins = $pdo->prepare('SELECT * FROM inspections WHERE tree_id = ? AND upload_id = ?');
$ins->execute([$tree_id, $upload_id]);
$d = $ins->fetch() ?: [];
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $p = $_POST;
    unset($p['tree_id']);
    unset($p['upload_id']);

    // Handle integer fields - convert empty strings to NULL
    $integerFields = ['option_priority'];
    foreach ($integerFields as $intField) {
        if (isset($p[$intField]) && $p[$intField] === '') {
            $p[$intField] = null;
        }
    }

    // Complete list of checkbox fields
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

    // Mitigation priority fields must preserve numeric values (1-5) instead of being coerced to 1.
    $mitigationFields = [
        'mitigation_No_Action','mitigation_Clear_Dead_Branches',
        'mitigation_Normal_Pruning','mitigation_Topping','mitigation_Tree_Cut',
    ];
    foreach ($mitigationFields as $mf) {
        $p[$mf] = isset($_POST[$mf]) ? intval($_POST[$mf]) : 0;
    }

    if (!empty($d)) {
        // UPDATE existing
        $cols = array_keys($p);
        $sets = implode(', ', array_map(fn($c) => "`$c` = ?", $cols));
        $vals = array_values($p);
        $vals[] = $d['insp_id'];
        $pdo->prepare("UPDATE inspections SET $sets WHERE insp_id = ?")->execute($vals);
    } else {
        // INSERT new - set default 'NO' for Yes/No fields that should default to NO
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
        
        $p['tree_id'] = $tree_id;
        $p['upload_id'] = $upload_id;
        $cols    = array_keys($p);
        $colsStr = implode(', ', array_map(fn($c) => "`$c`", $cols));
        $phs     = implode(', ', array_fill(0, count($cols), '?'));
        $pdo->prepare("INSERT INTO inspections ($colsStr) VALUES ($phs)")->execute(array_values($p));
    }

    $saved = true;

    // Reload inspection after save
    $insStmt2 = $pdo->prepare('SELECT * FROM inspections WHERE tree_id = ? AND upload_id = ?');
    $insStmt2->execute([$tree_id, $upload_id]);
    $d = $insStmt2->fetch() ?: [];
}

// Helper functions
function v($d, $k, $def = '') { 
    return htmlspecialchars($d[$k] ?? $def); 
}
function chk($d, $k) { 
    return !empty($d[$k]) ? 'checked' : ''; 
}
function yn($d, $k, $val) { 
    // Fields that should default to NO
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
    
    // If no saved value and this field should default to NO, treat as NO
    if ($current === null && in_array($k, $defaultNoFields)) {
        $current = 'NO';
    }
    
    return ($current === $val) ? 'checked' : ''; 
}

// Pre-fill from tree master data
$pre_dbh        = v($d, 'dbh') ?: ($tree['dbh'] ?? '');
$pre_height     = v($d, 'height') ?: ($tree['tree_height'] ?? '');
$pre_crown      = v($d, 'crown_spread_dia') ?: ($tree['crown_diameter'] ?? '');
$pre_client     = v($d, 'client');
$pre_address    = v($d, 'client_address');
$pre_location   = v($d, 'tree_location');
$pre_species    = v($d, 'tree_species');
$pre_circumf    = v($d, 'tree_circumference');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Inspection — Tree #<?= $tree_id ?></title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
    .yn-group { display:flex; gap:16px; align-items:center; }
    .yn-group label { display:flex; align-items:center; gap:5px; cursor:pointer; font-size:13px; }
    .form-section { border-top:1px solid #e5e5e5; padding-top:14px; margin-top:14px; }
    .grid-yn { display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:10px; margin-top:8px; }
    .yn-item { display:flex; align-items:center; gap:10px; }
    .yn-item label { margin:0; font-size:12px; }
    .yn-item .yn-buttons { display:flex; gap:8px; }
    /* Mobile optimizations for inspect.php */
@media (max-width: 768px) {
    .container {
        padding: 10px;
    }
    
    .topbar {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .topbar h1 {
        font-size: 1.3rem;
    }
    
    .btn-row {
        flex-direction: column;
        width: 100%;
    }
    
    .btn-row .btn {
        width: 100%;
        text-align: center;
        margin: 2px 0;
    }
    
    .card {
        padding: 12px;
    }
    
    .card h2 {
        font-size: 1.1rem;
    }
    
    .grid2, .grid3, .grid4 {
        grid-template-columns: 1fr !important;
        gap: 10px;
    }
    
    .grid-yn {
        grid-template-columns: 1fr !important;
    }
    
    .yn-item {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 8px;
        padding: 10px;
        background: #f8fafc;
        border-radius: 8px;
        margin-bottom: 8px;
    }
    
    .yn-buttons {
        display: flex;
        gap: 15px;
    }
    
    .form-section .grid4 {
        grid-template-columns: 1fr 1fr !important;
    }
    
    .form-section .grid4 label {
        font-size: 12px;
    }
    
    .mitigation-table {
        font-size: 10px;
    }
    
    .mitigation-table th, 
    .mitigation-table td {
        padding: 6px 2px;
    }
    
    .priority-btn {
        width: 30px !important;
        height: 30px !important;
    }
    
    .field input, 
    .field textarea, 
    .field select {
        font-size: 16px !important; /* Prevents zoom on iOS */
    }
    
    .yn-group {
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .alert {
        font-size: 13px;
    }
    
    /* Stack radio buttons vertically on mobile for better tap targets */
    .yn-group label {
        padding: 8px 0;
    }
    
    /* Better touch targets */
    button, 
    .btn, 
    .priority-btn,
    input[type="checkbox"] + label,
    input[type="radio"] + label {
        cursor: pointer;
        min-height: 44px; /* Better touch target size */
    }
    
    input[type="checkbox"], 
    input[type="radio"] {
        transform: scale(1.2);
        margin-right: 8px;
    }
}
/* Smooth scrolling for mobile */
html {
    scroll-behavior: smooth;
}

/* Better form section separation on mobile */
.form-section {
    margin-top: 20px;
    padding-top: 15px;
}

/* Make the tree data card more compact on mobile */
.card[style*="background:#f0fdf4"] .grid4 {
    grid-template-columns: 1fr 1fr !important;
    gap: 8px;
}

/* Improve modal on mobile */
@media (max-width: 768px) {
    .modal-content {
        width: 90%;
        margin: 20px;
        padding: 20px;
    }
}
  </style>
</head>
<body>
<div class="container">

  <div class="topbar no-print">
    <h1>Tree Inspection — Tree #<?= $tree_id ?></h1>
    <div class="btn-row">
      <?php if (!empty($d)): ?>
      <a href="print.php?id=<?= $d['insp_id'] ?>" class="btn btn-orange" target="_blank">Print Form</a>
      <?php endif; ?>
      <a href="index.php?upload_id=<?= $upload_id ?>" class="btn btn-gray">← Back</a>
    </div>
  </div>

  <?php if ($saved): ?>
  <div class="alert alert-success no-print">Saved successfully!</div>
  <?php endif; ?>

  <!-- Tree reference data from Excel -->
  <div class="card no-print" style="background:#f0fdf4;border:1px solid #bbf7d0">
    <h2 style="color:#166534">Tree #<?= $tree_id ?> — Data from Excel (<?= htmlspecialchars($tree['upload_id'] ? 'Dataset ID: ' . $tree['upload_id'] : '') ?>)</h2>
    <div class="grid4" style="font-size:13px;gap:10px">
      <div><strong>Height:</strong> <?= $tree['tree_height'] ?? '' ?> m</div>
      <div><strong>Crown Dia:</strong> <?= $tree['crown_diameter'] ?? '' ?> m</div>
      <div><strong>Crown Area:</strong> <?= $tree['crown_area'] ?? '' ?> m²</div>
      <div><strong>Crown Volume:</strong> <?= $tree['crown_volume'] ?? '' ?> m³</div>
      <div><strong>DBH:</strong> <?= $tree['dbh'] ?? '' ?> cm</div>
      <div><strong>Stem Biomass:</strong> <?= $tree['stem_biomass'] ?? '' ?> kg</div>
      <div><strong>Total Biomass:</strong> <?= $tree['total_tree_biomass'] ?? '' ?> kg</div>
      <div><strong>Carbon Stock:</strong> <?= $tree['carbon_stock'] ?? '' ?> kg</div>
    </div>
  </div>

<form method="POST">

  <!-- CLIENT INFO -->
  <div class="card">
    <h2>Client Information</h2>
    <div class="grid2">
      <div class="field">
        <label>Client Name</label>
        <input type="text" name="client" value="<?= htmlspecialchars($pre_client) ?>">
      </div>
      <div class="field">
        <label>Client Address</label>
        <input type="text" name="client_address" value="<?= htmlspecialchars($pre_address) ?>">
      </div>
    </div>
  </div>

  <!-- TREE INFO -->
  <div class="card">
    <h2>Tree Information</h2>
    <div class="grid3">
      <div class="field">
        <label>Tree Location</label>
        <input type="text" name="tree_location" value="<?= htmlspecialchars($pre_location) ?>" placeholder="e.g., Backyard, near fence">
      </div>
      <div class="field">
        <label>Tree Species</label>
        <input type="text" name="tree_species" value="<?= htmlspecialchars($pre_species) ?>">
      </div>
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
  <div class="card">
    <h2>Site Factors</h2>

    <div class="form-section" style="border-top:none;padding-top:0;margin-top:0">
      <p style="font-size:12px;font-weight:700;color:#555;margin-bottom:8px">TOPOGRAPHY</p>
      <div style="display:flex;gap:20px">
        <label class="chkrow"><input type="checkbox" name="topography_flat" value="1" <?= chk($d,'topography_flat') ?>> Flat</label>
        <label class="chkrow"><input type="checkbox" name="topography_slope" value="1" <?= chk($d,'topography_slope') ?>> Slope</label>
      </div>
    </div>

    <div class="form-section">
      <p style="font-size:12px;font-weight:700;color:#555;margin-bottom:8px">SITE CHANGES</p>
      <div class="grid4">
        <label class="chkrow"><input type="checkbox" name="site_none" value="1" <?= chk($d,'site_none') ?>> None</label>
        <label class="chkrow"><input type="checkbox" name="site_grade" value="1" <?= chk($d,'site_grade') ?>> Grade Change</label>
        <label class="chkrow"><input type="checkbox" name="site_clearing" value="1" <?= chk($d,'site_clearing') ?>> Site Clearing</label>
        <label class="chkrow"><input type="checkbox" name="site_hydrology" value="1" <?= chk($d,'site_hydrology') ?>> Change Soil Hydrology</label>
        <label class="chkrow"><input type="checkbox" name="site_root_cuts" value="1" <?= chk($d,'site_root_cuts') ?>> Root Cuts</label>
      </div>
    </div>

    <div class="form-section">
      <p style="font-size:12px;font-weight:700;color:#555;margin-bottom:8px">SOIL CONDITIONS</p>
      <div class="grid4">
        <label class="chkrow"><input type="checkbox" name="soil_limited" value="1" <?= chk($d,'soil_limited') ?>> Limited Volume</label>
        <label class="chkrow"><input type="checkbox" name="soil_saturated" value="1" <?= chk($d,'soil_saturated') ?>> Saturated</label>
        <label class="chkrow"><input type="checkbox" name="soil_shallow" value="1" <?= chk($d,'soil_shallow') ?>> Shallow</label>
        <label class="chkrow"><input type="checkbox" name="soil_compacted" value="1" <?= chk($d,'soil_compacted') ?>> Compacted</label>
        <label class="chkrow"><input type="checkbox" name="soil_pavement" value="1" <?= chk($d,'soil_pavement') ?>> Pavement Over Roots</label>
        <label class="chkrow"><input type="checkbox" name="soil_normal" value="1" <?= chk($d,'soil_normal') ?>> Normal</label>
      </div>
    </div>

    <div class="form-section grid2">
      <div>
        <p style="font-size:12px;font-weight:700;color:#555;margin-bottom:8px">PREVAILING WIND DIRECTION</p>
        <div class="yn-group">
          <label><input type="radio" name="prevailing_wind" value="YES" <?= yn($d,'prevailing_wind','YES') ?>> Yes</label>
          <label><input type="radio" name="prevailing_wind" value="NO"  <?= yn($d,'prevailing_wind','NO')  ?>> No</label>
        </div>
      </div>
      <div>
        <p style="font-size:12px;font-weight:700;color:#555;margin-bottom:8px">COMMON WEATHER</p>
        <div style="display:flex;gap:16px;flex-wrap:wrap">
          <label class="chkrow"><input type="checkbox" name="weather_strong" value="1" <?= chk($d,'weather_strong') ?>> Strong Winds</label>
          <label class="chkrow"><input type="checkbox" name="weather_rain"   value="1" <?= chk($d,'weather_rain')   ?>> Heavy Rain</label>
          <label class="chkrow"><input type="checkbox" name="weather_normal" value="1" <?= chk($d,'weather_normal') ?>> Normal</label>
        </div>
      </div>
    </div>
  </div>

  <!-- TREE HEALTH -->
  <div class="card">
    <h2>Tree Health and Species Profile</h2>

    <div class="form-section" style="border-top:none;padding-top:0;margin-top:0">
      <p style="font-size:12px;font-weight:700;color:#555;margin-bottom:8px">VIGOR</p>
      <div class="yn-group">
        <label><input type="radio" name="vigor" value="Low"    <?= yn($d,'vigor','Low') ?>> Low</label>
        <label><input type="radio" name="vigor" value="Normal" <?= yn($d,'vigor','Normal') ?>> Normal</label>
        <label><input type="radio" name="vigor" value="High"   <?= yn($d,'vigor','High') ?>> High</label>
      </div>
    </div>

    <div class="form-section">
      <p style="font-size:12px;font-weight:700;color:#555;margin-bottom:8px">FOLIAGE</p>
      <div class="grid4" style="align-items:center">
        <label class="chkrow"><input type="checkbox" name="foliage_none"      value="1" <?= chk($d,'foliage_none') ?>> None</label>
        <label class="chkrow"><input type="checkbox" name="foliage_normal"    value="1" <?= chk($d,'foliage_normal') ?>> Normal</label>
        <label class="chkrow"><input type="checkbox" name="foliage_chlorotic" value="1" <?= chk($d,'foliage_chlorotic') ?>> Chlorotic</label>
        <div class="field">
          <label>Chlorotic %</label>
          <input type="text" name="foliage_chlorotic_pct" value="<?= v($d,'foliage_chlorotic_pct') ?>" style="width:80px">
        </div>
        <label class="chkrow"><input type="checkbox" name="foliage_necrotic" value="1" <?= chk($d,'foliage_necrotic') ?>> Necrotic</label>
        <div class="field">
          <label>Necrotic %</label>
          <input type="text" name="foliage_necrotic_pct" value="<?= v($d,'foliage_necrotic_pct') ?>" style="width:80px">
        </div>
      </div>
    </div>

    <div class="form-section">
      <p style="font-size:12px;font-weight:700;color:#555;margin-bottom:8px">SPECIES FAILURE PROFILE</p>
      <div style="display:flex;gap:20px;align-items:center;flex-wrap:wrap">
        <label class="chkrow"><input type="checkbox" name="sp_branches" value="1" <?= chk($d,'sp_branches') ?>> Branches</label>
        <label class="chkrow"><input type="checkbox" name="sp_trunk"    value="1" <?= chk($d,'sp_trunk')    ?>> Trunk</label>
        <label class="chkrow"><input type="checkbox" name="sp_roots"    value="1" <?= chk($d,'sp_roots')    ?>> Roots</label>
        <div class="field">
          <label>Describe</label>
          <input type="text" name="sp_describe" value="<?= v($d,'sp_describe') ?>">
        </div>
        <label class="chkrow"><input type="checkbox" name="sp_none" value="1" <?= chk($d,'sp_none') ?>> None</label>
      </div>
    </div>
  </div>

  <!-- LOAD FACTORS -->
  <div class="card">
    <h2>Load Factors</h2>
    <div class="grid2">
      <div class="form-section" style="border-top:none;padding-top:0;margin-top:0">
        <p style="font-size:12px;font-weight:700;color:#555;margin-bottom:8px">WIND EXPOSURE</p>
        <div style="display:flex;gap:12px;flex-wrap:wrap">
          <?php foreach (['Protected','Partial','Full','Wind Funneling','None'] as $o): ?>
          <label class="chkrow">
            <input type="radio" name="wind_exposure" value="<?= $o ?>" <?= yn($d,'wind_exposure',$o) ?>> <?= $o ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="form-section" style="border-top:none;padding-top:0;margin-top:0">
        <p style="font-size:12px;font-weight:700;color:#555;margin-bottom:8px">RELATIVE CROWN SIZE</p>
        <div class="yn-group">
          <?php foreach (['Small','Medium','Large'] as $o): ?>
          <label><input type="radio" name="relative_crown_size" value="<?= $o ?>" <?= yn($d,'relative_crown_size',$o) ?>> <?= $o ?></label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="form-section">
        <p style="font-size:12px;font-weight:700;color:#555;margin-bottom:8px">CROWN DENSITY</p>
        <div class="yn-group">
          <?php foreach (['Sparse','Normal','Dense'] as $o): ?>
          <label><input type="radio" name="crown_density" value="<?= $o ?>" <?= yn($d,'crown_density',$o) ?>> <?= $o ?></label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- CROWN & BRANCHES DEFECTS -->
  <div class="card">
    <h2>Tree Defects — Crown &amp; Branches</h2>
    
    <div class="grid3">
      <div class="yn-item">
        <span style="font-weight:700">UNBALANCED CROWN:</span>
        <div class="yn-buttons">
          <label><input type="radio" name="unbalanced_crown" value="YES" <?= yn($d,'unbalanced_crown','YES') ?>> Yes</label>
          <label><input type="radio" name="unbalanced_crown" value="NO"  <?= yn($d,'unbalanced_crown','NO') ?>> No</label>
        </div>
      </div>
      
      <div class="yn-item">
        <span style="font-weight:700">DEAD TWIGS/BRANCHES:</span>
        <div class="yn-buttons">
          <label><input type="radio" name="dead_twigs" value="YES" <?= yn($d,'dead_twigs','YES') ?>> Yes</label>
          <label><input type="radio" name="dead_twigs" value="NO"  <?= yn($d,'dead_twigs','NO') ?>> No</label>
        </div>
      </div>
      
      <div class="yn-item">
        <span style="font-weight:700">BROKEN/HANGERS:</span>
        <div class="yn-buttons">
          <label><input type="radio" name="broken_hangers" value="YES" <?= yn($d,'broken_hangers','YES') ?>> Yes</label>
          <label><input type="radio" name="broken_hangers" value="NO"  <?= yn($d,'broken_hangers','NO') ?>> No</label>
        </div>
      </div>
      
      <div class="yn-item">
        <span style="font-weight:700">OVER-EXTENDED BRANCHES:</span>
        <div class="yn-buttons">
          <label><input type="radio" name="over_extended" value="YES" <?= yn($d,'over_extended','YES') ?>> Yes</label>
          <label><input type="radio" name="over_extended" value="NO"  <?= yn($d,'over_extended','NO') ?>> No</label>
        </div>
      </div>
    </div>

    <!-- Additional details for dead twigs and broken branches -->
    <div class="grid3" style="margin-top:15px">
      <div class="field">
        <label>Dead Twigs % Overall</label>
        <input type="text" name="dead_twigs_pct" value="<?= v($d,'dead_twigs_pct') ?>">
      </div>
      <div class="field">
        <label>Dead Twigs Max DIA</label>
        <input type="text" name="dead_twigs_dia" value="<?= v($d,'dead_twigs_dia') ?>">
      </div>
      <div class="field">
        <label>Broken Number</label>
        <input type="text" name="broken_num" value="<?= v($d,'broken_num') ?>">
      </div>
      <div class="field">
        <label>Broken Max DIA</label>
        <input type="text" name="broken_dia" value="<?= v($d,'broken_dia') ?>">
      </div>
    </div>

    <div class="form-section">
      <p style="font-size:12px;font-weight:700;margin-bottom:8px">PRUNING HISTORY</p>
      <div class="grid4">
        <?php
        $prunes = [
            'prune_cleaned' => 'Crown Cleaned',
            'prune_thinned' => 'Thinned',
            'prune_raised'  => 'Raised',
            'prune_reduced' => 'Reduced',
            'prune_topped'  => 'Topped',
            'prune_lion'    => 'Lion-Tailed',
            'prune_flush'   => 'Flush Cuts',
            'prune_none'    => 'None',
        ];
        foreach ($prunes as $k => $lbl): ?>
        <label class="chkrow">
          <input type="checkbox" name="<?= $k ?>" value="1" <?= chk($d,$k) ?>> <?= $lbl ?>
        </label>
        <?php endforeach; ?>
        <div class="field">
          <label>Other</label>
          <input type="text" name="prune_other" value="<?= v($d,'prune_other') ?>">
        </div>
      </div>
    </div>

    <div class="form-section">
      <p style="font-size:12px;font-weight:700;margin-bottom:8px">CROWN CONDITIONS</p>
      <div class="grid-yn">
        <?php
        $crownYN = [
            'crown_cracks'         => 'Cracks',
            'lightning_crown'      => 'Lightning Damage',
            'codominant'           => 'Codominant',
            'included_bark_crown'  => 'Included Bark',
            'weak_attachment'      => 'Weak Attachment',
            'cavity_crown'         => 'Cavity/Nest Hole',
            'prev_branch_fail'     => 'Previous Branch Failures',
            'dead_missing_bark_crown' => 'Dead/Missing Bark',
            'cankers_crown'        => 'Cankers/Galls/Burls',
            'sapwood_decay_crown'  => 'Sapwood Damage/Decay',
            'conks_crown'          => 'Conks',
            'heartwood_crown'      => 'Heartwood Decay',
        ];
        foreach ($crownYN as $k => $lbl): ?>
        <div class="yn-item">
          <span style="font-weight:700"><?= $lbl ?>:</span>
          <div class="yn-buttons">
            <label><input type="radio" name="<?= $k ?>" value="YES" <?= yn($d,$k,'YES') ?>> Yes</label>
            <label><input type="radio" name="<?= $k ?>" value="NO"  <?= yn($d,$k,'NO') ?>> No</label>
          </div>
          <?php if ($k === 'cavity_crown'): ?>
          <input type="text" name="cavity_crown_pct" value="<?= v($d,'cavity_crown_pct') ?>" placeholder="% Circ" style="width:60px">
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- TRUNK DEFECTS -->
  <div class="card">
    <h2>Tree Defects — Trunk</h2>
    <div class="grid-yn">
      <?php
      $trunkYN = [
          'trunk_dead_bark'     => 'Dead/Missing Bark',
          'abnormal_bark'       => 'Abnormal Bark Texture',
          'codominant_stems'    => 'Codominant Stems',
          'included_bark_trunk' => 'Included Bark',
          'trunk_cracks'        => 'Cracks',
          'sapwood_trunk'       => 'Sapwood Damage/Decay',
          'cankers_trunk'       => 'Cankers/Galls/Burls',
          'sap_ooze'            => 'Sap Ooze',
          'lightning_trunk'     => 'Lightning Damage',
          'heartwood_trunk'     => 'Heartwood Decay',
          'conks_trunk'         => 'Conks',
          'cavity_trunk'        => 'Cavity/Nest Hole',
          'lean'                => 'Lean',
          'response_growth'     => 'Response Growth',
      ];
      foreach ($trunkYN as $k => $lbl): ?>
      <div class="yn-item">
        <span style="font-weight:700"><?= $lbl ?>:</span>
        <div class="yn-buttons">
          <label><input type="radio" name="<?= $k ?>" value="YES" <?= yn($d,$k,'YES') ?>> Yes</label>
          <label><input type="radio" name="<?= $k ?>" value="NO"  <?= yn($d,$k,'NO') ?>> No</label>
        </div>
        <?php if ($k === 'cavity_trunk'): ?>
        <input type="text" name="cavity_trunk_pct" value="<?= v($d,'cavity_trunk_pct') ?>" placeholder="% Circ" style="width:60px">
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="field form-section">
      <label>Other Trunk Issues</label>
      <input type="text" name="trunk_other" value="<?= v($d,'trunk_other') ?>">
    </div>
  </div>

  <!-- ROOT DEFECTS -->
  <div class="card">
    <h2>Tree Defects — Roots &amp; Root Collar</h2>
    
    <div class="yn-item" style="margin-bottom:15px">
      <span style="font-weight:700">COLLAR BURIED/NOT VISIBLE:</span>
      <div class="yn-buttons">
        <label><input type="radio" name="collar_buried" value="YES" <?= yn($d,'collar_buried','YES') ?>> Yes</label>
        <label><input type="radio" name="collar_buried" value="NO"  <?= yn($d,'collar_buried','NO') ?>> No</label>
      </div>
      <input type="text" name="collar_depth" value="<?= v($d,'collar_depth') ?>" placeholder="Depth" style="width:80px; margin-left:10px">
    </div>
    
    <div class="grid-yn">
      <?php
      $rootYN = [
          'stem_girdling'      => 'Stem Girdling',
          'root_dead'          => 'Dead',
          'root_decay'         => 'Decay',
          'root_conks'         => 'Conks',
          'root_ooze'          => 'Ooze',
          'root_cracks'        => 'Cracks',
          'cavity_root'        => 'Cavity/Nest Hole',
          'cut_damage_roots'   => 'Cut/Damage Roots',
          'root_plate_lifting' => 'Root Plate Lifting',
          'soil_weakness'      => 'Soil Weakness',
          'root_response'      => 'Response Growth',
      ];
      foreach ($rootYN as $k => $lbl): ?>
      <div class="yn-item">
        <span style="font-weight:700"><?= $lbl ?>:</span>
        <div class="yn-buttons">
          <label><input type="radio" name="<?= $k ?>" value="YES" <?= yn($d,$k,'YES') ?>> Yes</label>
          <label><input type="radio" name="<?= $k ?>" value="NO"  <?= yn($d,$k,'NO') ?>> No</label>
        </div>
        <?php if ($k === 'cavity_root'): ?>
        <input type="text" name="cavity_root_pct" value="<?= v($d,'cavity_root_pct') ?>" placeholder="% Circ" style="width:60px">
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="field form-section">
      <label>Other Root Issues</label>
      <input type="text" name="root_other" value="<?= v($d,'root_other') ?>">
    </div>
  </div>

  <!-- OVERALL COMMENTS -->
  <div class="card">
    <h2>Overall Tree Inspection Comments</h2>
    <div class="field">
      <label>Notes</label>
      <textarea name="notes" rows="4"><?= v($d,'notes') ?></textarea>
    </div>
  </div>

      <!-- MITIGATION + PREPARED BY -->
  <div class="card">
    <h2>Mitigation Options &amp; Priority</h2>
    
    <!-- Mitigation Options Table - Compact -->
    <div class="table-wrapper" style="margin-bottom: 12px;">
      <table class="mitigation-table" style="width: 100%; min-width: 280px; font-size: 11px;">
        <thead>
          <tr>
            <th style="text-align: left; padding: 6px 4px;">Option</th>
            <th style="text-align: center; padding: 6px 2px;">1</th>
            <th style="text-align: center; padding: 6px 2px;">2</th>
            <th style="text-align: center; padding: 6px 2px;">3</th>
            <th style="text-align: center; padding: 6px 2px;">4</th>
            <th style="text-align: center; padding: 6px 2px;">5</th>
          </tr>
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
          ?>
          <?php foreach ($mitigationOptions as $value => $label): 
              $optionKey = 'mitigation_' . preg_replace('/[^a-zA-Z0-9]/', '_', $value);
              $savedPriority = isset($d[$optionKey]) ? intval($d[$optionKey]) : 0;
          ?>
          <tr>
            <td style="text-align: left; padding: 6px 4px; font-weight: 500;">
              <?= $label ?>
            </td>
            <?php for ($i = 1; $i <= 5; $i++): ?>
            <td style="text-align: center; padding: 4px 2px;">
              <button type="button" 
                      class="priority-btn" 
                      data-option="<?= htmlspecialchars($value) ?>" 
                      data-priority="<?= $i ?>"
                      style="width: 26px; height: 26px; border-radius: 50%; border: 2px solid <?= ($savedPriority === $i) ? '#3b82f6' : '#cbd5e1' ?>; background: <?= ($savedPriority === $i) ? '#3b82f6' : 'white' ?>; cursor: pointer;">
              </button>
            </td>
            <?php endfor; ?>
            <input type="hidden" name="<?= $optionKey ?>" value="<?= $savedPriority ?>" id="hidden_<?= $optionKey ?>">
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  </div>

  <!-- PREPARED BY -->
  <div class="card">
    <h2>Prepared By</h2>
    <div class="grid2">
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
  <div class="btn-row no-print" style="margin-bottom:40px">
    <button type="submit" class="btn btn-green" style="font-size:16px;padding:12px 28px">Save Inspection</button>
    <?php if (!empty($d)): ?>
    <a href="print.php?id=<?= $d['insp_id'] ?>" class="btn btn-orange" target="_blank">Print Form</a>
    <?php endif; ?>
    <a href="index.php?upload_id=<?= $upload_id ?>" class="btn btn-gray">← Back to List</a>
  </div>

</form>
</div>
<script>
// Mitigation table priority button toggling
document.querySelectorAll('.priority-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const option = this.dataset.option;
        const priority = this.dataset.priority;
        const optionKey = 'mitigation_' + option.replace(/[^a-zA-Z0-9]/g, '_');
        const hiddenInput = document.getElementById('hidden_' + optionKey);
        const allBtnsInRow = this.closest('tr').querySelectorAll('.priority-btn');
        
        // Check if this button is already selected
        const isSelected = this.style.backgroundColor === 'rgb(59, 130, 246)' || 
                          this.style.backgroundColor === '#3b82f6';
        
        if (isSelected) {
            // Unselect - clear this option
            this.style.backgroundColor = 'white';
            this.style.borderColor = '#cbd5e1';
            if (hiddenInput) hiddenInput.value = '';
        } else {
            // Unselect all buttons in this row first
            allBtnsInRow.forEach(btn => {   
                btn.style.backgroundColor = 'white';
                btn.style.borderColor = '#cbd5e1';
            });
            
            // Select this button
            this.style.backgroundColor = '#3b82f6';
            this.style.borderColor = '#3b82f6';
            if (hiddenInput) hiddenInput.value = priority;
        }
    });
});
</script>

</body>
</html>