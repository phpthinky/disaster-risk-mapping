<?php
// dashboard_division.php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'division_chief') {
    header('Location: login.php');
    exit;
}

// Get statistics for dashboard
$totalEvacCenters = $pdo->query("SELECT COUNT(*) as total FROM evacuation_centers")->fetch()['total'];
$totalCapacity = $pdo->query("SELECT SUM(capacity) as total FROM evacuation_centers")->fetch()['total'];
$activeAnnouncements = $pdo->query("SELECT COUNT(*) as total FROM announcements WHERE is_active = 1")->fetch()['total'];
$operationalCenters = $pdo->query("SELECT COUNT(*) as total FROM evacuation_centers WHERE status = 'operational'")->fetch()['total'];

// Get recent announcements
$recentAnnouncements = $pdo->query("
    SELECT a.*, u.username as created_by_name 
    FROM announcements a 
    LEFT JOIN users u ON a.created_by = u.id 
    WHERE a.is_active = 1 
    ORDER BY a.created_at DESC 
    LIMIT 5
")->fetchAll();

// Get evacuation centers status
$evacStatus = $pdo->query("
    SELECT 
        status,
        COUNT(*) as count,
        SUM(capacity) as total_capacity
    FROM evacuation_centers 
    GROUP BY status
")->fetchAll();

// Get barangay evacuation center distribution
$barangayDistribution = $pdo->query("
    SELECT b.name, COUNT(ec.id) as center_count, SUM(ec.capacity) as total_capacity
    FROM barangays b
    LEFT JOIN evacuation_centers ec ON b.id = ec.barangay_id
    GROUP BY b.id, b.name
    HAVING center_count > 0
    ORDER BY center_count DESC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Division Chief Dashboard - Sablayan Risk Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .stat-card {
            text-align: center;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            background: white;
            margin-bottom: 20px;
            transition: transform 0.3s;
            border-left: 4px solid;
        }
        .stat-card:hover {
            transform: translateY(-5px);
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
        .card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-2px);
        }
        .alert-emergency {
            border-left: 4px solid #dc3545;
        }
        .alert-info {
            border-left: 4px solid #17a2b8;
        }
        .quick-action-btn {
            transition: all 0.3s;
        }
        .quick-action-btn:hover {
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
                <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-4 border-bottom">
                    <h2>Division Chief Dashboard</h2>
                    <div class="btn-group">
                        <a href="evacuation_centers.php" class="btn btn-primary">
                            <i class="fas fa-home me-2"></i>Manage Evacuation Centers
                        </a>
                        <a href="announcements.php" class="btn btn-warning">
                            <i class="fas fa-bullhorn me-2"></i>Post Announcement
                        </a>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card" style="border-left-color: #3498db;">
                            <div class="stat-value text-primary"><?php echo $totalEvacCenters; ?></div>
                            <div class="stat-label">Evacuation Centers</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card" style="border-left-color: #27ae60;">
                            <div class="stat-value text-success"><?php echo number_format($totalCapacity); ?></div>
                            <div class="stat-label">Total Capacity</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card" style="border-left-color: #e74c3c;">
                            <div class="stat-value text-danger"><?php echo $activeAnnouncements; ?></div>
                            <div class="stat-label">Active Announcements</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card" style="border-left-color: #f39c12;">
                            <div class="stat-value text-warning"><?php echo $operationalCenters; ?></div>
                            <div class="stat-label">Operational Centers</div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <!-- Quick Actions -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-bolt me-2"></i> Quick Actions
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <a href="evacuation_centers.php?action=add" class="btn btn-primary w-100 quick-action-btn">
                                            <i class="fas fa-plus-circle me-2"></i>Add Evac Center
                                        </a>
                                    </div>
                                    <div class="col-md-6">
                                        <a href="announcements.php?action=add" class="btn btn-warning w-100 quick-action-btn">
                                            <i class="fas fa-bullhorn me-2"></i>New Announcement
                                        </a>
                                    </div>
                                    <div class="col-md-6">
                                        <a href="evacuation_centers.php" class="btn btn-info w-100 quick-action-btn">
                                            <i class="fas fa-list me-2"></i>View All Centers
                                        </a>
                                    </div>
                                    <div class="col-md-6">
                                        <a href="announcements.php" class="btn btn-success w-100 quick-action-btn">
                                            <i class="fas fa-eye me-2"></i>View Announcements
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Evacuation Centers Status -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-chart-pie me-2"></i> Evacuation Centers Status
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($evacStatus as $status): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 border rounded">
                                        <div>
                                            <span class="badge bg-<?php 
                                                echo $status['status'] == 'operational' ? 'success' : 
                                                     ($status['status'] == 'maintenance' ? 'warning' : 'danger'); 
                                            ?>">
                                                <?php echo ucfirst($status['status']); ?>
                                            </span>
                                        </div>
                                        <div class="text-end">
                                            <strong><?php echo $status['count']; ?> centers</strong><br>
                                            <small class="text-muted">Capacity: <?php echo number_format($status['total_capacity']); ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <!-- Recent Announcements -->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-bullhorn me-2"></i> Recent Announcements
                                </h5>
                                <a href="announcements.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (count($recentAnnouncements) > 0): ?>
                                    <?php foreach ($recentAnnouncements as $announcement): ?>
                                        <div class="alert alert-<?php 
                                            echo $announcement['announcement_type'] == 'emergency' ? 'danger' : 
                                                 ($announcement['announcement_type'] == 'info' ? 'info' : 
                                                 ($announcement['announcement_type'] == 'maintenance' ? 'warning' : 'secondary')); 
                                        ?> alert-<?php echo $announcement['announcement_type'] == 'emergency' ? 'emergency' : 'info'; ?> mb-3">
                                            <h6 class="alert-heading">
                                                <i class="fas fa-<?php 
                                                    echo $announcement['announcement_type'] == 'emergency' ? 'exclamation-triangle' : 
                                                         ($announcement['announcement_type'] == 'info' ? 'info-circle' : 
                                                         ($announcement['announcement_type'] == 'maintenance' ? 'tools' : 'bullhorn')); 
                                                ?> me-2"></i>
                                                <?php echo htmlspecialchars($announcement['title']); ?>
                                            </h6>
                                            <p class="mb-1"><?php echo htmlspecialchars(substr($announcement['message'], 0, 100)); ?>...</p>
                                            <small class="text-muted">
                                                By: <?php echo htmlspecialchars($announcement['created_by_name']); ?> | 
                                                <?php echo date('M j, Y g:i A', strtotime($announcement['created_at'])); ?>
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-bullhorn fa-2x mb-2"></i>
                                        <p>No recent announcements</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Barangay Distribution -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-map-marker-alt me-2"></i> Centers by Barangay
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($barangayDistribution) > 0): ?>
                                    <?php foreach ($barangayDistribution as $barangay): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2 p-2 border rounded">
                                            <div>
                                                <strong><?php echo htmlspecialchars($barangay['name']); ?></strong>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-primary"><?php echo $barangay['center_count']; ?> centers</span><br>
                                                <small class="text-muted">Capacity: <?php echo number_format($barangay['total_capacity']); ?></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-home fa-2x mb-2"></i>
                                        <p>No evacuation centers registered</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Emergency Contacts -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-phone-alt me-2"></i> Emergency Contacts
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-md-3 mb-3">
                                        <i class="fas fa-ambulance fa-2x text-danger mb-2"></i>
                                        <h6>Emergency Response</h6>
                                        <p class="mb-0">0912-345-6789</p>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <i class="fas fa-fire-extinguisher fa-2x text-warning mb-2"></i>
                                        <h6>Fire Department</h6>
                                        <p class="mb-0">0912-345-6790</p>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <i class="fas fa-shield-alt fa-2x text-primary mb-2"></i>
                                        <h6>Police Station</h6>
                                        <p class="mb-0">0912-345-6791</p>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <i class="fas fa-hospital fa-2x text-success mb-2"></i>
                                        <h6>Medical Emergency</h6>
                                        <p class="mb-0">0912-345-6792</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>