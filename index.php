<?php
require_once 'config/db.php';
$pdo = db();

// Safe rounding function to handle empty/null/string values
function safeRound($value, $decimals = 2) {
    if (empty($value) && $value !== 0 && $value !== '0') {
        return '—';
    }
    $numericValue = floatval($value);
    if ($numericValue == 0 && $value !== 0 && $value !== '0') {
        return '—';
    }
    return round($numericValue, $decimals);
}

// Function to check if inspection is complete based on filled fields
function isInspectionComplete($inspection) {
    // Required fields that indicate a complete inspection
    $requiredFields = [
        'tree_id', 'tree_location', 'tree_species', 'client',
        'dbh', 'height', 'crown_spread_dia', 'prepared_by'
    ];
    
    foreach ($requiredFields as $field) {
        if (empty($inspection[$field])) {
            return false;
        }
    }
    return true;
}

// Pagination settings
$items_per_page = 50;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Handle delete request
if (isset($_GET['delete_upload']) && is_numeric($_GET['delete_upload'])) {
    $delete_id = $_GET['delete_upload'];
    
    $deleteInspections = $pdo->prepare('DELETE FROM inspections WHERE upload_id = ?');
    $deleteInspections->execute([$delete_id]);
    
    $deleteTrees = $pdo->prepare('DELETE FROM trees WHERE upload_id = ?');
    $deleteTrees->execute([$delete_id]);
    
    $deleteUpload = $pdo->prepare('DELETE FROM uploads WHERE upload_id = ?');
    $deleteUpload->execute([$delete_id]);
    
    header('Location: index.php?deleted=1');
    exit;
}

// Get all uploads for dropdown
$uploads = $pdo->query('SELECT * FROM uploads ORDER BY upload_date DESC')->fetchAll();

// Get active upload ID
$active_upload_id = $_GET['upload_id'] ?? $_POST['upload_id'] ?? null;
if (!$active_upload_id && !empty($uploads)) {
    $activeUpload = $pdo->query('SELECT upload_id FROM uploads WHERE is_active = 1 LIMIT 1')->fetch();
    $active_upload_id = $activeUpload ? $activeUpload['upload_id'] : ($uploads[0]['upload_id'] ?? null);
}

// SEARCH & FILTER PARAMETERS
$search_tree_id = isset($_GET['search_tree_id']) ? trim($_GET['search_tree_id']) : '';
$search_location = isset($_GET['search_location']) ? trim($_GET['search_location']) : '';
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';

// Build WHERE conditions
$whereConditions = ["t.upload_id = ?"];
$params = [$active_upload_id];

if ($search_tree_id !== '') {
    $whereConditions[] = "t.id LIKE ?";
    $params[] = "%$search_tree_id%";
}

if ($search_location !== '') {
    $whereConditions[] = "i.tree_id LIKE ?";
    $params[] = "%$search_location%";
}

if ($filter_status !== '') {
    if ($filter_status === 'completed') {
        $whereConditions[] = "EXISTS (
            SELECT 1 FROM inspections i2 
            WHERE i2.tree_no = t.id 
            AND i2.upload_id = t.upload_id
            AND i2.tree_id IS NOT NULL AND i2.tree_id != ''
            AND i2.tree_location IS NOT NULL AND i2.tree_location != ''
            AND i2.tree_species IS NOT NULL AND i2.tree_species != ''
            AND i2.client IS NOT NULL AND i2.client != ''
            AND i2.dbh IS NOT NULL AND i2.dbh != ''
            AND i2.height IS NOT NULL AND i2.height != ''
            AND i2.crown_spread_dia IS NOT NULL AND i2.crown_spread_dia != ''
            AND i2.prepared_by IS NOT NULL AND i2.prepared_by != ''
        )";
    } elseif ($filter_status === 'incomplete') {
        $whereConditions[] = "NOT EXISTS (
            SELECT 1 FROM inspections i2 
            WHERE i2.tree_no = t.id 
            AND i2.upload_id = t.upload_id
            AND i2.tree_id IS NOT NULL AND i2.tree_id != ''
            AND i2.tree_location IS NOT NULL AND i2.tree_location != ''
            AND i2.tree_species IS NOT NULL AND i2.tree_species != ''
            AND i2.client IS NOT NULL AND i2.client != ''
            AND i2.dbh IS NOT NULL AND i2.dbh != ''
            AND i2.height IS NOT NULL AND i2.height != ''
            AND i2.crown_spread_dia IS NOT NULL AND i2.crown_spread_dia != ''
            AND i2.prepared_by IS NOT NULL AND i2.prepared_by != ''
        )";
    }
}

