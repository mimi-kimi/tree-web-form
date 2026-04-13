<?php
require_once 'config/db.php';
$pdo = db();

// Handle delete request
if (isset($_GET['delete_upload']) && is_numeric($_GET['delete_upload'])) {
    $delete_id = $_GET['delete_upload'];
    
    try {
        // With ON DELETE CASCADE, just delete the upload record
        // It will automatically delete all associated trees and inspections
        $deleteUpload = $pdo->prepare('DELETE FROM uploads WHERE upload_id = ?');
        $deleteUpload->execute([$delete_id]);
        
        $success = "Dataset deleted successfully!";
        
        // Redirect to refresh the page without the delete parameter
        header('Location: index.php?deleted=1');
        exit;
    } catch (PDOException $e) {
        $error = "Error deleting dataset: " . $e->getMessage();
    }
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
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Tree Inspection System</title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
    .upload-selector {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 12px 16px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
    }
    .upload-selector label {
        font-weight: 600;
        color: #1e293b;
    }
    .upload-selector select {
        padding: 6px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        background: white;
        min-width: 250px;
    }
    .upload-selector button {
        background: #3b82f6;
        color: white;
        border: none;
        padding: 6px 16px;
        border-radius: 6px;
        cursor: pointer;
    }
    .upload-badge {
        background: #10b981;
        color: white;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        margin-left: 8px;
    }
    .delete-btn {
        background: #ef4444;
        color: white;
        border: none;
        padding: 4px 12px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
        margin-left: 10px;
    }
    .delete-btn:hover {
        background: #dc2626;
    }
    .upload-item {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .alert-warning {
        background: #fef3c7;
        border: 1px solid #f59e0b;
        color: #92400e;
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 20px;
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
        max-width: 400px;
        text-align: center;
    }
    .modal-buttons {
        display: flex;
        gap: 12px;
        justify-content: center;
        margin-top: 20px;
    }
    .modal-buttons button {
        padding: 8px 20px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
    }
    .confirm-delete {
        background: #ef4444;
        color: white;
    }
    .cancel-delete {
        background: #9ca3af;
        color: white;
    }
  </style>
</head>
<body>
<div class="container">

<div id="deleteModal" class="modal">
    <div class="modal-content">
        <h3>Confirm Delete</h3>
        <p>Are you sure you want to delete dataset "<span id="deleteUploadName"></span>"?</p>
        <p style="font-size:12px; color:#666;">This will remove all trees in this dataset. Inspections linked to this dataset will also be deleted.</p>
        <div class="modal-buttons">
            <button class="confirm-delete" onclick="confirmDelete()">Yes, Delete</button>
            <button class="cancel-delete" onclick="closeModal()">Cancel</button>
        </div>
    </div>
</div>

  <div class="topbar">
    <h1>Tree Inspection System</h1>
    <span class="badge badge-blue">hsinnova_tree_inspections</span>
  </div>

  <?php if ($msg === 'uploaded'): ?>
  <div class="alert alert-success">
    Excel uploaded successfully! <strong><?= (int)($_GET['count'] ?? 0) ?> trees</strong> imported.
  </div>
  <?php elseif ($msg === 'error'): ?>
  <div class="alert alert-error">Upload failed. Make sure the file is .xlsx or .csv with correct columns.</div>
  <?php elseif ($deleted == 1): ?>
  <div class="alert alert-success">Dataset deleted successfully!</div>
  <?php elseif (isset($error)): ?>
  <div class="alert alert-error"><?= $error ?></div>
  <?php endif; ?>

  <!-- Upload Card -->
  <div class="card">
    <h2>Upload Tree Data (Excel)</h2>
    <form action="upload.php" method="POST" enctype="multipart/form-data">
      <div class="grid2" style="align-items:flex-end">
        <div class="field">
          <label>Select Excel File (.xlsx or .csv)</label>
          <input type="file" name="excel" accept=".xlsx,.csv" required>
        </div>
        <div class="field">
          <label>Upload Name (optional)</label>
          <input type="text" name="upload_name" placeholder="e.g., Site A - March 2024">
        </div>
        <div class="field">
          <label>Description</label>
          <input type="text" name="description" placeholder="Additional notes about this dataset">
        </div>
        <div class="field">
          <label>
            <input type="checkbox" name="set_active" value="1"> Set as active dataset
          </label>
        </div>
        <div>
          <button type="submit" class="btn btn-green">Upload &amp; Import</button>
          <p style="font-size:12px;color:#888;margin-top:6px">Each upload creates a new version. Switch versions using the dropdown below.</p>
        </div>
      </div>
    </form>
  </div>

  <!-- Upload/Dataset Selector -->
  <?php if (!empty($uploads)): ?>
  <div class="upload-selector">
    <label>📊 Active Dataset:</label>
    <form method="GET" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
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
    
    <!-- Delete button for current dataset -->
    <?php if ($active_upload_id): ?>
    <button class="delete-btn" onclick="showDeleteModal(<?= $active_upload_id ?>, '<?= addslashes($selectedUploadName) ?>')">
        🗑️ Delete Current Dataset
    </button>
    <?php endif; ?>
    
    <?php if ($selectedUploadName): ?>
    <span style="color:#64748b; font-size:13px;">Currently viewing: <strong><?= htmlspecialchars($selectedUploadName) ?></strong></span>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Tree List -->
  <div class="card">
    <h2>Trees (<?= count($trees) ?> total)</h2>
    <?php if (empty($trees)): ?>
      <p style="color:#aaa;text-align:center;padding:40px">
        No trees in this dataset. Upload an Excel file to get started, or select a different dataset above.
      </p>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Tree ID</th>
          <th>Height (m)</th>
          <th>Crown Dia (m)</th>
          <th>DBH (cm)</th>
          <th>Total Biomass (kg)</th>
          <th>Carbon Stock (kg)</th>
          <th>Inspection</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($trees as $t): ?>
        <tr>
          <td><strong>#<?= $t['id'] ?></strong></td>
          <td><?= $t['tree_height'] ?></td>
          <td><?= $t['crown_diameter'] ?></td>
          <td><?= $t['dbh'] ?></td>
          <td><?= $t['total_tree_biomass'] ?></td>
          <td><?= $t['carbon_stock'] ?></td>
          <td>
            <?php if (!empty($t['prepared_by'])): ?>
              <span class="badge badge-green">Completed</span>
            <?php else: ?>
              <span class="badge" style="background:#fef9c3;color:#854d0e">Incomplete</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="btn-row">
              <a href="inspect.php?tree_id=<?= $t['id'] ?>&upload_id=<?= $active_upload_id ?>" class="btn btn-blue" style="padding:5px 12px;font-size:12px">
                <?= $t['insp_id'] ? 'Edit Form' : 'Fill Form' ?>
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

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('deleteModal');
    if (event.target === modal) {
        closeModal();
    }
}
</script>

</body>
</html>