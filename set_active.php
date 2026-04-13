<?php
require_once 'config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['upload_id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$upload_id = intval($_POST['upload_id']);
$pdo = db();

try {
    // Deactivate all uploads
    $pdo->prepare("UPDATE uploads SET is_active = 0")->execute();
    
    // Activate the selected upload
    $pdo->prepare("UPDATE uploads SET is_active = 1 WHERE upload_id = ?")->execute([$upload_id]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>