$whereClause = implode(' AND ', $whereConditions);

// Get total count with filters
$total_trees = 0;
$trees = [];
$selectedUploadName = '';
if ($active_upload_id) {
    $countSql = "
        SELECT COUNT(*) 
        FROM trees t 
        LEFT JOIN inspections i ON i.tree_no = t.id AND i.upload_id = t.upload_id
        WHERE $whereClause
    ";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total_trees = $countStmt->fetchColumn();
    
    $sql = "
        SELECT 
            t.*, 
            i.insp_id, 
            i.prepared_by,
            i.preparer_name,
            i.tree_id,
            i.tree_location,
            i.client,
            i.tree_species,
            i.dbh,
            i.height,
            i.crown_spread_dia,
            i.tree_circumference
        FROM trees t 
        LEFT JOIN inspections i ON i.tree_no = t.id AND i.upload_id = t.upload_id
        WHERE $whereClause
        ORDER BY t.id ASC
        LIMIT ? OFFSET ?
    ";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $idx => $val) {
        $stmt->bindValue($idx + 1, $val, PDO::PARAM_STR);
    }
    $stmt->bindValue(count($params) + 1, $items_per_page, PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $trees = $stmt->fetchAll();
    
    $uploadStmt = $pdo->prepare('SELECT upload_name FROM uploads WHERE upload_id = ?');
    $uploadStmt->execute([$active_upload_id]);
    $selectedUploadName = $uploadStmt->fetchColumn();
}

