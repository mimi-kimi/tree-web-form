<?php
require_once 'config/db.php';

$pdo = db();

// Check if users table exists, if not create it
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'inspector', 'viewer') DEFAULT 'inspector',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Set admin password
$admin_password = 'admin123';
$hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);

// Check if admin exists
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'admin'");
$stmt->execute();
$admin = $stmt->fetch();

if ($admin) {
    // Update existing admin
    $stmt = $pdo->prepare("UPDATE users SET password = ?, full_name = 'Administrator', role = 'admin', is_active = 1 WHERE username = 'admin'");
    $stmt->execute([$hashed_password]);
    echo "Admin user updated successfully!<br>";
} else {
    // Create new admin
    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, is_active) VALUES (?, ?, ?, 'admin', 1)");
    $stmt->execute(['admin', $hashed_password, 'Administrator']);
    echo "Admin user created successfully!<br>";
}

echo "<br><strong>Login Credentials:</strong><br>";
echo "Username: admin<br>";
echo "Password: admin123<br>";
echo "<br><a href='login.php'>Go to Login Page</a>";
?>