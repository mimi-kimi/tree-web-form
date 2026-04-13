<?php
require_once 'config/db.php';
$pdo = db();

// Handle delete request
if (isset($_GET['delete_upload']) && is_numeric($_GET['delete_upload'])) {
    $delete_id = $_GET['delete_upload'];
    
    // Delete inspections first
    $deleteInspections = $pdo->prepare('DELETE FROM inspections WHERE upload_id = ?');
    $deleteInspections->execute([$delete_id]);
    
    // Delete trees
    $deleteTrees = $pdo->prepare('DELETE FROM trees WHERE upload_id = ?');
    $deleteTrees->execute([$delete_id]);
    
    // Delete the upload record
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

// Get trees from selected upload
$trees = [];
$selectedUploadName = '';
if ($active_upload_id) {
    $stmt = $pdo->prepare('
        SELECT 
            t.*, 
            i.insp_id, 
            i.prepared_by
        FROM trees t 
        LEFT JOIN inspections i ON i.tree_id = t.id AND i.upload_id = t.upload_id
        WHERE t.upload_id = ?
        ORDER BY t.id ASC
    ');
    $stmt->execute([$active_upload_id]);
    $trees = $stmt->fetchAll();
    
    $uploadStmt = $pdo->prepare('SELECT upload_name FROM uploads WHERE upload_id = ?');
    $uploadStmt->execute([$active_upload_id]);
    $selectedUploadName = $uploadStmt->fetchColumn();
}

$msg = $_GET['msg'] ?? '';
$deleted = $_GET['deleted'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
  <title>Tree Inspection System</title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
    * {
        box-sizing: border-box;
    }
    
    body {
        margin: 0;
        padding: 0;
        background: #f1f5f9;
    }
    
    .container {
        max-width: 100%;
        padding: 12px;
        margin: 0 auto;
    }
    
    /* Mobile-first responsive design */
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
    
    /* Upload form responsive */
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
    .field input[type="file"] {
        width: 100%;
        padding: 10px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 14px;
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
    
    /* Upload selector responsive */
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
    
    /* Mobile Table Styles - Card Layout */
    .tree-list {
        overflow-x: auto;
    }
    
    /* For mobile: transform table to cards */
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
    
    /* Desktop table styles */
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
        .btn-green { width: auto; }
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
    <h1>🌳 Tree Inspection System</h1>
    <span class="badge badge-blue">hsinnova_tree_inspections</span>
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
  <div class="alert alert-error"><?= $error ?></div>
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
        <label>Upload Name (optional)</label>
        <input type="text" name="upload_name" placeholder="e.g., Site A - March 2024">
      </div>
      <div class="field">
        <label>Description</label>
        <input type="text" name="description" placeholder="Additional notes">
      </div>
      <div class="field">
        <label>
          <input type="checkbox" name="set_active" value="1"> Set as active dataset
        </label>
      </div>
      <button type="submit" class="btn btn-green">📂 Upload &amp; Import</button>
    </form>
  </div>

  <!-- Upload/Dataset Selector -->
  <?php if (!empty($uploads)): ?>
  <div class="upload-selector">
    <strong>📊 Active Dataset:</strong>
    <form method="GET">
      <select name="upload_id" onchange="this.form.submit()">
        <?php foreach ($uploads as $u): ?>
        <option value="<?= $u['upload_id'] ?>" <?= ($active_upload_id == $u['upload_id']) ? 'selected' : '' ?>>
          <?= htmlspecialchars($u['upload_name']) ?> 
          (<?= $u['row_count'] ?> trees - <?= date('Y-m-d', strtotime($u['upload_date'])) ?>)
          <?= $u['is_active'] ? '✓ ACTIVE' : '' ?>
        </option>
        <?php endforeach; ?>
      </select>
      <button type="submit">Switch</button>
    </form>
    
    <?php if ($active_upload_id): ?>
    <button class="delete-btn" onclick="showDeleteModal(<?= $active_upload_id ?>, '<?= addslashes($selectedUploadName) ?>')">
        🗑️ Delete Current Dataset
    </button>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Tree List -->
  <div class="card tree-list">
    <h2>🌲 Trees (<?= count($trees) ?> total)</h2>
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
            <td><strong>#<?= $t['id'] ?></strong></td>
            <td><?= round($t['tree_height'], 2) ?></td>
            <td><?= round($t['crown_diameter'], 2) ?></td>
            <td><?= round($t['dbh'], 2) ?></td>
            <td><?= round($t['total_tree_biomass'], 2) ?></td>
            <td><?= round($t['carbon_stock'], 2) ?></td>
            <td>
              <?php if (!empty($t['prepared_by'])): ?>
                <span class="badge-green">✓ Completed</span>
              <?php else: ?>
                <span style="background:#fef3c7;color:#92400e;padding:4px 8px;border-radius:20px;font-size:11px;">⏳ Incomplete</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="btn-row">
                <a href="inspect.php?tree_id=<?= $t['id'] ?>&upload_id=<?= $active_upload_id ?>" class="btn btn-blue" style="padding:5px 12px;font-size:12px">
                  <?= $t['insp_id'] ? '✏️ Edit' : '📝 Fill' ?>
                </a>
                <?php if ($t['insp_id']): ?>
                <a href="print.php?id=<?= $t['insp_id'] ?>" class="btn btn-gray" style="padding:5px 12px;font-size:12px" target="_blank">🖨️ Print</a>
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
          <span class="tree-id">🌲 Tree #<?= $t['id'] ?></span>
          <?php if (!empty($t['prepared_by'])): ?>
            <span class="tree-status status-complete">✓ Completed</span>
          <?php else: ?>
            <span class="tree-status status-incomplete">⏳ Incomplete</span>
          <?php endif; ?>
        </div>
        
        <div class="tree-details">
          <div class="detail-item">
            <span class="detail-label">Height</span>
            <span class="detail-value"><?= round($t['tree_height'], 2) ?> m</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Crown Dia</span>
            <span class="detail-value"><?= round($t['crown_diameter'], 2) ?> m</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">DBH</span>
            <span class="detail-value"><?= round($t['dbh'], 2) ?> cm</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Biomass</span>
            <span class="detail-value"><?= round($t['total_tree_biomass'], 2) ?> kg</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Carbon Stock</span>
            <span class="detail-value"><?= round($t['carbon_stock'], 2) ?> kg</span>
          </div>
        </div>
        
        <div class="tree-actions">
          <a href="inspect.php?tree_id=<?= $t['id'] ?>&upload_id=<?= $active_upload_id ?>" class="btn btn-blue">
            <?= $t['insp_id'] ? '✏️ Edit Form' : '📝 Fill Form' ?>
          </a>
          <?php if ($t['insp_id']): ?>
          <a href="print.php?id=<?= $t['insp_id'] ?>" class="btn btn-gray" target="_blank">
            🖨️ Print
          </a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    
    <?php endif; ?>
  </div>
</div>

<script>
let deleteId = null;

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

window.onclick = function(event) {
    const modal = document.getElementById('deleteModal');
    if (event.target === modal) {
        closeModal();
    }
}
</script>

</body>
</html>