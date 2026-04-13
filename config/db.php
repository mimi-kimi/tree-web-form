<?php
// config/db.php
function db() {
$host = 'localhost';
$db   = 'hsinnova_tree_inspections';
$user = 'hsinnova_hsinnova_tree_admin';
$pass = 'Hs!nn0v@'; // <--- Update this!
$charset = 'utf8mb4';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}