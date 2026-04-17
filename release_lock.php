<?php
session_start();
require_once 'config/db.php';
require_once 'config/auth.php');

if (!isLoggedIn()) {
    exit;
}

$tree_no = intval($_GET['tree_no'] ?? 0);
$upload_id = intval($_GET['upload_id'] ?? 0);

if ($tree_no && $upload_id) {
    releaseLock($tree_no, $upload_id, $_SESSION['user_id']);
}
?>