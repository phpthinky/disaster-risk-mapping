<?php
// announcements.php
session_start();
require_once 'core/core.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'barangay_staff') {
    header('Location: login.php');
    exit;
} else{
    $user_id = $_SESSION['user_id'];
}

// Handle form submissions
if ($_POST) {
    if (isset($_POST['add_announcement'])) {
        $title = $_POST['title'];
        $message = $_POST['message'];
        $announcement_type = $_POST['announcement_type'];
        $target_audience = $_POST['target_audience'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $pdo->prepare("INSERT INTO announcements 
                              (title, message, announcement_type, target_audience, is_active, created_by) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $message, $announcement_type, $target_audience, $is_active, $_SESSION['user_id']]);
        
        $success = "Announcement posted successfully!";
    }
    
    if (isset($_POST['update_announcement'])) {
        $announcement_id = $_POST['announcement_id'];
        $title = $_POST['title'];
        $message = $_POST['message'];
        $announcement_type = $_POST['announcement_type'];
        $target_audience = $_POST['target_audience'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $pdo->prepare("UPDATE announcements 
                              SET title = ?, message = ?, announcement_type = ?, target_audience = ?, 
                                  is_active = ?, updated_at = NOW() 
                              WHERE id = ?");
        $stmt->execute([$title, $message, $announcement_type, $target_audience, $is_active, $announcement_id]);
        
        $success = "Announcement updated successfully!";
    }
}

// Handle delete action
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    
    $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
    $stmt->execute([$delete_id]);
    
    header('Location: announcements.php?deleted=1');
    exit;
}

// Handle toggle active status
if (isset($_GET['toggle_active'])) {
    $announcement_id = $_GET['toggle_active'];
    
    $stmt = $pdo->prepare("UPDATE announcements SET is_active = NOT is_active WHERE id = ?");
    $stmt->execute([$announcement_id]);
    
    header('Location: announcements.php?toggled=1');
    exit;
}

// Handle edit action
$edit_announcement = null;
if (isset($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    $stmt = $pdo->prepare("SELECT * FROM announcements WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_announcement = $stmt->fetch();
}

// Get announcements data
$announcements = $pdo->query("
    SELECT a.*, u.username as created_by_name 
    FROM announcements a 
    LEFT JOIN users u ON a.created_by = u.id WHERE created_by = '$user_id'
    ORDER BY a.created_at DESC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements Management - Sablayan Risk Assessment</title>
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
        .announcement-emergency {
            border-left: 4px solid #dc3545;
        }
        .announcement-info {
            border-left: 4px solid #17a2b8;
        }
        .announcement-maintenance {
            border-left: 4px solid #ffc107;
        }
        .announcement-general {
            border-left: 4px solid #6c757d;
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
                    <h1 class="h2">Announcements Management</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal">
                        <i class="fas fa-bullhorn me-2"></i>Post New Announcement
                    </button>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <?php if (isset($_GET['deleted'])): ?>
                    <div class="alert alert-success">Announcement deleted successfully!</div>
                <?php endif; ?>

                <?php if (isset($_GET['toggled'])): ?>
                    <div class="alert alert-success">Announcement status updated successfully!</div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-12">
                        <!-- Announcements List -->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">All Announcements</h5>
                                <span class="badge bg-primary"><?php echo count($announcements); ?> announcements</span>
                            </div>
                            <div class="card-body">
                                <?php if (count($announcements) > 0): ?>
                                    <?php foreach ($announcements as $announcement): ?>
                                        <div class="card mb-3 announcement-<?php echo $announcement['announcement_type']; ?>">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h5 class="card-title">
                                                        <i class="fas fa-<?php 
                                                            echo $announcement['announcement_type'] == 'emergency' ? 'exclamation-triangle' : 
                                                                 ($announcement['announcement_type'] == 'info' ? 'info-circle' : 
                                                                 ($announcement['announcement_type'] == 'maintenance' ? 'tools' : 'bullhorn')); 
                                                        ?> me-2 text-<?php 
                                                            echo $announcement['announcement_type'] == 'emergency' ? 'danger' : 
                                                                 ($announcement['announcement_type'] == 'info' ? 'info' : 
                                                                 ($announcement['announcement_type'] == 'maintenance' ? 'warning' : 'secondary')); 
                                                        ?>"></i>
                                                        <?php echo htmlspecialchars($announcement['title']); ?>
                                                    </h5>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="announcements.php?edit_id=<?php echo $announcement['id']; ?>" 
                                                           class="btn btn-outline-primary" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="announcements.php?toggle_active=<?php echo $announcement['id']; ?>" 
                                                           class="btn btn-<?php echo $announcement['is_active'] ? 'outline-warning' : 'outline-success'; ?>" 
                                                           title="<?php echo $announcement['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                            <i class="fas fa-<?php echo $announcement['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                        </a>
                                                        <button class="btn btn-outline-danger delete-announcement" 
                                                                data-id="<?php echo $announcement['id']; ?>" 
                                                                data-title="<?php echo htmlspecialchars($announcement['title']); ?>"
                                                                title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <p class="card-text"><?php echo nl2br(htmlspecialchars($announcement['message'])); ?></p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <span class="badge bg-<?php 
                                                            echo $announcement['announcement_type'] == 'emergency' ? 'danger' : 
                                                                 ($announcement['announcement_type'] == 'info' ? 'info' : 
                                                                 ($announcement['announcement_type'] == 'maintenance' ? 'warning' : 'secondary')); 
                                                        ?>">
                                                            <?php echo ucfirst($announcement['announcement_type']); ?>
                                                        </span>
                                                        <span class="badge bg-<?php echo $announcement['is_active'] ? 'success' : 'secondary'; ?> ms-1">
                                                            <?php echo $announcement['is_active'] ? 'Active' : 'Inactive'; ?>
                                                        </span>
                                                        <span class="badge bg-primary ms-1">
                                                            Target: <?php echo str_replace('_', ' ', $announcement['target_audience']); ?>
                                                        </span>
                                                    </div>
                                                    <small class="text-muted">
                                                        By: <?php echo htmlspecialchars($announcement['created_by_name']); ?> | 
                                                        <?php echo date('M j, Y g:i A', strtotime($announcement['created_at'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-5">
                                        <i class="fas fa-bullhorn fa-3x mb-3"></i>
                                        <h5>No Announcements Yet</h5>
                                        <p>Post your first announcement to keep everyone informed.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Announcement Modal -->
    <div class="modal fade" id="addAnnouncementModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Post New Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Announcement Title</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea name="message" class="form-control" rows="5" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Announcement Type</label>
                                    <select name="announcement_type" class="form-select" required>
                                        <option value="general">General</option>
                                        <option value="info">Information</option>
                                        <option value="maintenance">Maintenance</option>
                                        <option value="emergency">Emergency</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Target Audience</label>
                                    <select name="target_audience" class="form-select" required>
                                        <option value="all">All Users</option>
                                        <option value="barangay_staff">Barangay Staff Only</option>
                                        <option value="division_chief">Division Chiefs Only</option>
                                        <option value="admin">Administrators Only</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" id="isActive" checked>
                            <label class="form-check-label" for="isActive">Activate this announcement immediately</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_announcement" class="btn btn-primary">Post Announcement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Announcement Modal -->
    <div class="modal fade" id="editAnnouncementModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="announcement_id" id="editAnnouncementId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Announcement Title</label>
                            <input type="text" name="title" class="form-control" id="editTitle" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea name="message" class="form-control" rows="5" id="editMessage" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Announcement Type</label>
                                    <select name="announcement_type" class="form-select" id="editType" required>
                                        <option value="general">General</option>
                                        <option value="info">Information</option>
                                        <option value="maintenance">Maintenance</option>
                                        <option value="emergency">Emergency</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Target Audience</label>
                                    <select name="target_audience" class="form-select" id="editAudience" required>
                                        <option value="all">All Users</option>
                                        <option value="barangay_staff">Barangay Staff Only</option>
                                        <option value="division_chief">Division Chiefs Only</option>
                                        <option value="admin">Administrators Only</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" id="editIsActive">
                            <label class="form-check-label" for="editIsActive">Activate this announcement</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_announcement" class="btn btn-warning">Update Announcement</button>
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
                    <p>Are you sure you want to delete announcement: <strong id="deleteAnnouncementTitle"></strong>?</p>
                    <p class="text-danger"><small>This action cannot be undone.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Modals
            const addAnnouncementModal = new bootstrap.Modal(document.getElementById('addAnnouncementModal'));
            const editAnnouncementModal = new bootstrap.Modal(document.getElementById('editAnnouncementModal'));
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));

            // Edit announcement buttons
            document.querySelectorAll('.edit-announcement').forEach(btn => {
                btn.addEventListener('click', function() {
                    const announcementId = this.getAttribute('data-id');
                    const row = this.closest('.card');
                    
                    // In a real application, you would fetch the data via AJAX
                    // For now, we'll redirect to the edit page
                    window.location.href = `announcements.php?edit_id=${announcementId}`;
                });
            });

            // Delete announcement buttons
            document.querySelectorAll('.delete-announcement').forEach(btn => {
                btn.addEventListener('click', function() {
                    const announcementId = this.getAttribute('data-id');
                    const announcementTitle = this.getAttribute('data-title');
                    
                    document.getElementById('deleteAnnouncementTitle').textContent = announcementTitle;
                    document.getElementById('confirmDeleteBtn').href = `announcements.php?delete_id=${announcementId}`;
                    deleteModal.show();
                });
            });

            // If we're in edit mode, show the edit modal
            <?php if ($edit_announcement): ?>
                document.getElementById('editAnnouncementId').value = '<?php echo $edit_announcement['id']; ?>';
                document.getElementById('editTitle').value = '<?php echo htmlspecialchars($edit_announcement['title']); ?>';
                document.getElementById('editMessage').value = '<?php echo htmlspecialchars($edit_announcement['message']); ?>';
                document.getElementById('editType').value = '<?php echo $edit_announcement['announcement_type']; ?>';
                document.getElementById('editAudience').value = '<?php echo $edit_announcement['target_audience']; ?>';
                document.getElementById('editIsActive').checked = <?php echo $edit_announcement['is_active'] ? 'true' : 'false'; ?>;
                
                editAnnouncementModal.show();
            <?php endif; ?>
        });
    </script>
</body>
</html>