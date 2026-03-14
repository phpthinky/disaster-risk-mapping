<?php
// dashboard_barangay.php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Redirect admin users to main dashboard
if ($_SESSION['role'] == 'admin') {
    header('Location: dashboard.php');
    exit;
}

$barangay_id = $_SESSION['barangay_id'];

// Get barangay-specific statistics
$stmt = $pdo->prepare("
    SELECT 
        b.name as barangay_name,
        COALESCE(pd.total_population, b.population) as total_population,
        COALESCE(SUM(hz.affected_population), 0) as at_risk_population,
        COUNT(hz.id) as hazard_count,
        COUNT(CASE WHEN hz.risk_level = 'high' THEN 1 END) as high_risk_count
    FROM barangays b
    LEFT JOIN (
        SELECT barangay_id, MAX(total_population) as total_population 
        FROM population_data 
        GROUP BY barangay_id
    ) as pd ON b.id = pd.barangay_id
    LEFT JOIN hazard_zones hz ON b.id = hz.barangay_id
    WHERE b.id = ?
    GROUP BY b.id, b.name, b.population, pd.total_population
");
$stmt->execute([$barangay_id]);
$barangay_stats = $stmt->fetch();

// Get announcements for this user based on their role and target audience
$announcements_query = "
    SELECT a.*, u.username as created_by_name 
    FROM announcements a 
    LEFT JOIN users u ON a.created_by = u.id 
    WHERE a.is_active = 1 
    AND (
        a.target_audience = 'all' OR
        (a.target_audience = 'barangay_staff' AND ? = 'barangay_staff') OR
        (a.target_audience = 'admin' AND ? = 'admin') OR
        (a.target_audience = 'division_chief' AND ? = 'division_chief')
    )
    ORDER BY 
        CASE a.announcement_type 
            WHEN 'emergency' THEN 1
            WHEN 'maintenance' THEN 2
            WHEN 'info' THEN 3
            ELSE 4
        END,
        a.created_at DESC
    LIMIT 10
";

$announcements_stmt = $pdo->prepare($announcements_query);
$announcements_stmt->execute([$_SESSION['role'], $_SESSION['role'], $_SESSION['role']]);
$announcements = $announcements_stmt->fetchAll();

// Get recent alerts for this barangay
$alerts = $pdo->prepare("
    SELECT a.* 
    FROM alerts a 
    WHERE (a.barangay_id = ? OR a.barangay_id IS NULL) 
    AND a.is_active = 1 
    ORDER BY a.created_at DESC 
    LIMIT 5
");
$alerts->execute([$barangay_id]);
$recent_alerts = $alerts->fetchAll();

// Get recent data entries by this user
$entries = $pdo->prepare("
    SELECT de.* 
    FROM data_entries de 
    WHERE de.user_id = ? 
    ORDER BY de.created_at DESC 
    LIMIT 5
");
$entries->execute([$_SESSION['user_id']]);
$recent_entries = $entries->fetchAll();

// Calculate risk percentage
$risk_percentage = $barangay_stats['total_population'] > 0 ? 
    ($barangay_stats['at_risk_population'] / $barangay_stats['total_population']) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Dashboard - Sablayan Risk Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .stat-card {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s;
            background: white;
            margin-bottom: 20px;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-value {
            font-size: 2.5rem;
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
            transition: transform 0.3s;
        }
        .card:hover {
            transform: translateY(-2px);
        }
        .alert-notification {
            border-left: 4px solid;
            transition: all 0.3s;
        }
        .alert-notification:hover {
            transform: translateX(5px);
        }
        .announcement-card {
            border-left: 4px solid;
            transition: all 0.3s;
            cursor: pointer;
            margin-bottom: 15px;
            border-radius: 8px;
            overflow: hidden;
        }
        .announcement-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .announcement-emergency {
            border-left-color: #dc3545;
            background: linear-gradient(135deg, #fff5f5, #fff);
        }
        .announcement-info {
            border-left-color: #17a2b8;
            background: linear-gradient(135deg, #f0f9ff, #fff);
        }
        .announcement-maintenance {
            border-left-color: #ffc107;
            background: linear-gradient(135deg, #fffdf0, #fff);
        }
        .announcement-general {
            border-left-color: #6c757d;
            background: linear-gradient(135deg, #f8f9fa, #fff);
        }
        .announcement-icon {
            font-size: 1.5rem;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        .emergency-icon {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        .info-icon {
            background-color: rgba(23, 162, 184, 0.1);
            color: #17a2b8;
        }
        .maintenance-icon {
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }
        .general-icon {
            background-color: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }
        .announcement-badge {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .announcement-modal .modal-content {
            border-radius: 15px;
            overflow: hidden;
        }
        .announcement-modal .modal-header {
            border-bottom: none;
            padding-bottom: 0;
        }
        .modal-type-indicator {
            width: 100%;
            height: 5px;
            margin-bottom: 20px;
        }
        .modal-emergency {
            background: linear-gradient(90deg, #dc3545, #e35d6a);
        }
        .modal-info {
            background: linear-gradient(90deg, #17a2b8, #2ab7ca);
        }
        .modal-maintenance {
            background: linear-gradient(90deg, #ffc107, #ffd54f);
        }
        .modal-general {
            background: linear-gradient(90deg, #6c757d, #868e96);
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
                    <h2>Barangay Dashboard - <?php echo $barangay_stats['barangay_name']; ?></h2>
                    <div class="btn-group">
                        <a href="population_data.php" class="btn btn-outline-primary">
                            <i class="fas fa-users me-2"></i>Update Population
                        </a>
                        <a href="hazard_data.php" class="btn btn-outline-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>Report Hazard
                        </a>
                    </div>
                </div>

                <!-- Barangay Statistics -->
                <div class="row mb-4">
                    <!-- Total Population -->
                    <div class="col-md-3 mb-3">
                        <div class="stat-card bg-white p-4 rounded-2xl shadow-lg hover:shadow-2xl transition-shadow duration-300 text-center border border-gray-200">
                            <i class="fas fa-users fa-2x mb-2 text-blue-600"></i>
                            <div class="stat-value text-2xl font-bold text-gray-800">
                                <?php echo number_format($barangay_stats['total_population']); ?>
                            </div>
                            <div class="stat-label text-gray-500">Total Population</div>
                        </div>
                    </div>

                    <!-- Population at Risk -->
                    <div class="col-md-3 mb-3">
                        <div class="stat-card bg-white p-4 rounded-2xl shadow-lg hover:shadow-2xl transition-shadow duration-300 text-center border border-gray-200">
                            <i class="fas fa-exclamation-triangle fa-2x mb-2 text-red-600"></i>
                            <div class="stat-value text-2xl font-bold text-gray-800">
                                <?php echo number_format($barangay_stats['at_risk_population']); ?>
                            </div>
                            <div class="stat-label text-gray-500">Population at Risk</div>
                        </div>
                    </div>

                    <!-- Hazard Zones -->
                    <div class="col-md-3 mb-3">
                        <div class="stat-card bg-white p-4 rounded-2xl shadow-lg hover:shadow-2xl transition-shadow duration-300 text-center border border-gray-200">
                            <i class="fas fa-radiation fa-2x mb-2 text-orange-600"></i>
                            <div class="stat-value text-2xl font-bold text-gray-800">
                                <?php echo $barangay_stats['hazard_count']; ?>
                            </div>
                            <div class="stat-label text-gray-500">Hazard Zones</div>
                        </div>
                    </div>

                    <!-- Risk Percentage -->
                    <div class="col-md-3 mb-3">
                        <div class="stat-card bg-white p-4 rounded-2xl shadow-lg hover:shadow-2xl transition-shadow duration-300 text-center border border-gray-200">
                            <i class="fas fa-percentage fa-2x mb-2 text-green-600"></i>
                            <div class="stat-value text-2xl font-bold text-gray-800">
                                <?php echo number_format($risk_percentage, 1); ?>%
                            </div>
                            <div class="stat-label text-gray-500">Risk Percentage</div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <!-- Announcements Section -->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-bullhorn me-2 text-primary"></i> Latest Announcements
                                </h5>
                                <!--<span class="badge bg-primary">-->
                                <!--    <i class="fas fa-bell me-1"></i> <?php echo count($announcements); ?>-->
                                <!--</span>-->
                            </div>
                            <div class="card-body">
                                <?php if (count($announcements) > 0): ?>
                                    <div id="announcementsContainer">
                                        <?php foreach ($announcements as $announcement): 
                                            $type_class = 'announcement-' . $announcement['announcement_type'];
                                            $icon_class = '';
                                            $modal_class = '';
                                            
                                            switch($announcement['announcement_type']) {
                                                case 'emergency':
                                                    $icon_class = 'emergency-icon';
                                                    $modal_class = 'modal-emergency';
                                                    $icon = 'exclamation-triangle';
                                                    break;
                                                case 'info':
                                                    $icon_class = 'info-icon';
                                                    $modal_class = 'modal-info';
                                                    $icon = 'info-circle';
                                                    break;
                                                case 'maintenance':
                                                    $icon_class = 'maintenance-icon';
                                                    $modal_class = 'modal-maintenance';
                                                    $icon = 'tools';
                                                    break;
                                                default:
                                                    $icon_class = 'general-icon';
                                                    $modal_class = 'modal-general';
                                                    $icon = 'bullhorn';
                                                    break;
                                            }
                                        ?>
                                        <div class="announcement-card" 
                                             data-bs-toggle="modal" 
                                             data-bs-target="#announcementModal"
                                             data-id="<?php echo $announcement['id']; ?>"
                                             data-title="<?php echo htmlspecialchars($announcement['title']); ?>"
                                             data-message="<?php echo htmlspecialchars($announcement['message']); ?>"
                                             data-type="<?php echo $announcement['announcement_type']; ?>"
                                             data-date="<?php echo date('M j, Y g:i A', strtotime($announcement['created_at'])); ?>"
                                             data-author="<?php echo htmlspecialchars($announcement['created_by_name']); ?>"
                                             data-modal-class="<?php echo $modal_class; ?>"
                                             data-icon="<?php echo $icon; ?>"
                                             data-icon-class="<?php echo $icon_class; ?>">
                                            <div class="p-3">
                                                <div class="d-flex align-items-start">
                                                    <div class="announcement-icon <?php echo $icon_class; ?>">
                                                        <i class="fas fa-<?php echo $icon; ?>"></i>
                                                    </div>
                                                    <div class="flex-grow-1">

                                                        <p class="mb-2 text-muted small">
                                                            <?php echo substr(htmlspecialchars($announcement['message']), 0, 100); ?>
                                                            <?php if (strlen($announcement['message']) > 100): ?>... <span class="text-primary">Read more</span><?php endif; ?>
                                                        </p>
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <small class="text-muted">
                                                                <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($announcement['created_by_name']); ?>
                                                            </small>
                                                            <small class="text-muted">
                                                                <i class="fas fa-clock me-1"></i> <?php echo date('M j, g:i A', strtotime($announcement['created_at'])); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <!-- No announcements message -->
                                <?php else: ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-bullhorn fa-3x mb-3 text-muted"></i>
                                        <h5>No Announcements</h5>
                                        <p>There are no announcements at the moment.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if (count($announcements) > 0): ?>
                            <div class="card-footer text-center bg-light">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i> 
                                    Click on any announcement to view full details
                                </small>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Recent Alerts -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-bell me-2"></i> Recent Alerts
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($recent_alerts) > 0): ?>
                                    <?php foreach ($recent_alerts as $alert): ?>
                                        <div class="alert alert-<?php echo $alert['alert_type']; ?> alert-notification mb-3">
                                            <h6><?php echo htmlspecialchars($alert['title']); ?></h6>
                                            <p class="mb-1"><?php echo htmlspecialchars($alert['message']); ?></p>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y g:i A', strtotime($alert['created_at'])); ?>
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-bell-slash fa-2x mb-2"></i>
                                        <p>No recent alerts</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <!-- Quick Actions -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-bolt me-2"></i> Quick Actions
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="population_data.php" class="btn btn-primary">
                                        <i class="fas fa-users me-2"></i>Update Population Data
                                    </a>
                                    <a href="hazard_data.php" class="btn btn-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>Report New Hazard
                                    </a>
                                    <a href="data_entry.php" class="btn btn-info">
                                        <i class="fas fa-edit me-2"></i>Submit General Data
                                    </a>
                                    <a href="barangay_reports.php" class="btn btn-success">
                                        <i class="fas fa-file-pdf me-2"></i>Generate Barangay Report
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Data Entries -->
                        <!-- <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-history me-2"></i> My Recent Entries
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($recent_entries) > 0): ?>
                                    <?php foreach ($recent_entries as $entry): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2 p-2 border rounded">
                                            <div>
                                                <small class="text-muted"><?php echo date('M j, H:i', strtotime($entry['created_at'])); ?></small>
                                                <div class="small"><?php echo substr($entry['description'], 0, 50); ?>...</div>
                                            </div>
                                            <span class="badge bg-<?php 
                                                echo $entry['status'] == 'approved' ? 'success' : 
                                                     ($entry['status'] == 'rejected' ? 'danger' : 'warning'); 
                                            ?>">
                                                <?php echo ucfirst($entry['status']); ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-inbox fa-2x mb-2"></i>
                                        <p>No data entries yet</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div> -->
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Announcement Modal -->
    <div class="modal fade announcement-modal" id="announcementModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-type-indicator" id="modalTypeIndicator"></div>
                <div class="modal-header border-bottom-0 pb-0">
                    <div class="d-flex align-items-center w-100">
                        <div class="announcement-icon" id="modalIcon"></div>
                        <div class="flex-grow-1">
                            <h5 class="modal-title fw-bold" id="modalTitle"></h5>
                            <div class="d-flex align-items-center mt-1">
                                <small class="text-muted me-3">
                                    <i class="fas fa-user me-1"></i> <span id="modalAuthor"></span>
                                </small>
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i> <span id="modalDate"></span>
                                </small>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="modal-body pt-0">
                    <div class="mb-3">
                        <span class="badge me-2" id="modalTypeBadge"></span>
                        <span class="badge bg-secondary" id="modalAudience"></span>
                    </div>
                    <div class="p-3 bg-light rounded">
                        <p class="mb-0" id="modalMessage"></p>
                    </div>
                </div>
                <div class="modal-footer border-top-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="markAsReadBtn">
                        <i class="fas fa-check me-1"></i> Mark as Read
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const announcementModal = new bootstrap.Modal(document.getElementById('announcementModal'));
            const modalTitle = document.getElementById('modalTitle');
            const modalMessage = document.getElementById('modalMessage');
            const modalDate = document.getElementById('modalDate');
            const modalAuthor = document.getElementById('modalAuthor');
            const modalTypeBadge = document.getElementById('modalTypeBadge');
            const modalTypeIndicator = document.getElementById('modalTypeIndicator');
            const modalIcon = document.getElementById('modalIcon');
            const modalAudience = document.getElementById('modalAudience');
            const markAsReadBtn = document.getElementById('markAsReadBtn');
            
            // Store read announcements in localStorage
            let readAnnouncements = JSON.parse(localStorage.getItem('readAnnouncements') || '[]');
            
            // Mark announcements as read visually
            readAnnouncements.forEach(id => {
                const announcement = document.querySelector(`.announcement-card[data-id="${id}"]`);
                if (announcement) {
                    announcement.style.opacity = '0.7';
                    announcement.style.filter = 'grayscale(30%)';
                }
            });
            
            // Announcement card click handler
            document.querySelectorAll('.announcement-card').forEach(card => {
                card.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const title = this.getAttribute('data-title');
                    const message = this.getAttribute('data-message');
                    const type = this.getAttribute('data-type');
                    const date = this.getAttribute('data-date');
                    const author = this.getAttribute('data-author');
                    const modalClass = this.getAttribute('data-modal-class');
                    const iconClass = this.getAttribute('data-icon-class');
                    const icon = this.getAttribute('data-icon');
                    
                    // Set modal content
                    modalTitle.textContent = title;
                    modalMessage.textContent = message;
                    modalDate.textContent = date;
                    modalAuthor.textContent = author;
                    
                    // Set type badge
                    const typeColors = {
                        'emergency': 'danger',
                        'info': 'info',
                        'maintenance': 'warning',
                        'general': 'secondary'
                    };
                    
                    modalTypeBadge.textContent = type.charAt(0).toUpperCase() + type.slice(1);
                    modalTypeBadge.className = `badge bg-${typeColors[type] || 'secondary'}`;
                    
                    // Set type indicator
                    modalTypeIndicator.className = 'modal-type-indicator ' + modalClass;
                    
                    // Set icon
                    modalIcon.innerHTML = `<i class="fas fa-${icon} fa-2x"></i>`;
                    modalIcon.className = `announcement-icon ${iconClass}`;
                    
                    // Set audience (simplified - you might want to fetch this from data attribute)
                    const audienceMap = {
                        'all': 'All Users',
                        'barangay_staff': 'Barangay Staff',
                        'division_chief': 'Division Chiefs',
                        'admin': 'Administrators'
                    };
                    modalAudience.textContent = audienceMap['barangay_staff']; // Default for barangay staff
                    
                    // Mark as read button handler
                    markAsReadBtn.onclick = function() {
                        if (!readAnnouncements.includes(id)) {
                            readAnnouncements.push(id);
                            localStorage.setItem('readAnnouncements', JSON.stringify(readAnnouncements));
                            
                            // Update card appearance
                            card.style.opacity = '0.7';
                            card.style.filter = 'grayscale(30%)';
                            
                            // Show confirmation
                            const originalText = markAsReadBtn.innerHTML;
                            markAsReadBtn.innerHTML = '<i class="fas fa-check me-1"></i> Marked as Read';
                            markAsReadBtn.disabled = true;
                            
                            setTimeout(() => {
                                markAsReadBtn.innerHTML = originalText;
                                markAsReadBtn.disabled = false;
                                announcementModal.hide();
                            }, 1500);
                        }
                    };
                    
                    // Show modal
                    announcementModal.show();
                });
            });
            
            // Auto-refresh announcements every 5 minutes
            setInterval(() => {
                // In a real application, you would make an AJAX request here
                console.log('Time to refresh announcements...');
                // location.reload(); // Simple refresh, or use AJAX for better UX
            }, 300000); // 5 minutes
        });
    </script>
</body>
</html>