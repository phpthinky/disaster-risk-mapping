<?php
// profile.php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user information
$stmt = $pdo->prepare("
    SELECT u.*, b.name as barangay_name 
    FROM users u 
    LEFT JOIN barangays b ON u.barangay_id = b.id 
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get user activity statistics
$activity_stats = $pdo->prepare("
    SELECT 
        COUNT(*) as total_entries,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_entries,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_entries,
        MAX(created_at) as last_activity
    FROM data_entries 
    WHERE user_id = ?
");
$activity_stats->execute([$user_id]);
$stats = $activity_stats->fetch();

// Get recent activity
$recent_activity = $pdo->prepare("
    SELECT * FROM data_entries 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$recent_activity->execute([$user_id]);
$activities = $recent_activity->fetchAll();

// Handle profile update
if (isset($_POST['update_profile'])) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    // Check if username already exists (excluding current user)
    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $checkStmt->execute([$username, $user_id]);
    
    if ($checkStmt->fetch()) {
        $errors[] = "Username already exists!";
    }
    
    // Validate current password if changing password
    if (!empty($new_password)) {
        if (!password_verify($current_password, $user['password'])) {
            $errors[] = "Current password is incorrect!";
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = "New passwords do not match!";
        }
        
        if (strlen($new_password) < 6) {
            $errors[] = "New password must be at least 6 characters long!";
        }
    }
    
    if (empty($errors)) {
        if (!empty($new_password)) {
            // Update with new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ? WHERE id = ?");
            $stmt->execute([$username, $email, $hashed_password, $user_id]);
        } else {
            // Update without changing password
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
            $stmt->execute([$username, $email, $user_id]);
        }
        
        // Update session username
        $_SESSION['username'] = $username;
        
        $success = "Profile updated successfully!";
        
        // Refresh user data
        $stmt = $pdo->prepare("SELECT u.*, b.name as barangay_name FROM users u LEFT JOIN barangays b ON u.barangay_id = b.id WHERE u.id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
    } else {
        $error = implode("<br>", $errors);
    }
}

// Get barangays for dropdown (only for admin)
$barangays = [];
if ($_SESSION['role'] == 'admin') {
    $barangays = $pdo->query("SELECT * FROM barangays")->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - Sablayan Risk Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
            border-radius: 10px;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin: 0 auto 15px;
            border: 3px solid white;
        }
        .stat-card {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            background: white;
            margin-bottom: 20px;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 0;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            font-weight: 500;
        }
        .card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-2px);
        }
        .activity-item {
            border-left: 3px solid #3498db;
            padding-left: 15px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        .activity-item:hover {
            border-left-color: #2980b9;
            background-color: #f8f9fa;
        }
        .nav-pills .nav-link.active {
            background-color: #3498db;
            border-color: #3498db;
        }
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 2px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Profile Header - NOW INSIDE THE MAIN CONTENT -->
                <div class="profile-header">
                    <div class="container-fluid">
                        <div class="row align-items-center">
                            <div class="col-md-2 text-center">
                                <div class="profile-avatar">
                                    <i class="fas fa-user"></i>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <h1 class="h2 mb-1"><?php echo htmlspecialchars($user['username']); ?></h1>
                                <p class="mb-1">
                                    <i class="fas fa-shield-alt me-1"></i>
                                    <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                </p>
                                <p class="mb-0">
                                    <i class="fas fa-building me-1"></i>
                                    <?php echo $user['barangay_name'] ? htmlspecialchars($user['barangay_name']) : 'All Barangays'; ?>
                                </p>
                            </div>
                            <div class="col-md-2 text-end">
                                <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'danger'; ?> fs-6">
                                    <i class="fas fa-<?php echo $user['is_active'] ? 'check' : 'times'; ?>-circle me-1"></i>
                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <!--<div class="row mb-4">-->
                <!--    <div class="col-md-3">-->
                <!--        <div class="stat-card">-->
                <!--            <div class="stat-value text-primary"><?php echo $stats['total_entries']; ?></div>-->
                <!--            <div class="stat-label">Total Entries</div>-->
                <!--        </div>-->
                <!--    </div>-->
                <!--    <div class="col-md-3">-->
                <!--        <div class="stat-card">-->
                <!--            <div class="stat-value text-success"><?php echo $stats['approved_entries']; ?></div>-->
                <!--            <div class="stat-label">Approved Entries</div>-->
                <!--        </div>-->
                <!--    </div>-->
                <!--    <div class="col-md-3">-->
                <!--        <div class="stat-card">-->
                <!--            <div class="stat-value text-warning"><?php echo $stats['pending_entries']; ?></div>-->
                <!--            <div class="stat-label">Pending Entries</div>-->
                <!--        </div>-->
                <!--    </div>-->
                <!--    <div class="col-md-3">-->
                <!--        <div class="stat-card">-->
                <!--            <div class="stat-value text-info">-->
                <!--                <?php echo $stats['last_activity'] ? date('M j', strtotime($stats['last_activity'])) : 'Never'; ?>-->
                <!--            </div>-->
                <!--            <div class="stat-label">Last Activity</div>-->
                <!--        </div>-->
                <!--    </div>-->
                <!--</div>-->

                <div class="row">
                    <div class="col-md-8">
                        <!-- Profile Information -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-user-edit me-2"></i> Profile Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (isset($success)): ?>
                                    <div class="alert alert-success"><?php echo $success; ?></div>
                                <?php endif; ?>

                                <?php if (isset($error)): ?>
                                    <div class="alert alert-danger"><?php echo $error; ?></div>
                                <?php endif; ?>

                                <form method="POST">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Username</label>
                                                <input type="text" name="username" class="form-control" 
                                                    value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Email Address</label>
                                                <input type="email" name="email" class="form-control" 
                                                    value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Role</label>
                                                <input type="text" class="form-control" 
                                                    value="<?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>" readonly>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Barangay</label>
                                                <input type="text" class="form-control" 
                                                    value="<?php echo $user['barangay_name'] ? htmlspecialchars($user['barangay_name']) : 'All Barangays'; ?>" readonly>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Member Since</label>
                                                <input type="text" class="form-control" 
                                                    value="<?php echo date('M j, Y', strtotime($user['created_at'])); ?>" readonly>
                                            </div>
                                        </div>
                                    </div>

                                    <hr class="my-4">

                                    <h6 class="mb-3">
                                        <i class="fas fa-lock me-2"></i> Change Password
                                        <small class="text-muted">(Leave blank to keep current password)</small>
                                    </h6>

                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Current Password</label>
                                                <input type="password" name="current_password" class="form-control">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">New Password</label>
                                                <input type="password" name="new_password" class="form-control" id="newPassword">
                                                <div class="password-strength" id="passwordStrength"></div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Confirm New Password</label>
                                                <input type="password" name="confirm_password" class="form-control">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between">
                                        <button type="submit" name="update_profile" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Update Profile
                                        </button>
                                        <a href="dashboard<?php echo $_SESSION['role'] == 'admin' ? '' : '_barangay'; ?>.php" 
                                           class="btn btn-outline-secondary">
                                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <!-- Recent Activity -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-history me-2"></i> Recent Activity
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($activities) > 0): ?>
                                    <?php foreach ($activities as $activity): ?>
                                        <div class="activity-item">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1"><?php echo ucfirst($activity['data_type']); ?> Entry</h6>
                                                    <p class="mb-1 small"><?php echo substr($activity['description'], 0, 50); ?>...</p>
                                                    <small class="text-muted">
                                                        <?php echo date('M j, g:i A', strtotime($activity['created_at'])); ?>
                                                    </small>
                                                </div>
                                                <span class="badge bg-<?php 
                                                    echo $activity['status'] == 'approved' ? 'success' : 
                                                         ($activity['status'] == 'rejected' ? 'danger' : 'warning'); 
                                                ?>">
                                                    <?php echo ucfirst($activity['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-inbox fa-2x mb-2"></i>
                                        <p>No recent activity</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Account Status -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-info-circle me-2"></i> Account Status
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <strong>Status:</strong>
                                    <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'danger'; ?> float-end">
                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </div>
                                <div class="mb-3">
                                    <strong>Last Login:</strong>
                                    <span class="float-end text-muted">
                                        <?php echo date('M j, Y g:i A'); ?>
                                    </span>
                                </div>
                                <div class="mb-3">
                                    <strong>Account Created:</strong>
                                    <span class="float-end text-muted">
                                        <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                    </span>
                                </div>
                                <div class="mb-0">
                                    <strong>User ID:</strong>
                                    <span class="float-end text-muted">
                                        #<?php echo $user['id']; ?>
                                    </span>
                                </div>
                            </div>
                        </div>


                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password strength indicator
            const newPasswordInput = document.getElementById('newPassword');
            const passwordStrength = document.getElementById('passwordStrength');
            
            newPasswordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                
                if (password.length >= 6) strength += 25;
                if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength += 25;
                if (password.match(/\d/)) strength += 25;
                if (password.match(/[^a-zA-Z\d]/)) strength += 25;
                
                let color = '#e74c3c'; // red
                if (strength >= 50) color = '#f39c12'; // orange
                if (strength >= 75) color = '#27ae60'; // green
                
                passwordStrength.style.width = strength + '%';
                passwordStrength.style.backgroundColor = color;
            });

            // Add hover effects to cards
            const cards = document.querySelectorAll('.card, .stat-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.boxShadow = '0 4px 15px rgba(0,0,0,0.15)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.boxShadow = '0 2px 10px rgba(0,0,0,0.08)';
                });
            });

            // Form validation
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const newPassword = document.querySelector('input[name="new_password"]').value;
                const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
                const currentPassword = document.querySelector('input[name="current_password"]').value;
                
                if (newPassword && !currentPassword) {
                    e.preventDefault();
                    alert('Please enter your current password to change your password.');
                    return;
                }
                
                if (newPassword && newPassword !== confirmPassword) {
                    e.preventDefault();
                    alert('New passwords do not match.');
                    return;
                }
            });
        });
    </script>
</body>
</html>