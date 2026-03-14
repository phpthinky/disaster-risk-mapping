<?php
// create_admin.php
require_once 'config.php';

$password = '12345';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Check if admin already exists
$stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'admin'");
$stmt->execute();
$admin_exists = $stmt->fetch();

if ($admin_exists) {
    // Update existing admin password
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
    $stmt->execute([$hashed_password]);
    echo "Admin password updated successfully!<br>";
    echo "Username: admin<br>";
    echo "Password: 12345<br>";
    echo "Hashed password: " . $hashed_password;
} else {
    // Create new admin user
    $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, 'admin')");
    $stmt->execute(['admin', $hashed_password, 'admin@sablayan.gov.ph']);
    echo "Admin user created successfully!<br>";
    echo "Username: admin<br>";
    echo "Password: 12345<br>";
    echo "Hashed password: " . $hashed_password;
}
?>