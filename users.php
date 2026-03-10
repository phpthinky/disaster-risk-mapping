<?php

// users.php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

// Handle form submissions
if ($_POST) {
    // Add new user
    if (isset($_POST['add_user'])) {
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $email = $_POST['email'];
        $barangay_id = $_POST['barangay_id'];
        $role = $_POST['role'];
        
        // Check if username already exists
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $checkStmt->execute([$username]);
        
        if ($checkStmt->fetch()) {
            $error = "Username already exists!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, email, barangay_id, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$username, $password, $email, $barangay_id, $role]);
            $success = "User added successfully!";
        }
    }
    
    // Update user
    if (isset($_POST['update_user'])) {
        $user_id = $_POST['user_id'];
        $username = $_POST['username'];
        $email = $_POST['email'];
        $barangay_id = $_POST['barangay_id'];
        $role = $_POST['role'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Check if username already exists (excluding current user)
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $checkStmt->execute([$username, $user_id]);
        
        if ($checkStmt->fetch()) {
            $error = "Username already exists!";
        } else {
            // Update password only if provided
            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, email = ?, barangay_id = ?, role = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$username, $password, $email, $barangay_id, $role, $is_active, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, barangay_id = ?, role = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$username, $email, $barangay_id, $role, $is_active, $user_id]);
            }
            $success = "User updated successfully!";
        }
    }
}

// Handle delete action
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    
    // Prevent admin from deleting themselves
    if ($delete_id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$delete_id]);
        $success = "User deleted successfully!";
    } else {
        $error = "You cannot delete your own account!";
    }
}

// Handle toggle active status
if (isset($_GET['toggle_active'])) {
    $user_id = $_GET['toggle_active'];
    
    // Prevent admin from deactivating themselves
    if ($user_id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$user_id]);
        $success = "User status updated successfully!";
    } else {
        $error = "You cannot deactivate your own account!";
    }
}

