<?php
require_once 'config/db.php';
require_once 'config/auth.php';
$pdo = db();

requireLogin();

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = trim($_POST['full_name'] ?? '');
        
        if (!empty($full_name)) {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ? WHERE user_id = ?");
            $stmt->execute([$full_name, $user_id]);
            $_SESSION['full_name'] = $full_name;
            $message = 'Profile updated successfully!';
        } else {
            $error = 'Full name cannot be empty';
        }
    }
    
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (password_verify($current_password, $user['password'])) {
            if ($new_password === $confirm_password && strlen($new_password) >= 6) {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $stmt->execute([$hashed, $user_id]);
                $message = 'Password changed successfully!';
            } else {
                $error = 'New password must match and be at least 6 characters';
            }
        } else {
            $error = 'Current password is incorrect';
        }
    }
    
    // Admin: Add new user
    if (isset($_POST['add_user']) && isAdmin()) {
        $new_username = trim($_POST['new_username'] ?? '');
        $new_password = $_POST['new_password'] ?? '';
        $new_full_name = trim($_POST['new_full_name'] ?? '');
        $new_role = $_POST['new_role'] ?? 'inspector';
        
        if (empty($new_username) || empty($new_password) || empty($new_full_name)) {
            $error = 'All fields are required for new user';
        } elseif (strlen($new_password) < 6) {
            $error = 'Password must be at least 6 characters';
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$new_username]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'Username already exists';
            } else {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$new_username, $hashed, $new_full_name, $new_role]);
                $message = "User '{$new_username}' created successfully!";
            }
        }
    }
    
    // Admin: Edit existing user
    if (isset($_POST['edit_user']) && isAdmin()) {
        $edit_user_id = intval($_POST['edit_user_id'] ?? 0);
        $edit_full_name = trim($_POST['edit_full_name'] ?? '');
        $edit_role = $_POST['edit_role'] ?? 'inspector';
        $edit_password = $_POST['edit_password'] ?? '';
        
        if ($edit_user_id && $edit_user_id != $user_id) {
            if (!empty($edit_password)) {
                if (strlen($edit_password) >= 6) {
                    $hashed = password_hash($edit_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, role = ?, password = ? WHERE user_id = ?");
                    $stmt->execute([$edit_full_name, $edit_role, $hashed, $edit_user_id]);
                } else {
                    $error = 'Password must be at least 6 characters';
                }
            } else {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, role = ? WHERE user_id = ?");
                $stmt->execute([$edit_full_name, $edit_role, $edit_user_id]);
            }
            $message = 'User updated successfully!';
        }
    }
    
    // Admin: Delete user
    if (isset($_POST['delete_user']) && isAdmin()) {
        $delete_user_id = intval($_POST['delete_user_id'] ?? 0);
        
        if ($delete_user_id && $delete_user_id != $user_id) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->execute([$delete_user_id]);
            $message = 'User deleted successfully!';
        } else {
            $error = 'You cannot delete your own account';
        }
    }
}

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get all users (for admin)
$all_users = [];
if (isAdmin()) {
    $stmt = $pdo->prepare("SELECT * FROM users ORDER BY created_at DESC");
    $stmt->execute();
    $all_users = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Tree Inspection System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }
        .app-header {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 24px;
        }
        .profile-card {
            background: white;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 32px;
        }
        .profile-header {
            display: flex;
            align-items: center;
            gap: 24px;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 1px solid #e2e8f0;
            flex-wrap: wrap;
        }
        .profile-avatar {
            width: 80px;
            height: 80px;
            background: #b91c1c;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 700;
            color: white;
        }
        .profile-info h1 {
            font-size: 24px;
            margin-bottom: 4px;
        }
        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .role-admin { background: #fef3c7; color: #92400e; }
        .role-inspector { background: #dbeafe; color: #1e40af; }
        .role-viewer { background: #f1f5f9; color: #475569; }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        input, select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #b91c1c;
            box-shadow: 0 0 0 3px rgba(185,28,28,0.1);
        }
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            font-family: 'Inter', sans-serif;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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
        .alert-success {
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #166534;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-error {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 16px;
            font-weight: 600;
            margin: 24px 0 16px 0;
            padding-bottom: 8px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .info-row {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .info-label {
            width: 140px;
            font-weight: 600;
            color: #64748b;
        }
        .info-value {
            flex: 1;
            color: #1e293b;
        }
        .users-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }
        .users-table th, .users-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        .users-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #475569;
            font-size: 12px;
            text-transform: uppercase;
        }
        .users-table td {
            font-size: 13px;
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
            border-radius: 12px;
            padding: 24px;
            max-width: 500px;
            width: 90%;
        }
        @media (max-width: 768px) {
            .container {
                padding: 0 16px;
            }
            .profile-card {
                padding: 20px;
            }
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            .users-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="app-header">
        <h1><i class="fas fa-user-circle" style="color: #b91c1c;"></i> My Profile</h1>
        <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <!-- Profile Section -->
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                </div>
                <div class="profile-info">
                    <h1><?= htmlspecialchars($user['full_name']) ?></h1>
                    <span class="role-badge role-<?= $user['role'] ?>">
                        <?php 
                        switch($user['role']) {
                            case 'admin': echo '👑 Administrator'; break;
                            case 'inspector': echo '🔧 Inspector'; break;
                            case 'viewer': echo '👁️ Viewer'; break;
                        }
                        ?>
                    </span>
                </div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Username</div>
                <div class="info-value"><?= htmlspecialchars($user['username']) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Role</div>
                <div class="info-value"><?= ucfirst($user['role']) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Member Since</div>
                <div class="info-value"><?= date('F j, Y', strtotime($user['created_at'])) ?></div>
            </div>
            
            <div class="section-title">Update Profile</div>
            <form method="POST">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                </div>
                <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
            </form>
            
            <div class="section-title">Change Password</div>
            <form method="POST">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" required minlength="6">
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" required>
                </div>
                <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
            </form>
        </div>
        
        <!-- Admin Only: User Management Section -->
        <?php if (isAdmin()): ?>
        <div class="profile-card">
            <div class="section-title">
                <span><i class="fas fa-users"></i> User Management</span>
                <button class="btn btn-primary btn-sm" onclick="openAddUserModal()">
                    <i class="fas fa-plus"></i> Add New User
                </button>
            </div>
            
            <table class="users-table">
                <thead>
                    <tr><th>Username</th><th>Full Name</th><th>Role</th><th>Created</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($all_users as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['username']) ?></td>
                        <td><?= htmlspecialchars($u['full_name']) ?></td>
                        <td>
                            <span class="role-badge role-<?= $u['role'] ?>">
                                <?= ucfirst($u['role']) ?>
                            </span>
                        </td>
                        <td><?= date('Y-m-d', strtotime($u['created_at'])) ?></td>
                        <td>
                            <button class="btn btn-secondary btn-sm" onclick='editUser(<?= json_encode($u) ?>)'>
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <?php if ($u['user_id'] != $user_id): ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this user?')">
                                <input type="hidden" name="delete_user_id" value="<?= $u['user_id'] ?>">
                                <button type="submit" name="delete_user" class="btn btn-danger btn-sm">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 20px;"><i class="fas fa-user-plus"></i> Add New User</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="new_username" required>
                </div>
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="new_full_name" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="new_password" required minlength="6">
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="new_role">
                        <option value="admin">Admin</option>
                        <option value="inspector" selected>Inspector</option>
                        <option value="viewer">Viewer</option>
                    </select>
                </div>
                <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeAddUserModal()">Cancel</button>
                    <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 20px;"><i class="fas fa-user-edit"></i> Edit User</h3>
            <form method="POST">
                <input type="hidden" name="edit_user_id" id="edit_user_id">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="edit_full_name" id="edit_full_name" required>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="edit_role" id="edit_role">
                        <option value="admin">Admin</option>
                        <option value="inspector">Inspector</option>
                        <option value="viewer">Viewer</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>New Password (leave blank to keep current)</label>
                    <input type="password" name="edit_password" minlength="6">
                </div>
                <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeEditUserModal()">Cancel</button>
                    <button type="submit" name="edit_user" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openAddUserModal() {
            document.getElementById('addUserModal').style.display = 'flex';
        }
        
        function closeAddUserModal() {
            document.getElementById('addUserModal').style.display = 'none';
        }
        
        function editUser(user) {
            document.getElementById('edit_user_id').value = user.user_id;
            document.getElementById('edit_full_name').value = user.full_name;
            document.getElementById('edit_role').value = user.role;
            document.getElementById('editUserModal').style.display = 'flex';
        }
        
        function closeEditUserModal() {
            document.getElementById('editUserModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>