$total_pages = ($total_trees > 0) ? ceil($total_trees / $items_per_page) : 1;
$msg = $_GET['msg'] ?? '';
$deleted = $_GET['deleted'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Tree Inspection System | ERP</title>
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
            overflow-x: hidden;
        }

        .app-wrapper {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background: #1e293b;
            color: #e2e8f0;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
        }

        .sidebar-header {
            display: flex;
            flex-direction: column; 
            padding: 24px 20px;
            border-bottom: 1px solid #334155;
        }

        .sidebar-logo {
            text-align: center;
        }

        .sidebar-logo-img {
            width: 100%;
            max-width: 180px;
            height: auto;
            margin-bottom: 12px;
        }

        .sidebar-logo-sub {
            font-size: 13px;
            color: #94a3b8;
            margin-top: 4px;
        }

        .sidebar-nav {
            flex: 1;
            padding: 20px 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: #cbd5e1;
            text-decoration: none;
            transition: all 0.2s;
            margin: 4px 8px;
            border-radius: 8px;
        }

        .nav-item:hover {
            background: #334155;
            color: white;
        }

        .nav-item.active {
            background: #b91c1c;
            color: white;
        }

        .nav-item i {
            width: 20px;
            font-size: 16px;
        }

        .nav-item span {
            font-size: 14px;
            font-weight: 500;
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid #334155;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            background: #b91c1c;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .user-name {
            font-size: 13px;
            font-weight: 600;
            color: white;
        }

        .user-role {
            font-size: 11px;
            color: #94a3b8;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            min-height: 100vh;
        }

        .top-bar {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 99;
        }

        .page-title h1 {
            font-size: 20px;
            font-weight: 600;
            color: #0f172a;
        }

        .page-title p {
            font-size: 13px;
            color: #64748b;
            margin-top: 2px;
        }

        .content-area {
            padding: 24px 32px;
        }

        .control-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }

        .control-card-title {
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .control-card-title i {
            color: #b91c1c;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group label {
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #b91c1c;
            box-shadow: 0 0 0 3px rgba(185, 28, 28, 0.1);
        }

        .filter-bar {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: flex-end;
            width: 100%;
        }

        .filter-group {
            flex: 1;
            min-width: 0;
        }

        .filter-group label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 6px;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
            font-family: 'Inter', sans-serif;
            background: white;
            box-sizing: border-box;
        }

        .filter-actions {
            display: flex;
            gap: 12px;
            flex-shrink: 0;
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
            border-color: #b91c1c;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 11px;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .data-table-wrapper {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: white;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .data-table th {
            background: #f8fafc;
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .data-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
        }

        .data-table tr:hover td {
            background: #fafcff;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-completed {
            background: #dcfce7;
            color: #166534;
        }

        .status-incomplete {
            background: #fef3c7;
            color: #92400e;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stat-card-info {
            display: flex;
            flex-direction: column;
        }

        .stat-card-label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card-value {
            font-size: 28px;
            font-weight: 700;
            color: #0f172a;
        }

        .stat-card-icon {
            width: 48px;
            height: 48px;
            background: #fef2f2;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #b91c1c;
            font-size: 24px;
        }

        .dataset-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 20px;
        }

        .dataset-selector {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .dataset-selector select {
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
            background: white;
        }

        .dataset-info {
            font-size: 13px;
            color: #64748b;
        }

        .dataset-info strong {
            color: #0f172a;
        }

        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            flex-wrap: wrap;
            gap: 12px;
            padding: 16px;
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .pagination {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .pagination a, .pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 10px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .pagination a {
            background: white;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .pagination a:hover {
            background: #b91c1c;
            color: white;
            border-color: #b91c1c;
        }

        .pagination .active {
            background: #b91c1c;
            color: white;
            border: 1px solid #b91c1c;
        }

        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: #f1f5f9;
            color: #94a3b8;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .alert-error {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .action-buttons-cell {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
                width: 280px;
                z-index: 1000;
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 999;
            }
            
            .sidebar-overlay.active {
                display: block;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .content-area {
                padding: 16px;
            }
            
            .top-bar {
                padding: 12px 16px;
            }
            
            .menu-toggle {
                display: block;
                background: none;
                border: none;
                font-size: 20px;
                cursor: pointer;
                color: #475569;
            }
            
            .filter-bar {
                flex-direction: column;
            }
            
            .filter-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .filter-actions .btn {
                width: 100%;
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            
            .stat-card-value {
                font-size: 22px;
            }
            
            .stat-card-icon {
                width: 40px;
                height: 40px;
                font-size: 20px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .dataset-bar {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .dataset-selector {
                width: 100%;
                flex-direction: column;
            }
            
            .dataset-selector select,
            .dataset-selector .btn {
                width: 100%;
            }
            
            .mobile-cards {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }
            
            .desktop-table {
                display: none;
            }
            
            .tree-card {
                background: white;
                border: 1px solid #e2e8f0;
                border-radius: 10px;
                padding: 14px;
            }
            
            .tree-card-header {
                display: flex;
                justify-content: space-between;
                margin-bottom: 10px;
                padding-bottom: 8px;
                border-bottom: 1px solid #e2e8f0;
            }
            
            .tree-details {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
                margin-bottom: 12px;
            }
            
            .tree-actions {
                display: flex;
                gap: 8px;
            }
            
            .tree-actions .btn {
                flex: 1;
                justify-content: center;
            }
        }

        @media (min-width: 769px) {
            .mobile-cards {
                display: none;
            }
            .desktop-table {
                display: block;
            }
            .menu-toggle {
                display: none;
            }
        }
    </style>
</head>
<body>
<div class="app-wrapper">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <img src="assets/HS Innovators sd bhd transparent.png" alt="HS innovators Sdn Bhd" class="sidebar-logo-img" onerror="this.style.display='none'">
                <div class="sidebar-logo-sub">Tree Inspection Management</div>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="#" class="nav-item active">
                <i class="fas fa-tree"></i>
                <span>Tree Inventory</span>
            </a>
            <a href="#" class="nav-item">
                <i class="fas fa-chart-line"></i>
                <span>Analytics</span>
            </a>
            <a href="#" class="nav-item">
                <i class="fas fa-file-alt"></i>
                <span>Reports</span>
            </a>
            <a href="#" class="nav-item">
                <i class="fas fa-database"></i>
                <span>Datasets</span>
            </a>
            <a href="#" class="nav-item">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">A</div>
                <div>
                    <div class="user-name">Admin User</div>
                    <div class="user-role">System Administrator</div>
                </div>
            </div>
        </div>
    </aside>
    
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
    
    <main class="main-content">
        <div class="top-bar">
            <div style="display: flex; align-items: center; gap: 16px;">
                <button class="menu-toggle" id="menuToggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="page-title">
                    <h1>Tree Inventory</h1>
                    <p>Manage and inspect tree assets</p>
                </div>
            </div>
        </div>

        <div class="content-area">
            <?php if ($msg === 'uploaded'): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                Excel uploaded! <strong><?= (int)($_GET['count'] ?? 0) ?> trees</strong> imported.
            </div>
            <?php elseif ($msg === 'error'): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                Upload failed. Use .xlsx or .csv with correct columns.
            </div>
            <?php elseif ($deleted == 1): ?>
            <div class="alert alert-success">
                <i class="fas fa-trash-alt"></i>
                Dataset deleted successfully.
            </div>
            <?php elseif ($msg === 'missing_name'): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                Please provide a name for the dataset.
            </div>
            <?php endif; ?>

            <div class="control-card">
                <div class="control-card-title">
                    <i class="fas fa-cloud-upload-alt"></i> Import New Dataset
                </div>
                <form action="upload.php" method="POST" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Excel File (.xlsx, .csv)</label>
                            <input type="file" name="excel" accept=".xlsx,.csv" required>
                        </div>
                        <div class="form-group">
                            <label>Dataset Name</label>
                            <input type="text" name="upload_name" placeholder="e.g., Site A - March 2024" required>
                        </div>
                        <div class="form-group">
                            <label>Description (optional)</label>
                            <input type="text" name="description" placeholder="Additional notes">
                        </div>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="set_active" value="1" checked> Set as active dataset
                        </label>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Upload & Import</button>
                    </div>
                </form>
            </div>

            <?php if (!empty($uploads) && $active_upload_id): ?>
            <div class="control-card">
                <div class="control-card-title">
                    <i class="fas fa-database"></i> Dataset Management
                </div>
                <div class="dataset-bar">
                    <div class="dataset-selector">
                        <select id="datasetSelect" onchange="switchDataset()">
                            <?php foreach ($uploads as $u): ?>
                            <option value="<?= $u['upload_id'] ?>" <?= ($active_upload_id == $u['upload_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['upload_name']) ?> (<?= $u['row_count'] ?> trees) <?= $u['is_active'] ? '✓ Active' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-outline btn-sm" onclick="setAsActive()"><i class="fas fa-star"></i> Set Active</button>
                        <button class="btn btn-danger btn-sm" onclick="showDeleteModalForSelected()"><i class="fas fa-trash"></i> Delete</button>
                        <?php if ($total_trees > 0): ?>
                        <button class="btn btn-secondary btn-sm" onclick="window.location.href='print_all.php?upload_id=<?= $active_upload_id ?>'"><i class="fas fa-print"></i> Print All</button>
                        <button class="btn btn-secondary btn-sm" onclick="window.location.href='export_excel.php?upload_id=<?= $active_upload_id ?>'"><i class="fas fa-file-excel"></i> Export</button>
                        <?php endif; ?>
                    </div>
                    <div class="dataset-info">
                        <strong><?= htmlspecialchars($selectedUploadName) ?></strong> · <?= $total_trees ?> records
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($active_upload_id): ?>
            <div class="control-card">
                <div class="control-card-title">
                    <i class="fas fa-filter"></i> Filter Records
                </div>
                <form method="GET">
                    <input type="hidden" name="upload_id" value="<?= $active_upload_id ?>">
                    <div class="filter-bar">
                        <div class="filter-group">
                            <label>Tree ID (Database)</label>
                            <input type="text" name="search_tree_id" placeholder="Enter Tree ID" value="<?= htmlspecialchars($search_tree_id) ?>">
                        </div>
                        <div class="filter-group">
                            <label>Tree ID (Inspection)</label>
                            <input type="text" name="search_location" placeholder="Enter Inspection Tree ID" value="<?= htmlspecialchars($search_location) ?>">
                        </div>
                        <div class="filter-group">
                            <label>Status</label>
                            <select name="filter_status">
                                <option value="">All</option>
                                <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="incomplete" <?= $filter_status === 'incomplete' ? 'selected' : '' ?>>Incomplete</option>
                            </select>
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Apply</button>
                            <a href="?upload_id=<?= $active_upload_id ?>" class="btn btn-outline">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <?php if ($total_trees > 0): 
                $completedCount = 0;
                foreach ($trees as $t) {
                    if (!empty($t['insp_id']) && isInspectionComplete($t)) {
                        $completedCount++;
                    }
                }
                $incompleteCount = $total_trees - $completedCount;
                $completionPercent = $total_trees > 0 ? round(($completedCount / $total_trees) * 100) : 0;
            ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-info">
                        <span class="stat-card-label">Total Trees</span>
                        <span class="stat-card-value"><?= $total_trees ?></span>
                    </div>
                    <div class="stat-card-icon">
                        <i class="fas fa-tree"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-info">
                        <span class="stat-card-label">Completed</span>
                        <span class="stat-card-value" style="color: #166534;"><?= $completedCount ?></span>
                    </div>
                    <div class="stat-card-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-info">
                        <span class="stat-card-label">Incomplete</span>
                        <span class="stat-card-value" style="color: #92400e;"><?= $incompleteCount ?></span>
                    </div>
                    <div class="stat-card-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-info">
                        <span class="stat-card-label">Completion Rate</span>
                        <span class="stat-card-value"><?= $completionPercent ?>%</span>
                    </div>
                    <div class="stat-card-icon">
                        <i class="fas fa-chart-simple"></i>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="data-table-wrapper">
                <?php if (empty($trees)): ?>
                <div style="text-align: center; padding: 60px; color: #94a3b8;">
                    <i class="fas fa-leaf" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
                    <p>No trees found. Upload data to get started.</p>
                </div>
                <?php else: ?>
                
                <div class="desktop-table">
                    <table class="data-table">
                        <thead>
                            <tr><th>ID</th><th>Inspection Tree ID</th><th>Location</th><th>Height (m)</th><th>Crown Dia (m)</th><th>DBH (cm)</th><th>Biomass (kg)</th><th>Carbon (kg)</th><th>Status</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($trees as $t): 
                            $hasInspection = !empty($t['insp_id']);
                            $isComplete = ($hasInspection && isInspectionComplete($t));
                        ?>
                        <tr>
                            <td><strong>#<?= $t['id'] ?></strong></td>
                            <td><?= !empty($t['tree_id']) ? htmlspecialchars($t['tree_id']) : '—' ?></td>
                            <td><?= !empty($t['tree_location']) ? htmlspecialchars($t['tree_location']) : '—' ?></td>
                            <td><?= safeRound($t['tree_height']) ?></td>
                            <td><?= safeRound($t['crown_diameter']) ?></td>
                            <td><?= safeRound($t['dbh']) ?></td>
                            <td><?= safeRound($t['total_tree_biomass']) ?></td>
                            <td><?= safeRound($t['carbon_stock']) ?></td>
                            <td><?= $isComplete ? '<span class="status-badge status-completed"><i class="fas fa-check-circle"></i> Completed</span>' : '<span class="status-badge status-incomplete"><i class="fas fa-clock"></i> Incomplete</span>' ?></td>
                            <td>
                                <div class="action-buttons-cell">
                                    <a href="inspect.php?tree_no=<?= $t['id'] ?>&upload_id=<?= $active_upload_id ?>" class="btn btn-primary btn-sm"><i class="fas fa-edit"></i> <?= $hasInspection ? 'Edit' : 'Inspect' ?></a>
                                    <?php if ($hasInspection): ?>
                                    <a href="print.php?id=<?= $t['insp_id'] ?>" class="btn btn-success btn-sm" target="_blank"><i class="fas fa-print"></i> Print</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mobile-cards">
                    <?php foreach ($trees as $t): 
                        $hasInspection = !empty($t['insp_id']);
                        $isComplete = ($hasInspection && isInspectionComplete($t));
                    ?>
                    <div class="tree-card">
                        <div class="tree-card-header">
                            <strong>Tree #<?= $t['id'] ?></strong>
                            <?= $isComplete ? '<span class="status-badge status-completed">Completed</span>' : '<span class="status-badge status-incomplete">Incomplete</span>' ?>
                        </div>
                        <div class="tree-details">
                            <div><small>Inspection Tree ID:</small><br><?= !empty($t['tree_id']) ? htmlspecialchars($t['tree_id']) : '—' ?></div>
                            <div><small>Location:</small><br><?= !empty($t['tree_location']) ? htmlspecialchars($t['tree_location']) : '—' ?></div>
                            <div><small>Height:</small><br><?= safeRound($t['tree_height']) ?> m</div>
                            <div><small>Crown Dia:</small><br><?= safeRound($t['crown_diameter']) ?> m</div>
                            <div><small>DBH:</small><br><?= safeRound($t['dbh']) ?> cm</div>
                            <div><small>Biomass:</small><br><?= safeRound($t['total_tree_biomass']) ?> kg</div>
                            <div><small>Carbon:</small><br><?= safeRound($t['carbon_stock']) ?> kg</div>
                        </div>
                        <div class="tree-actions">
                            <a href="inspect.php?tree_no=<?= $t['id'] ?>&upload_id=<?= $active_upload_id ?>" class="btn btn-primary btn-sm"><i class="fas fa-edit"></i> <?= $hasInspection ? 'Edit' : 'Inspect' ?></a>
                            <?php if ($hasInspection): ?>
                            <a href="print.php?id=<?= $t['insp_id'] ?>" class="btn btn-success btn-sm" target="_blank"><i class="fas fa-print"></i> Print</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination">
                        <?php if ($current_page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">«</a>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $current_page - 1])) ?>">‹</a>
                        <?php else: ?>
                            <span class="disabled">«</span>
                            <span class="disabled">‹</span>
                        <?php endif; ?>
                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <?php if ($i == $current_page): ?>
                                <span class="active"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $current_page + 1])) ?>">›</a>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>">»</a>
                        <?php else: ?>
                            <span class="disabled">›</span>
                            <span class="disabled">»</span>
                        <?php endif; ?>
                    </div>
                    <div class="pagination-info">
                        Page <?= $current_page ?> of <?= $total_pages ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<div id="deleteModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
    <div style="background: white; padding: 24px; border-radius: 12px; max-width: 400px; width: 90%; text-align: center;">
        <h3 style="margin-bottom: 16px;">Confirm Delete</h3>
        <p>Delete dataset "<span id="deleteUploadName"></span>"?</p>
        <div style="display: flex; gap: 12px; justify-content: center; margin-top: 20px;">
            <button class="btn btn-danger" onclick="confirmDelete()">Delete</button>
            <button class="btn btn-outline" onclick="closeModal()">Cancel</button>
        </div>
    </div>
</div>

<script>
let deleteId = null;

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('open');
    overlay.classList.toggle('active');
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
}

function switchDataset() {
    let select = document.getElementById('datasetSelect');
    window.location.href = '?upload_id=' + select.value;
}

function setAsActive() {
    let select = document.getElementById('datasetSelect');
    let uploadId = select.value;
    fetch('set_active.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'upload_id=' + uploadId
    })
    .then(response => response.json())
    .then(data => { if (data.success) location.reload(); else alert('Error'); })
    .catch(error => alert('Failed'));
}

function showDeleteModalForSelected() {
    let select = document.getElementById('datasetSelect');
    deleteId = select.value;
    let option = select.options[select.selectedIndex];
    document.getElementById('deleteUploadName').innerText = option.text.split(' (')[0];
    document.getElementById('deleteModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('deleteModal').style.display = 'none';
    deleteId = null;
}

function confirmDelete() {
    if (deleteId) {
        window.location.href = 'index.php?delete_upload=' + deleteId;
    }
}

window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        closeSidebar();
    }
});

window.onclick = function(event) {
    const modal = document.getElementById('deleteModal');
    if (event.target == modal) {
        closeModal();
    }
}
</script>
</body>
</html>