// Get all users with barangay information
$users = $pdo->query("
    SELECT u.*, b.name as barangay_name 
    FROM users u 
    LEFT JOIN barangays b ON u.barangay_id = b.id 
    ORDER BY u.created_at DESC
")->fetchAll();

// Get barangays for dropdown
$barangays = $pdo->query("SELECT * FROM barangays")->fetchAll();

// Calculate statistics
$totalUsers = count($users);
$activeUsers = array_filter($users, function($user) { return $user['is_active']; });
$adminUsers = array_filter($users, function($user) { return $user['role'] == 'admin'; });
$staffUsers = array_filter($users, function($user) { return $user['role'] == 'barangay_staff'; });
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Sablayan Risk Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-2px);
        }
        .card-header {
            background-color: white;
            border-bottom: 1px solid #eee;
            font-weight: 600;
        }
        .status-active { color: #27ae60; font-weight: bold; }
        .status-inactive { color: #e74c3c; font-weight: bold; }
        .role-admin { color: #e74c3c; font-weight: bold; }
        .role-staff { color: #3498db; font-weight: bold; }
        .stat-card {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
        }
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            font-weight: 500;
        }
        .btn-action {
            transition: all 0.2s;
        }
        .btn-action:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">User Management</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-user-plus me-2"></i>Add New User
                    </button>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card stat-card border-0 text-center p-3 shadow-card">
            <div class="card-body">
                <i class="bi bi-people-fill text-primary fs-1 mb-2"></i>
                <h3 class="fw-bold text-primary mb-1"><?php echo $totalUsers; ?></h3>
                <p class="text-secondary fw-semibold mb-0">Total Users</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card stat-card border-0 text-center p-3 shadow-card">
            <div class="card-body">
                <i class="bi bi-person-check-fill text-success fs-1 mb-2"></i>
                <h3 class="fw-bold text-success mb-1"><?php echo count($activeUsers); ?></h3>
                <p class="text-secondary fw-semibold mb-0">Active Users</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card stat-card border-0 text-center p-3 shadow-card">
            <div class="card-body">
                <i class="bi bi-shield-lock-fill text-danger fs-1 mb-2"></i>
                <h3 class="fw-bold text-danger mb-1"><?php echo count($adminUsers); ?></h3>
                <p class="text-secondary fw-semibold mb-0">Administrators</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card stat-card border-0 text-center p-3 shadow-card">
            <div class="card-body">
                <i class="bi bi-building text-info fs-1 mb-2"></i>
                <h3 class="fw-bold text-info mb-1"><?php echo count($staffUsers); ?></h3>
                <p class="text-secondary fw-semibold mb-0">Barangay Staff</p>
            </div>
        </div>
    </div>
</div>

<!-- Custom CSS -->
<style>
.shadow-card {
    border-radius: 15px;
    background: #ffffff;
    border: 2px solid #e9ecef;
    box-shadow: 0 6px 15px rgba(0,0,0,0.15);
    transition: all 0.3s ease;
}

.shadow-card:hover {
    transform: translateY(-8px);
    border-color: #007bff;
    box-shadow: 0 12px 25px rgba(0,123,255,0.3);
    background: linear-gradient(180deg, #ffffff, #f8f9fa);
}

.stat-card i {
    transition: transform 0.3s ease, color 0.3s ease;
}

.stat-card:hover i {
    transform: scale(1.3);
}

.stat-card p {
    letter-spacing: 0.5px;
}
</style>

<!-- Bootstrap Icons CDN -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">


                <!-- Users Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">All Users</h5>
                        <div class="input-group" style="width: 300px;">
                            <input type="text" id="searchUsers" class="form-control" placeholder="Search users...">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="usersTable">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Barangay</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-user-circle me-2 text-muted"></i>
                                                    <?php echo htmlspecialchars($user['username']); ?>
                                                    <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                                        <span class="badge bg-info ms-2">You</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="role-<?php echo $user['role']; ?>">
                                                    <i class="fas <?php echo $user['role'] == 'admin' ? 'fa-shield-alt' : 'fa-user-tie'; ?> me-1"></i>
                                                    <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['barangay_name'] ?? 'N/A'); ?></td>
                                            <td>
                                                <?php if ($user['is_active']): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check-circle me-1"></i>Active
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">
                                                        <i class="fas fa-times-circle me-1"></i>Inactive
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary btn-action edit-user" 
                                                            data-user-id="<?php echo $user['id']; ?>"
                                                            data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                            data-email="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                                                            data-role="<?php echo $user['role']; ?>"
                                                            data-barangay-id="<?php echo $user['barangay_id']; ?>"
                                                            data-is-active="<?php echo $user['is_active']; ?>"
                                                            title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                        <a href="users.php?toggle_active=<?php echo $user['id']; ?>" 
                                                           class="btn btn-<?php echo $user['is_active'] ? 'outline-warning' : 'outline-success'; ?> btn-action" 
                                                           title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                            <i class="fas fa-<?php echo $user['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                        </a>
                                                        <button class="btn btn-outline-danger btn-action delete-user" 
                                                                data-user-id="<?php echo $user['id']; ?>" 
                                                                data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                                title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="btn btn-outline-secondary disabled" title="Cannot modify your own account">
                                                            <i class="fas fa-lock"></i>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" name="username" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="password" name="password" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Role</label>
                                    <select name="role" class="form-select" required>
                                        <option value="barangay_staff">Barangay Staff</option>
                                        <option value="admin">Administrator</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Barangay</label>
                            <select name="barangay_id" class="form-select" id="addBarangaySelect">
                                <option value="">Select Barangay (Optional)</option>
                                <?php foreach ($barangays as $barangay): ?>
                                    <option value="<?php echo $barangay['id']; ?>"><?php echo $barangay['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Barangay assignment is required for barangay staff</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="user_id" id="editUserId">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" name="username" class="form-control" id="editUsername" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="password" name="password" class="form-control" id="editPassword">
                                    <small class="text-muted">Leave blank to keep current password</small>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" id="editEmail">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Role</label>
                                    <select name="role" class="form-select" id="editRole" required>
                                        <option value="barangay_staff">Barangay Staff</option>
                                        <option value="admin">Administrator</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Barangay</label>
                            <select name="barangay_id" class="form-select" id="editBarangaySelect">
                                <option value="">Select Barangay (Optional)</option>
                                <?php foreach ($barangays as $barangay): ?>
                                    <option value="<?php echo $barangay['id']; ?>"><?php echo $barangay['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" id="editIsActive">
                            <label class="form-check-label" for="editIsActive">Active User</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_user" class="btn btn-warning">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete user: <strong id="deleteUserName"></strong>?</p>
                    <p class="text-danger"><small>This action cannot be undone. All data associated with this user will be lost.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete User</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Modals
            const addUserModal = new bootstrap.Modal(document.getElementById('addUserModal'));
            const editUserModal = new bootstrap.Modal(document.getElementById('editUserModal'));
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));

            // Edit user buttons
            document.querySelectorAll('.edit-user').forEach(btn => {
                btn.addEventListener('click', function() {
                    const userId = this.getAttribute('data-user-id');
                    const username = this.getAttribute('data-username');
                    const email = this.getAttribute('data-email');
                    const role = this.getAttribute('data-role');
                    const barangayId = this.getAttribute('data-barangay-id');
                    const isActive = this.getAttribute('data-is-active');

                    document.getElementById('editUserId').value = userId;
                    document.getElementById('editUsername').value = username;
                    document.getElementById('editEmail').value = email;
                    document.getElementById('editRole').value = role;
                    document.getElementById('editBarangaySelect').value = barangayId;
                    document.getElementById('editIsActive').checked = isActive === '1';

                    editUserModal.show();
                });
            });

            // Delete user buttons
            document.querySelectorAll('.delete-user').forEach(btn => {
                btn.addEventListener('click', function() {
                    const userId = this.getAttribute('data-user-id');
                    const username = this.getAttribute('data-username');
                    
                    document.getElementById('deleteUserName').textContent = username;
                    document.getElementById('confirmDeleteBtn').href = `users.php?delete_id=${userId}`;
                    deleteModal.show();
                });
            });

            // Search functionality
            const searchInput = document.getElementById('searchUsers');
            const usersTable = document.getElementById('usersTable');
            
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = usersTable.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
                
                for (let row of rows) {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                }
            });

            // Show/hide barangay field based on role
            function toggleBarangayRequired(selectElement, isRequired) {
                if (isRequired) {
                    selectElement.required = true;
                    selectElement.closest('.mb-3').querySelector('small').style.display = 'block';
                } else {
                    selectElement.required = false;
                    selectElement.closest('.mb-3').querySelector('small').style.display = 'none';
                }
            }

            // For add modal
            const addRoleSelect = document.querySelector('#addUserModal select[name="role"]');
            const addBarangaySelect = document.getElementById('addBarangaySelect');
            
            addRoleSelect.addEventListener('change', function() {
                toggleBarangayRequired(addBarangaySelect, this.value === 'barangay_staff');
            });
            toggleBarangayRequired(addBarangaySelect, addRoleSelect.value === 'barangay_staff');

            // For edit modal
            const editRoleSelect = document.querySelector('#editUserModal select[name="role"]');
            const editBarangaySelect = document.getElementById('editBarangaySelect');
            
            editRoleSelect.addEventListener('change', function() {
                toggleBarangayRequired(editBarangaySelect, this.value === 'barangay_staff');
            });

            // Add hover effects to table rows
            const tableRows = document.querySelectorAll('#usersTable tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f8f9fa';
                });
                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                });
            });
        });
    </script>
</body>
</html>