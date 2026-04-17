<?php
require_once 'config/db.php';
require_once 'config/settings.php';
$pdo = db();

$upload_id = intval($_GET['upload_id'] ?? 0);
if (!$upload_id) {
    header('Location: index.php');
    exit;
}

// Get upload/dataset info
$uploadStmt = $pdo->prepare('SELECT * FROM uploads WHERE upload_id = ?');
$uploadStmt->execute([$upload_id]);
$upload = $uploadStmt->fetch();

if (!$upload) {
    header('Location: index.php');
    exit;
}

// Get all trees with their inspections
$stmt = $pdo->prepare('
    SELECT 
        t.*,
        i.*
    FROM trees t 
    LEFT JOIN inspections i ON i.tree_no = t.id AND i.upload_id = t.upload_id
    WHERE t.upload_id = ?
    ORDER BY t.id ASC
');
$stmt->execute([$upload_id]);
$trees = $stmt->fetchAll();

function val($d, $k) { return htmlspecialchars($d[$k] ?? ''); }
function box($d, $k) { return !empty($d[$k]) ? '■' : '☐'; }
function ynBox($d, $k, $compare) { 
    $value = $d[$k] ?? 'NO';
    return ($value === $compare) ? '◼' : '☐';
}
function formatMeasurement($value, $unit, $default = '______') {
    if (!empty($value) && $value !== '') {
        return htmlspecialchars($value) . ' ' . $unit;
    }
    return $default;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($upload['upload_name']) ?> - Complete Inspection Report</title>
    <style>
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family: Arial, sans-serif; font-size:10px; background:#fff; color:#000; line-height:1.3; }

        .no-print {
            padding:12px 16px;
            background:#f0f2f5;
            display:flex;
            gap:10px;
            align-items:center;
            flex-wrap:wrap;
            position:sticky;
            top:0;
            z-index:100;
        }
        .no-print button, .no-print a {
            padding:8px 18px;
            border-radius:6px;
            border:none;
            cursor:pointer;
            font-size:13px;
            font-weight:600;
            text-decoration:none;
        }
        .btn-print { background:#2563eb; color:#fff; }
        .btn-excel { background:#10b981; color:#fff; }
        .btn-back { background:#6b7280; color:#fff; }

        .form {
            width: 210mm;
            margin: 0 auto;
            padding: 8mm 10mm;
            background: #fff;
            page-break-after: always;
        }
        
        .form:last-child {
            page-break-after: auto;
        }

        .title {
            text-align:center;
            font-size:14px;
            font-weight:bold;
            text-decoration:underline;
            margin-bottom:8px;
        }

        .row {
            display:flex;
            align-items:baseline;
            gap:6px;
            margin-bottom:4px;
            flex-wrap:wrap;
        }

        .lbl  { font-weight:bold; white-space:nowrap; font-size:9.5px; min-width:90px; }
        .lbl-auto { min-width: auto; margin-right: 5px; }
        .val  { border-bottom:1px solid #000; min-width:60px; padding:0 3px; font-size:9px; display:inline-block; }
        .val-md  { min-width:100px; }
        .val-lg  { min-width:150px; }
        .val-xl  { min-width:200px; }

        hr { border:none; border-top:1px solid #000; margin:5px 0; }

        .sec {
            text-align:center;
            font-weight:bold;
            text-decoration:underline;
            font-size:11px;
            margin:8px 0 5px;
        }
        .subsec {
            text-align:center;
            font-style:italic;
            margin:5px 0 4px;
            font-size:9.5px;
        }

        .inline {
            display:inline-flex;
            align-items:baseline;
            gap:3px;
            margin-right:10px;
            white-space:nowrap;
        }

        .yn-pair {
            display:inline-flex;
            align-items:baseline;
            gap:1px;
            margin-right:8px;
            white-space:nowrap;
        }

        .grid3p {
            display:grid;
            grid-template-columns:repeat(3, 1fr);
            gap:4px 12px;
            margin-bottom:6px;
        }

        .mit-table { width:100%; border-collapse:collapse; margin-top:4px; }
        .mit-table th, .mit-table td { border:1px solid #aaa; padding:3px 6px; font-size:9px; text-align:center; }
        .mit-table td:first-child { text-align:left; }

        .signature-section {
            margin-top: 20px;
        }

        .notes-section {
            margin: 8px 0;
            page-break-inside: avoid;
        }
        .notes-label {
            font-weight: bold;
            font-size: 10px;
            margin-bottom: 4px;
        }
        .notes-content {
            border: 1px solid #ccc;
            padding: 10px;
            min-height: 60px;
            font-size: 9px;
            line-height: 1.4;
            word-wrap: break-word;
            background: #fafafa;
        }

        @media print {
            .no-print { display:none !important; }
            body { font-size:9px; }
            @page { size:A4; margin:8mm; }
            .form {
                page-break-after: always;
            }
            .form:last-child {
                page-break-after: auto;
            }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button class="btn-print" onclick="window.print()">🖨️ Print / Save as PDF</button>
    <button class="btn-excel" onclick="window.location.href='export_excel.php?upload_id=<?= $upload_id ?>'">📊 Export to Excel</button>
    <a href="index.php?upload_id=<?= $upload_id ?>" class="btn-back">← Back</a>
</div>

<?php foreach ($trees as $tree): 
    // Use default values if inspection doesn't exist or fields are empty
    $display_preparer_name = !empty($tree['preparer_name']) ? $tree['preparer_name'] : $settings['default_preparer_name'];
?>
<div class="form">
    <div class="title">TREE INSPECTION FORM</div>
    <hr>

    <!-- CLIENT + TREE INFO -->
    <div class="row">
        <span class="lbl">CLIENT:</span>
        <span class="val val-lg"><?= val($tree, 'client') ?></span>
        <span class="lbl">TREE ID:</span>
        <span class="val val-lg"><?= val($tree, 'tree_id') ?></span>
    </div>
    <div class="row">
        <span class="lbl">TREE LOCATION:</span>
        <span class="val val-lg"><?= val($tree, 'tree_location') ?></span>
        <span class="lbl">TREE SPECIES:</span>
        <span class="val val-lg"><?= val($tree, 'tree_species') ?></span>
        <span class="lbl lbl-auto">DBH:</span>
        <span class="val val-md"><?= formatMeasurement($tree['dbh'] ?? '', 'mm') ?></span>
    </div>
    <div class="row">
        <span class="lbl">HEIGHT:</span>
        <span class="val"><?= formatMeasurement($tree['tree_height'] ?? '', 'm') ?></span>
        <span class="lbl">CROWN SPREAD DIA:</span>
        <span class="val val-md"><?= formatMeasurement($tree['crown_diameter'] ?? '', 'm') ?></span>
        <span class="lbl">TREE CIRCUMFERENCE:</span>
        <span class="val val-md"><?= val($tree, 'tree_circumference') ?: '______' ?></span>
    </div>
    <hr>

    <!-- SITE FACTORS -->
    <div class="sec">SITE FACTORS</div>
    <div class="row">
        <span class="lbl">TOPOGRAPHY:</span>
        <span class="inline">FLAT <?= box($tree, 'topography_flat') ?></span>
        <span class="inline">SLOPE <?= box($tree, 'topography_slope') ?></span>
    </div>
    <div class="row">
        <span class="lbl">SITE CHANGES:</span>
        <span class="inline">NONE <?= box($tree, 'site_none') ?></span>
        <span class="inline">GRADE CHANGE <?= box($tree, 'site_grade') ?></span>
        <span class="inline">SITE CLEARING <?= box($tree, 'site_clearing') ?></span>
        <span class="inline">CHANGE SOIL HYDROLOGY <?= box($tree, 'site_hydrology') ?></span>
        <span class="inline">ROOT CUTS <?= box($tree, 'site_root_cuts') ?></span>
    </div>
    <div class="row">
        <span class="lbl">SOIL CONDITIONS:</span>
        <span class="inline">LIMITED VOLUME <?= box($tree, 'soil_limited') ?></span>
        <span class="inline">SATURATED <?= box($tree, 'soil_saturated') ?></span>
        <span class="inline">SHALLOW <?= box($tree, 'soil_shallow') ?></span>
        <span class="inline">COMPACTED <?= box($tree, 'soil_compacted') ?></span>
        <span class="inline">PAVEMENT OVER ROOTS <?= box($tree, 'soil_pavement') ?></span>
        <span class="inline">NORMAL <?= box($tree, 'soil_normal') ?></span>
    </div>
    <div class="row">
        <span class="lbl">PREVAILING WIND DIRECTION:</span>
        <span class="yn-pair">YES <?= ynBox($tree, 'prevailing_wind', 'YES') ?></span>
        <span class="yn-pair">NO <?= ynBox($tree, 'prevailing_wind', 'NO') ?></span>
        <span class="lbl" style="margin-left:10px">COMMON WEATHER:</span>
        <span class="inline">STRONG WINDS <?= box($tree, 'weather_strong') ?></span>
        <span class="inline">HEAVY RAIN <?= box($tree, 'weather_rain') ?></span>
        <span class="inline">NORMAL <?= box($tree, 'weather_normal') ?></span>
    </div>
    <hr>

    <!-- TREE HEALTH -->
    <div class="sec">TREE HEALTH AND SPECIES PROFILE</div>
    <div class="row">
        <span class="lbl">VIGOR:</span>
        <span class="yn-pair">LOW <?= ynBox($tree, 'vigor', 'Low') ?></span>
        <span class="yn-pair">NORMAL <?= ynBox($tree, 'vigor', 'Normal') ?></span>
        <span class="yn-pair">HIGH <?= ynBox($tree, 'vigor', 'High') ?></span>
    </div>
    <div class="row">
        <span class="lbl">FOLIAGE:</span>
        <span class="inline">NONE <?= box($tree, 'foliage_none') ?></span>
        <span class="inline">NORMAL <?= box($tree, 'foliage_normal') ?></span>
        <span class="inline">CHLOROTIC <?= box($tree, 'foliage_chlorotic') ?></span>
        <?php if (!empty($tree['foliage_chlorotic_pct'])): ?> <?= val($tree, 'foliage_chlorotic_pct') ?>%<?php endif; ?>
        <span class="inline" style="margin-left:5px">NECROTIC <?= box($tree, 'foliage_necrotic') ?></span>
        <?php if (!empty($tree['foliage_necrotic_pct'])): ?> <?= val($tree, 'foliage_necrotic_pct') ?>%<?php endif; ?>
    </div>
    <div class="row">
        <span class="lbl">SPECIES FAILURE PROFILE:</span>
        <span class="inline">BRANCHES <?= box($tree, 'sp_branches') ?></span>
        <span class="inline">TRUNK <?= box($tree, 'sp_trunk') ?></span>
        <span class="inline">ROOTS <?= box($tree, 'sp_roots') ?></span>
        <span class="lbl lbl-auto">DESCRIBE</span>
        <span class="val val-md"><?= val($tree, 'sp_describe') ?></span>
        <span class="inline">NONE <?= box($tree, 'sp_none') ?></span>
    </div>
    <hr>

    <!-- LOAD FACTORS -->
    <div class="sec">LOAD FACTORS</div>
    <div class="row">
        <span class="lbl">WIND EXPOSURE:</span>
        <span class="yn-pair">PROTECTED <?= ynBox($tree, 'wind_exposure', 'Protected') ?></span>
        <span class="yn-pair">PARTIAL <?= ynBox($tree, 'wind_exposure', 'Partial') ?></span>
        <span class="yn-pair">FULL <?= ynBox($tree, 'wind_exposure', 'Full') ?></span>
        <span class="yn-pair">WIND FUNNELING <?= ynBox($tree, 'wind_exposure', 'Wind Funneling') ?></span>
        <span class="yn-pair">NONE <?= ynBox($tree, 'wind_exposure', 'None') ?></span>
    </div>
    <div class="row">
        <span class="lbl">RELATIVE CROWN SIZE:</span>
        <span class="yn-pair">SMALL <?= ynBox($tree, 'relative_crown_size', 'Small') ?></span>
        <span class="yn-pair">MEDIUM <?= ynBox($tree, 'relative_crown_size', 'Medium') ?></span>
        <span class="yn-pair">LARGE <?= ynBox($tree, 'relative_crown_size', 'Large') ?></span>
        <span class="lbl" style="margin-left:12px">CROWN DENSITY:</span>
        <span class="yn-pair">SPARSE <?= ynBox($tree, 'crown_density', 'Sparse') ?></span>
        <span class="yn-pair">NORMAL <?= ynBox($tree, 'crown_density', 'Normal') ?></span>
        <span class="yn-pair">DENSE <?= ynBox($tree, 'crown_density', 'Dense') ?></span>
    </div>
    <hr>

    <!-- TREE DEFECTS AND CONDITIONS -->
    <div class="sec">TREE DEFECTS AND CONDITIONS</div>
    <div class="subsec">- Crown and Branches -</div>

    <div class="row">
        <span class="lbl">UNBALANCED CROWN:</span>
        <span class="yn-pair">YES <?= ynBox($tree, 'unbalanced_crown', 'YES') ?></span>
        <span class="yn-pair">NO <?= ynBox($tree, 'unbalanced_crown', 'NO') ?></span>
        <span class="lbl" style="margin-left:10px">DEAD TWIGS/BRANCHES:</span>
        <span class="yn-pair">YES <?= ynBox($tree, 'dead_twigs', 'YES') ?></span>
        <span class="yn-pair">NO <?= ynBox($tree, 'dead_twigs', 'NO') ?></span>
        <span class="val" style="min-width:28px"><?= val($tree, 'dead_twigs_pct') ?></span>% OVERALL
        <span class="lbl lbl-auto">MAX DIA</span>
        <span class="val"><?= val($tree, 'dead_twigs_dia') ?></span>
    </div>
    <div class="row">
        <span class="lbl">BROKEN/HANGERS:</span>
        <span class="yn-pair">YES <?= ynBox($tree, 'broken_hangers', 'YES') ?></span>
        <span class="yn-pair">NO <?= ynBox($tree, 'broken_hangers', 'NO') ?></span>
        <span class="lbl lbl-auto">NUMBER</span>
        <span class="val"><?= val($tree, 'broken_num') ?></span>
        <span class="lbl lbl-auto">MAX DIA</span>
        <span class="val"><?= val($tree, 'broken_dia') ?></span>
        <span class="lbl" style="margin-left:10px">OVER-EXTENDED BRANCHES:</span>
        <span class="yn-pair">YES <?= ynBox($tree, 'over_extended', 'YES') ?></span>
        <span class="yn-pair">NO <?= ynBox($tree, 'over_extended', 'NO') ?></span>
    </div>

    <div class="row">
        <span class="lbl">PRUNING HISTORY:</span>
        <span class="inline">CROWN CLEANED <?= box($tree, 'prune_cleaned') ?></span>
        <span class="inline">THINNED <?= box($tree, 'prune_thinned') ?></span>
        <span class="inline">RAISED <?= box($tree, 'prune_raised') ?></span>
        <span class="inline">REDUCED <?= box($tree, 'prune_reduced') ?></span>
        <span class="inline">TOPPED <?= box($tree, 'prune_topped') ?></span>
        <span class="inline">LION-TAILED <?= box($tree, 'prune_lion') ?></span>
        <span class="inline">FLUSH CUTS <?= box($tree, 'prune_flush') ?></span>
        <span class="inline">OTHER <?= !empty($tree['prune_other']) ? '☑' : '☐' ?></span>
        <span class="inline">NONE <?= box($tree, 'prune_none') ?></span>
    </div>

    <div class="grid3p">
        <?php
        $crownYN = [
            'crown_cracks' => 'CRACKS',
            'lightning_crown' => 'LIGHTNING DAMAGE',
            'codominant' => 'CODOMINANT',
            'included_bark_crown' => 'INCLUDED BARK',
            'weak_attachment' => 'WEAK ATTACHMENT',
            'prev_branch_fail' => 'PREVIOUS BRANCH FAILURES',
            'dead_missing_bark_crown' => 'DEAD/MISSING BARK',
            'cankers_crown' => 'CANKERS/GALLS/BURLS',
            'sapwood_decay_crown' => 'SAPWOOD DAMAGE/DECAY',
            'conks_crown' => 'CONKS',
            'heartwood_crown' => 'HEARTWOOD DECAY',
        ];
        foreach ($crownYN as $k => $lbl): ?>
        <div class="row">
            <?= $lbl ?>: 
            <span class="yn-pair">YES <?= ynBox($tree, $k, 'YES') ?></span>
            <span class="yn-pair">NO <?= ynBox($tree, $k, 'NO') ?></span>
        </div>
        <?php endforeach; ?>
        <div class="row">
            CAVITY/NEST HOLE: 
            <span class="yn-pair">YES <?= ynBox($tree, 'cavity_crown', 'YES') ?></span>
            <span class="yn-pair">NO <?= ynBox($tree, 'cavity_crown', 'NO') ?></span>
            <?php if (!empty($tree['cavity_crown_pct'])): ?>
            <span><?= val($tree, 'cavity_crown_pct') ?>% CIRC.</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Trunk Defects -->
    <div class="subsec">- Trunk -</div>
    <div class="grid3p">
        <?php
        $trunkYN = [
            'trunk_dead_bark' => 'DEAD/MISSING BARK',
            'abnormal_bark' => 'ABNORMAL BARK TEXTURE/COLOR',
            'codominant_stems' => 'CODOMINANT STEMS',
            'included_bark_trunk' => 'INCLUDED BARK',
            'trunk_cracks' => 'CRACKS',
            'sapwood_trunk' => 'SAPWOOD DAMAGE/DECAY',
            'cankers_trunk' => 'CANKERS/GALLS/BURLS',
            'sap_ooze' => 'SAP OOZE',
            'lightning_trunk' => 'LIGHTNING DAMAGE',
            'heartwood_trunk' => 'HEARTWOOD DECAY',
            'conks_trunk' => 'CONKS',
            'lean' => 'LEAN',
            'response_growth' => 'RESPONSE GROWTH',
        ];
        foreach ($trunkYN as $k => $lbl): ?>
        <div class="row">
            <?= $lbl ?>: 
            <span class="yn-pair">YES <?= ynBox($tree, $k, 'YES') ?></span>
            <span class="yn-pair">NO <?= ynBox($tree, $k, 'NO') ?></span>
        </div>
        <?php endforeach; ?>
        <div class="row">
            CAVITY/NEST HOLE: 
            <span class="yn-pair">YES <?= ynBox($tree, 'cavity_trunk', 'YES') ?></span>
            <span class="yn-pair">NO <?= ynBox($tree, 'cavity_trunk', 'NO') ?></span>
            <?php if (!empty($tree['cavity_trunk_pct'])): ?>
            <span><?= val($tree, 'cavity_trunk_pct') ?>% CIRC.</span>
            <?php endif; ?>
        </div>
    </div>
    <?php if (!empty($tree['trunk_other'])): ?>
    <div class="row">
        <span class="lbl">OTHER:</span>
        <span class="val val-xl"><?= val($tree, 'trunk_other') ?></span>
    </div>
    <?php endif; ?>

    <!-- Root Defects -->
    <div class="subsec">- Roots and Root Collar -</div>
    <div class="row">
        <span class="lbl">COLLAR BURIED/NOT VISIBLE:</span>
        <span class="yn-pair">YES <?= ynBox($tree, 'collar_buried', 'YES') ?></span>
        <span class="yn-pair">NO <?= ynBox($tree, 'collar_buried', 'NO') ?></span>
        <span class="lbl lbl-auto">Depth:</span>
        <span class="val"><?= val($tree, 'collar_depth') ?></span>
        <span class="lbl" style="margin-left:10px">STEM GIRDLING:</span>
        <span class="yn-pair">YES <?= ynBox($tree, 'stem_girdling', 'YES') ?></span>
        <span class="yn-pair">NO <?= ynBox($tree, 'stem_girdling', 'NO') ?></span>
    </div>
    <div class="grid3p">
        <?php
        $rootYN = [
            'root_dead' => 'DEAD',
            'root_decay' => 'DECAY',
            'root_conks' => 'CONKS',
            'root_ooze' => 'OOZE',
            'root_cracks' => 'CRACKS',
            'cut_damage_roots' => 'CUT/DAMAGE ROOTS',
            'root_plate_lifting' => 'ROOT PLATE LIFTING',
            'soil_weakness' => 'SOIL WEAKNESS',
            'root_response' => 'RESPONSE GROWTH',
        ];
        foreach ($rootYN as $k => $lbl): ?>
        <div class="row">
            <?= $lbl ?>: 
            <span class="yn-pair">YES <?= ynBox($tree, $k, 'YES') ?></span>
            <span class="yn-pair">NO <?= ynBox($tree, $k, 'NO') ?></span>
        </div>
        <?php endforeach; ?>
        <div class="row">
            CAVITY/NEST HOLE: 
            <span class="yn-pair">YES <?= ynBox($tree, 'cavity_root', 'YES') ?></span>
            <span class="yn-pair">NO <?= ynBox($tree, 'cavity_root', 'NO') ?></span>
            <?php if (!empty($tree['cavity_root_pct'])): ?>
            <span><?= val($tree, 'cavity_root_pct') ?>% CIRC.</span>
            <?php endif; ?>
        </div>
    </div>
    <?php if (!empty($tree['root_other'])): ?>
    <div class="row">
        <span class="lbl">OTHER:</span>
        <span class="val val-xl"><?= val($tree, 'root_other') ?></span>
    </div>
    <?php endif; ?>
    <hr>

    <!-- OVERALL COMMENTS -->
    <div class="sec">OVERALL TREE INSPECTION COMMENTS</div>
    <div class="notes-section">
        <div class="notes-label">NOTES:</div>
        <div class="notes-content"><?= nl2br(htmlspecialchars($tree['notes'] ?? '')) ?></div>
    </div>
    <hr>

    <!-- MITIGATION + PREPARED BY -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:6px">
        <div>
            <div style="display:flex; justify-content:space-between; align-items:baseline; margin-bottom:8px;">
                <span style="font-weight:bold;text-decoration:underline;font-size:10px">MITIGATION OPTIONS</span>
                <span style="font-weight:bold;text-decoration:underline;font-size:10px; margin-right:30px;">OPTION PRIORITY</span>
            </div>
            <table class="mit-table">
                <thead>
                    <tr><th style="text-align:left">Option</th><th>1</th><th>2</th><th>3</th><th>4</th><th>5</th></tr>
                </thead>
                <tbody>
                    <?php
                    $mits = [
                        'No Action' => '+ NO ACTION',
                        'Clear Dead Branches' => '+ CLEAR DEAD BRANCHES',
                        'Normal Pruning' => '+ NORMAL PRUNING',
                        'Topping' => '+ TOPPING',
                        'Tree Cut' => '+ TREE CUT'
                    ];
                    foreach ($mits as $val => $label):
                        $fieldKey = 'mitigation_' . preg_replace('/[^a-zA-Z0-9]/', '_', $val);
                        $priority = intval($tree[$fieldKey] ?? 0);
                    ?>
                    <tr>
                        <td style="text-align:left"><?= $label ?></td>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <td style="text-align:center"><?= ($priority === $i) ? '☑' : '☐' ?></td>
                        <?php endfor; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="signature-section">
            <div style="font-weight:bold;text-decoration:underline;margin-bottom:70px;font-size:10px">PREPARED BY</div>
            <div>
                <div style="border-top: 1px solid #000; width: 100%;"></div>
                <div style="font-size:11px; font-weight:bold; margin-top:8px;"><?= htmlspecialchars($display_preparer_name) ?></div>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>
</body>
</html>