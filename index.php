<?php
require_once 'config/db.php';
$pdo = db();

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

// Get total count for pagination
$total_trees = 0;
$trees = [];
$selectedUploadName = '';
if ($active_upload_id) {
    // Get total count
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM trees WHERE upload_id = ?');
    $countStmt->execute([$active_upload_id]);
    $total_trees = $countStmt->fetchColumn();
    
    // Get trees with pagination
    $stmt = $pdo->prepare('
        SELECT 
            t.*, 
            i.insp_id, 
            i.prepared_by
        FROM trees t 
        LEFT JOIN inspections i ON i.tree_id = t.id AND i.upload_id = t.upload_id
        WHERE t.upload_id = ?
        ORDER BY t.id ASC
        LIMIT ? OFFSET ?
    ');
    $stmt->bindValue(1, $active_upload_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $items_per_page, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
  <title>Tree Inspection System</title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
    * {
        box-sizing: border-box;
    }
    /* Form separator - creates visual gap */
.form-separator {
    height: 1px;
    background: linear-gradient(to right, transparent, #e2e8f0, transparent);
    margin: 20px 0 16px 0;
}

/* Upload footer with checkbox on the right */
.upload-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin-top: 8px;
    flex-wrap: wrap;
}

.upload-footer .btn {
    width: auto;
    margin: 0;
}

/* Checkbox label styling - text on left, checkbox on right */
.checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    font-weight: normal;
    font-size: 14px;
    color: #334155;
    padding: 10px 0;
}

.checkbox-label span {
    cursor: pointer;
    order: 1; /* Text first */
}

.checkbox-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
    margin: 0;
    cursor: pointer;
    order: 2; /* Checkbox after text */
}

/* Mobile: stack vertically with checkbox on right */
@media (max-width: 640px) {
    .upload-footer {
        flex-direction: column-reverse;
        align-items: stretch;
        gap: 12px;
    }
    
    .upload-footer .btn {
        width: 100%;
    }
    
    .checkbox-label {
        justify-content: space-between;
        padding: 12px;
        background: #f8fafc;
        border-radius: 8px;
    }
    }
    .upload-sele    ctor {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 20px;
}

.upload-selector strong {
    display: block;
    margin-bottom: 12px;
    font-size: 14px;
}

.selector-form {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.upload-selector select {
    width: 100%;
    padding: 10px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: white;
    font-size: 14px;
}

.selector-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.selector-buttons button {
    flex: 1;
    padding: 10px 16px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s;
    text-align: center;
}

.selector-buttons button[type="submit"] {
    background: #3b82f6;
    color: white;
}

.set-active-btn {
    background: #f59e0b;
    color: white;
}

.delete-btn {
    background: #ef4444;
    color: white;
}

/* Mobile: buttons stack vertically */
@media (max-width: 640px) {
    .selector-buttons {
        flex-direction: column;
    }
    
    .selector-buttons button {
        width: 100%;
    }
}

/* Desktop: buttons side by side */
@media (min-width: 641px) {
    .selector-buttons {
        flex-direction: row;
    }
    
    .selector-buttons button {
        flex: 1;
    }
}
    body {
        margin: 0;
        padding: 0;
        background: #f1f5f9;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    }
    
    .container {
        max-width: 100%;
        padding: 12px;
        margin: 0 auto;
    }
    
    .topbar {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #e2e8f0;
    }
    
    .topbar h1 {
        font-size: 1.5rem;
        margin: 0;
        color: #1e293b;
    }
    
    .badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        align-self: flex-start;
    }
    
    .badge-blue {
        background: #dbeafe;
        color: #1e40af;
    }
    
    .card {
        background: white;
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .card h2 {
        font-size: 1.2rem;
        margin-top: 0;
        margin-bottom: 16px;
        color: #0f172a;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .alert {
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
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
    
    .field {
        margin-bottom: 12px;
    }
    
    .field label {
        display: block;
        margin-bottom: 6px;
        font-weight: 600;
        font-size: 13px;
        color: #334155;
    }
    
    .field input[type="text"],
    .field input[type="file"],
    .field input[type="number"],
    .field textarea,
    .field select {
        width: 100%;
        padding: 10px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 14px;
        font-family: inherit;
    }
    
    .btn {
        display: inline-block;
        padding: 10px 16px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        text-align: center;
        transition: all 0.2s;
    }
    
    .btn:active {
        transform: scale(0.98);
    }
    
    .btn-green {
        background: #10b981;
        color: white;
        width: 100%;
    }
    
    .btn-blue {
        background: #3b82f6;
        color: white;
    }
    
    .btn-gray {
        background: #64748b;
        color: white;
    }
    
    .btn-red {
        background: #ef4444;
        color: white;
    }
    
    .upload-selector {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 20px;
    }
    
    .upload-selector form {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    
    .upload-selector select {
        width: 100%;
        padding: 10px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        background: white;
        font-size: 14px;
    }
    
    .upload-selector button {
        background: #3b82f6;
        color: white;
        border: none;
        padding: 10px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
    }
    
    .delete-btn {
        background: #ef4444;
        color: white;
        border: none;
        padding: 10px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        margin-top: 8px;
    }
    
    /* Pagination Styles */
    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #e2e8f0;
        flex-wrap: wrap;
    }
    
    .pagination a, .pagination span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 40px;
        height: 40px;
        padding: 0 8px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.2s;
    }
    
    .pagination a {
        background: #f1f5f9;
        color: #3b82f6;
        border: 1px solid #e2e8f0;
    }
    
    .pagination a:hover {
        background: #3b82f6;
        color: white;
        border-color: #3b82f6;
    }
    
    .pagination .active {
        background: #3b82f6;
        color: white;
        border: 1px solid #3b82f6;
    }
    
    .pagination .disabled {
        opacity: 0.5;
        cursor: not-allowed;
        background: #f1f5f9;
        color: #94a3b8;
        border: 1px solid #e2e8f0;
    }
    
    .pagination-info {
        text-align: center;
        font-size: 13px;
        color: #64748b;
        margin-top: 12px;
    }
    
    /* Mobile Table Styles */
    @media (max-width: 768px) {
        .desktop-table {
            display: none;
        }
        
        .mobile-cards {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .tree-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        
        .tree-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .tree-id {
            font-weight: bold;
            font-size: 16px;
            color: #1e293b;
        }
        
        .tree-status {
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 20px;
        }
        
        .status-complete {
            background: #dcfce7;
            color: #166534;
        }
        
        .status-incomplete {
            background: #fef3c7;
            color: #92400e;
        }
        
        .tree-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 11px;
            color: #64748b;
            margin-bottom: 4px;
        }
        
        .detail-value {
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
        }
        
        .tree-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
        }
        
        .tree-actions .btn {
            flex: 1;
            text-align: center;
            font-size: 12px;
            padding: 8px;
        }
    }
    
    @media (min-width: 769px) {
        .mobile-cards {
            display: none;
        }
        
        .desktop-table {
            display: block;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        th {
            background: #f8fafc;
            font-weight: 600;
            color: #475569;
            position: sticky;
            top: 0;
        }
        
        .btn-row {
            display: flex;
            gap: 8px;
        }
        
        .btn-row .btn {
            font-size: 11px;
            padding: 5px 10px;
        }
    }
    
    .badge-green {
        background: #dcfce7;
        color: #166534;
        padding: 4px 8px;
        border-radius: 20px;
        font-size: 11px;
    }
    
    .grid2, .grid3, .grid4 {
        display: grid;
        gap: 12px;
    }
    
    .grid2 { grid-template-columns: 1fr; }
    
    @media (min-width: 640px) {
        .container { padding: 20px; max-width: 1200px; }
        .grid2 { grid-template-columns: 1fr 1fr; }
        .btn-green { width: auto; margin-top: 20px; }
        .upload-selector form { flex-direction: row; align-items: center; flex-wrap: wrap; }
        .upload-selector select { width: auto; min-width: 250px; }
        .upload-selector button { width: auto; }
        .delete-btn { width: auto; margin-top: 0; }
    }
    
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }
    
    .modal-content {
        background: white;
        padding: 24px;
        border-radius: 12px;
        max-width: 90%;
        width: 320px;
        text-align: center;
    }
    
    .modal-buttons {
        display: flex;
        gap: 12px;
        justify-content: center;
        margin-top: 20px;
    }
    
    .modal-buttons button {
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
    }
    
    .confirm-delete { background: #ef4444; color: white; }
    .cancel-delete { background: #9ca3af; color: white; }
    
    .text-center { text-align: center; }
    .text-muted { color: #94a3b8; }
    .mt-4 { margin-top: 40px; }
    
    /* Jump to page input */
    .page-jump {
        display: flex;
        gap: 8px;
        align-items: center;
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .page-jump input {
        width: 70px;
        padding: 8px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        text-align: center;
    }
    
    .page-jump button {
        padding: 8px 12px;
        background: #3b82f6;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
    }
  </style>
</head>
<body>
<div class="container">

<div id="deleteModal" class="modal">
    <div class="modal-content">
        <h3>Confirm Delete</h3>
        <p>Are you sure you want to delete dataset "<span id="deleteUploadName"></span>"?</p>
        <p style="font-size:12px; color:#666;">This will remove all trees and inspections in this dataset.</p>
        <div class="modal-buttons">
            <button class="confirm-delete" onclick="confirmDelete()">Yes, Delete</button>
            <button class="cancel-delete" onclick="closeModal()">Cancel</button>
        </div>
    </div>
</div>

  <div class="topbar">
    <h1>Tree Inspection System</h1>
  </div>

  <?php if ($msg === 'uploaded'): ?>
  <div class="alert alert-success">
    ✅ Excel uploaded successfully! <strong><?= (int)($_GET['count'] ?? 0) ?> trees</strong> imported.
  </div>
  <?php elseif ($msg === 'error'): ?>
  <div class="alert alert-error">❌ Upload failed. Make sure the file is .xlsx or .csv with correct columns.</div>
  <?php elseif ($deleted == 1): ?>
  <div class="alert alert-success">🗑️ Dataset deleted successfully!</div>
  <?php elseif (isset($error)): ?>
  <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
  <?php elseif ($msg === 'missing_name'): ?>
    <div class="alert alert-error">❌ Upload failed. Please provide a name for the dataset.</div>
  <?php endif; ?>

  <!-- Upload Card -->
<div class="card">
  <h2>📤 Upload Tree Data</h2>
  <form action="upload.php" method="POST" enctype="multipart/form-data">
    <div class="field">
      <label>Excel File (.xlsx or .csv)</label>
      <input type="file" name="excel" accept=".xlsx,.csv" required>
    </div>
        <div class="field">
        <label>Upload Name</label>
        <input type="text" name="upload_name" placeholder="e.g., Site A - March 2024" required>
    </div>
    <div class="field">
      <label>Description (optional)</label>
      <input type="text" name="description" placeholder="Additional notes about this dataset">
    </div>
    
    <!-- Separator line with gap -->
    <div class="form-separator"></div>
    
    <!-- Checkbox and button row - checkbox on the right -->
    <div class="upload-footer">
        <label class="checkbox-label">
        <span>Set as active dataset</span>
        <input type="checkbox" name="set_active" value="1" checked>
      </label>
      <button type="submit" class="btn btn-green"> Upload &amp; Import</button>
    </div>
  </form>
</div>
<!-- Upload/Dataset Selector - Auto-switch version -->
<?php if (!empty($uploads)): ?>
<div class="upload-selector">
    <strong>📊 Dataset Manager:</strong>
    <form method="GET" class="selector-form" id="switchForm">
      <select name="upload_id" id="datasetSelect" onchange="this.form.submit()">
        <?php foreach ($uploads as $u): ?>
        <option value="<?= $u['upload_id'] ?>" <?= ($active_upload_id == $u['upload_id']) ? 'selected' : '' ?>>
          <?= htmlspecialchars($u['upload_name']) ?> 
          (<?= $u['row_count'] ?> trees - <?= date('Y-m-d', strtotime($u['upload_date'])) ?>)
          <?= $u['is_active'] ? '✓ ACTIVE' : '' ?>
        </option>
        <?php endforeach; ?>
      </select>
      <div class="selector-buttons">
        <button type="button" class="set-active-btn" onclick="setAsActive()">Set as Active</button>
        <button type="button" class="delete-btn" onclick="showDeleteModalForSelected()"> Delete Current Dataset</button>
      </div>
    </form>
</div>
<?php endif; ?>

  <!-- Tree List -->
  <div class="card tree-list">
    <h2>
      🌲 Trees (<?= $total_trees ?> total)
      <?php if ($total_trees > 0): ?>
      <span style="font-size: 12px; font-weight: normal;">Showing <?= $offset + 1 ?> - <?= min($offset + $items_per_page, $total_trees) ?> of <?= $total_trees ?></span>
      <?php endif; ?>
    </h2>
    
    <?php if (empty($trees)): ?>
      <p class="text-center text-muted mt-4">
        No trees in this dataset. Upload an Excel file to get started.
      </p>
    <?php else: ?>
    
    <!-- Desktop Table View -->
    <div class="desktop-table">
      <table>
        <thead>
          <tr>
            <th>Tree ID</th>
            <th>Height (m)</th>
            <th>Crown Dia (m)</th>
            <th>DBH (cm)</th>
            <th>Total Biomass (kg)</th>
            <th>Carbon Stock (kg)</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($trees as $t): ?>
          <tr>
            <td><strong>#<?= htmlspecialchars($t['id']) ?></strong></td>
            <td><?= round(htmlspecialchars($t['tree_height']), 2) ?></td>
            <td><?= round(htmlspecialchars($t['crown_diameter']), 2) ?></td>
            <td><?= round(htmlspecialchars($t['dbh']), 2) ?></td>
            <td><?= round(htmlspecialchars($t['total_tree_biomass']), 2) ?></td>
            <td><?= round(htmlspecialchars($t['carbon_stock']), 2) ?></td>
            <td>
              <?php if (!empty($t['prepared_by'])): ?>
                <span class="badge-green">Completed</span>
              <?php else: ?>
                <span style="background:#fef3c7;color:#92400e;padding:4px 8px;border-radius:20px;font-size:11px;">Incomplete</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="btn-row">
                <a href="inspect.php?tree_id=<?= $t['id'] ?>&upload_id=<?= $active_upload_id ?>" class="btn btn-blue" style="padding:5px 12px;font-size:12px">
                  <?= $t['insp_id'] ? 'Edit' : 'Fill' ?>
                </a>
                <?php if ($t['insp_id']): ?>
                <a href="print.php?id=<?= $t['insp_id'] ?>" class="btn btn-gray" style="padding:5px 12px;font-size:12px" target="_blank">Print</a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    
    <!-- Mobile Card View -->
    <div class="mobile-cards">
      <?php foreach ($trees as $t): ?>
      <div class="tree-card">
        <div class="tree-card-header">
          <span class="tree-id">🌲 Tree #<?= htmlspecialchars($t['id']) ?></span>
          <?php if (!empty($t['prepared_by'])): ?>
            <span class="tree-status status-complete">✓ Completed</span>
          <?php else: ?>
            <span class="tree-status status-incomplete">⏳ Incomplete</span>
          <?php endif; ?>
        </div>
        
        <div class="tree-details">
          <div class="detail-item">
            <span class="detail-label">Height</span>
            <span class="detail-value"><?= round(htmlspecialchars($t['tree_height']), 2) ?> m</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Crown Dia</span>
            <span class="detail-value"><?= round(htmlspecialchars($t['crown_diameter']), 2) ?> m</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">DBH</span>
            <span class="detail-value"><?= round(htmlspecialchars($t['dbh']), 2) ?> cm</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Biomass</span>
            <span class="detail-value"><?= round(htmlspecialchars($t['total_tree_biomass']), 2) ?> kg</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Carbon Stock</span>
            <span class="detail-value"><?= round(htmlspecialchars($t['carbon_stock']), 2) ?> kg</span>
          </div>
        </div>
        
        <div class="tree-actions">
          <a href="inspect.php?tree_id=<?= $t['id'] ?>&upload_id=<?= $active_upload_id ?>" class="btn btn-blue">
            <?= $t['insp_id'] ? 'Edit Form' : 'Fill Form' ?>
          </a>
          <?php if ($t['insp_id']): ?>
          <a href="print.php?id=<?= $t['insp_id'] ?>" class="btn btn-gray" target="_blank">
             Print
          </a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php if ($current_page > 1): ?>
        <a href="?upload_id=<?= $active_upload_id ?>&page=1">« First</a>
        <a href="?upload_id=<?= $active_upload_id ?>&page=<?= $current_page - 1 ?>">‹ Previous</a>
      <?php else: ?>
        <span class="disabled">« First</span>
        <span class="disabled">‹ Previous</span>
      <?php endif; ?>
      
      <?php
      $start_page = max(1, $current_page - 2);
      $end_page = min($total_pages, $current_page + 2);
      
      for ($i = $start_page; $i <= $end_page; $i++):
      ?>
        <?php if ($i == $current_page): ?>
          <span class="active"><?= $i ?></span>
        <?php else: ?>
          <a href="?upload_id=<?= $active_upload_id ?>&page=<?= $i ?>"><?= $i ?></a>
        <?php endif; ?>
      <?php endfor; ?>
      
      <?php if ($current_page < $total_pages): ?>
        <a href="?upload_id=<?= $active_upload_id ?>&page=<?= $current_page + 1 ?>">Next ›</a>
        <a href="?upload_id=<?= $active_upload_id ?>&page=<?= $total_pages ?>">Last »</a>
      <?php else: ?>
        <span class="disabled">Next ›</span>
        <span class="disabled">Last »</span>
      <?php endif; ?>
    </div>
    
    <div class="pagination-info">
      <div class="page-jump">
        <span>Go to page:</span>
        <input type="number" id="pageInput" min="1" max="<?= $total_pages ?>" value="<?= $current_page ?>">
        <button onclick="goToPage()">Go</button>
      </div>
    </div>
    <?php endif; ?>
    
    <?php endif; ?>
  </div>
</div>

<script>
let deleteId = null;
let deleteName = '';

function showDeleteModal(uploadId, uploadName) {
    deleteId = uploadId;
    document.getElementById('deleteUploadName').innerText = uploadName;
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

function goToPage() {
    let page = document.getElementById('pageInput').value;
    let maxPage = <?= $total_pages ?>;
    if (page >= 1 && page <= maxPage) {
        window.location.href = '?upload_id=<?= $active_upload_id ?>&page=' + page;
    } else {
        alert('Please enter a valid page number between 1 and ' + maxPage);
    }
}

window.onclick = function(event) {
    const modal = document.getElementById('deleteModal');
    if (event.target === modal) {
        closeModal();
    }
}
function setAsActive() {
    let select = document.getElementById('datasetSelect');
    let uploadId = select.value;
    
    fetch('set_active.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'upload_id=' + uploadId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to set as active');
    });
}

function showDeleteModalForSelected() {
    let select = document.getElementById('datasetSelect');
    deleteId = select.value;
    let selectedOption = select.options[select.selectedIndex];
    deleteName = selectedOption.text.split(' (')[0];
    
    document.getElementById('deleteUploadName').innerText = deleteName;
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

function goToPage() {
    let page = document.getElementById('pageInput').value;
    let maxPage = <?= $total_pages ?? 1 ?>;
    let uploadId = document.getElementById('datasetSelect')?.value || <?= $active_upload_id ?? 0 ?>;
    if (page >= 1 && page <= maxPage) {
        window.location.href = '?upload_id=' + uploadId + '&page=' + page;
    } else {
        alert('Please enter a valid page number between 1 and ' + maxPage);
    }
}

window.onclick = function(event) {
    const modal = document.getElementById('deleteModal');
    if (event.target === modal) {
        closeModal();
    }
}

</script>

</body>
</html>