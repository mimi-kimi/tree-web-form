<?php
session_start();
require_once 'config/db.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['user_role'] !== $role && $_SESSION['user_role'] !== 'admin') {
        header('Location: index.php?error=unauthorized');
        exit;
    }
}

function getCurrentUser() {
    return $_SESSION;
}

// Acquire lock for editing
function acquireLock($tree_no, $upload_id, $user_id) {
    $pdo = db();
    
    // Clean up expired locks
    $pdo->prepare("DELETE FROM inspection_locks WHERE expires_at < NOW()")->execute();
    
    // Try to acquire lock
    try {
        $stmt = $pdo->prepare("
            INSERT INTO inspection_locks (tree_no, upload_id, user_id, expires_at) 
            VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE))
        ");
        $stmt->execute([$tree_no, $upload_id, $user_id]);
        return true;
    } catch (PDOException $e) {
        // Lock already exists, check who has it
        $stmt = $pdo->prepare("
            SELECT u.full_name, u.username, l.locked_at, l.expires_at 
            FROM inspection_locks l
            JOIN users u ON l.user_id = u.user_id
            WHERE l.tree_no = ? AND l.upload_id = ?
        ");
        $stmt->execute([$tree_no, $upload_id]);
        return $stmt->fetch();
    }
}

// Release lock
function releaseLock($tree_no, $upload_id, $user_id) {
    $pdo = db();
    $stmt = $pdo->prepare("DELETE FROM inspection_locks WHERE tree_no = ? AND upload_id = ? AND user_id = ?");
    $stmt->execute([$tree_no, $upload_id, $user_id]);
}

// Renew lock (keep alive while editing)
function renewLock($tree_no, $upload_id, $user_id) {
    $pdo = db();
    $stmt = $pdo->prepare("
        UPDATE inspection_locks 
        SET expires_at = DATE_ADD(NOW(), INTERVAL 5 MINUTE) 
        WHERE tree_no = ? AND upload_id = ? AND user_id = ?
    ");
    $stmt->execute([$tree_no, $upload_id, $user_id]);
}

// Check if user has lock
function hasLock($tree_no, $upload_id, $user_id) {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM inspection_locks 
        WHERE tree_no = ? AND upload_id = ? AND user_id = ? AND expires_at > NOW()
    ");
    $stmt->execute([$tree_no, $upload_id, $user_id]);
    return $stmt->fetchColumn() > 0;
}
function canEdit() {
    return in_array($_SESSION['user_role'], ['admin', 'inspector']);
}

function canDelete() {
    return $_SESSION['user_role'] === 'admin';
}

function canManageUsers() {
    return $_SESSION['user_role'] === 'admin';
}

function canOverrideLock() {
    return $_SESSION['user_role'] === 'admin';
}

function isAdmin() {
    return $_SESSION['user_role'] === 'admin';
}

function isInspector() {
    return $_SESSION['user_role'] === 'inspector';
}

function isViewer() {
    return $_SESSION['user_role'] === 'viewer';
}
?>
