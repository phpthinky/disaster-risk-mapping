<?php
// force_admin.php
// Place this file in your project root, run it once, then DELETE it immediately.

require_once 'config.php';

// ─────────────────────────────────────────
//  CONFIGURE YOUR ADMIN CREDENTIALS HERE
// ─────────────────────────────────────────
$username = 'admin';
$password = 'Admin@1234';   // Change this!
$email    = 'admin@sablayan.gov.ph';
// ─────────────────────────────────────────

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Check if username already exists
$check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
$check->execute([$username]);
$existing = $check->fetch();

if ($existing) {
    // Force update existing user to admin
    $stmt = $pdo->prepare("
        UPDATE users 
        SET password = ?, email = ?, role = 'admin', is_active = 1, barangay_id = NULL
        WHERE username = ?
    ");
    $stmt->execute([$hashedPassword, $email, $username]);
    $action = "UPDATED";
    $userId = $existing['id'];
} else {
    // Insert new admin user
    $stmt = $pdo->prepare("
        INSERT INTO users (username, password, email, role, is_active, barangay_id)
        VALUES (?, ?, ?, 'admin', 1, NULL)
    ");
    $stmt->execute([$hashedPassword, $email, $username]);
    $action = "CREATED";
    $userId = $pdo->lastInsertId();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Force Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex justify-content-center align-items-center" style="min-height:100vh;">
    <div class="card shadow" style="max-width:480px; width:100%;">
        <div class="card-body p-4">
            <h4 class="card-title text-success mb-4">
                <i class="bi bi-shield-check me-2"></i>
                Admin User <?php echo $action; ?>
            </h4>

            <table class="table table-bordered table-sm">
                <tr>
                    <th width="40%">User ID</th>
                    <td><strong><?php echo $userId; ?></strong></td>
                </tr>
                <tr>
                    <th>Username</th>
                    <td><strong><?php echo htmlspecialchars($username); ?></strong></td>
                </tr>
                <tr>
                    <th>Password</th>
                    <td><code><?php echo htmlspecialchars($password); ?></code></td>
                </tr>
                <tr>
                    <th>Email</th>
                    <td><?php echo htmlspecialchars($email); ?></td>
                </tr>
                <tr>
                    <th>Role</th>
                    <td><span class="badge bg-danger">admin</span></td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td><span class="badge bg-success">Active</span></td>
                </tr>
            </table>

            <div class="alert alert-warning mt-3 mb-3">
                <strong>⚠️ Important:</strong> Delete <code>force_admin.php</code> from your server immediately after use!
            </div>

            <a href="login.php" class="btn btn-primary w-100">Go to Login</a>
        </div>
    </div>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</body>
</html>