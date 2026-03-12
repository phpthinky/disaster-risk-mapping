<?php
// dashboard.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
require_once 'core/core.php';

// Authentication and role checks
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Role-based redirects
if ($_SESSION['role'] == 'barangay_staff') {
    header('Location: dashboard_barangay.php');
    exit;
}

if ($_SESSION['role'] == 'division_chief') {
    header('Location: dashboard_division.php');
    exit;
}

// Get dashboard statistics
// Total Population
$stmt = $pdo->query("
    SELECT SUM(latest_population) as total_population 
    FROM (
        SELECT barangay_id, MAX(total_population) as latest_population 
        FROM population_data 
        GROUP BY barangay_id
    ) as latest_data
");
$totalPopulation = $stmt->fetch()['total_population'] ?? 0;

// Population at Risk
$stmt = $pdo->query("
    SELECT SUM(max_affected) as total_at_risk
    FROM (
        SELECT barangay_id, MAX(affected_population) as max_affected
        FROM hazard_zones
        GROUP BY barangay_id
    ) as per_barangay
");
$atRiskPopulation = $stmt->fetch()['total_at_risk'] ?? 0;

// Active Hazards
$stmt = $pdo->query("SELECT COUNT(*) as total FROM hazard_zones");
$activeHazards = $stmt->fetch()['total'];

// Total Barangays
$stmt = $pdo->query("SELECT COUNT(*) as total FROM barangays");
$totalBarangays = $stmt->fetch()['total'];

// Risk Distribution
$riskDistribution = $pdo->query("
    SELECT 
        risk_level,
        SUM(affected_population) as total_affected
    FROM hazard_zones 
    GROUP BY risk_level
")->fetchAll(PDO::FETCH_KEY_PAIR);

$highRisk = $riskDistribution['high'] ?? 0;
$mediumRisk = $riskDistribution['medium'] ?? 0;
$lowRisk = $riskDistribution['low'] ?? 0;

// Calculate percentages - FIX DIVISION BY ZERO ERROR
$totalRiskPopulation = $highRisk + $mediumRisk + $lowRisk;
$highRiskPercent = $totalRiskPopulation > 0 ? round(($highRisk / $totalRiskPopulation) * 100, 1) : 0;
$mediumRiskPercent = $totalRiskPopulation > 0 ? round(($mediumRisk / $totalRiskPopulation) * 100, 1) : 0;
$lowRiskPercent = $totalRiskPopulation > 0 ? round(($lowRisk / $totalRiskPopulation) * 100, 1) : 0;

// Calculate at risk percentage for progress bar
$atRiskPercent = $totalPopulation > 0 ? round(($atRiskPopulation / $totalPopulation) * 100) : 0;

// Get announcements
$stmt = $pdo->prepare("
    SELECT *
    FROM announcements
    WHERE is_active = 1 AND created_by != ?
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$announcements = $stmt->fetchAll();

// Get recent activities


// Helper function for time elapsed
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

// Set page title
$page_title = "Dashboard";
?>

<!-- Include Header -->
<?php include BASE_PATH.'includes/header.php'; ?>

<!-- Include Navbar -->
<?php include BASE_PATH.'includes/navbar.php'; ?>

<!-- Include Sidebar -->
<?php include BASE_PATH.'includes/sidebar.php'; ?>

<!-- Main Content Wrapper -->
<div class="main-wrapper" id="mainWrapper">
    <div class="content-wrapper">
        <div class="container-fluid px-4 py-4">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
                <div>
                    <h4 class="mb-1 fw-bold">Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>! 👋</h4>
                    <p class="text-muted mb-0">Here's what's happening with your risk assessment system today.</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" onclick="window.location.href='reports.php'">
                        <i class="fas fa-download me-2"></i>Export Report
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#quickActionModal">
                        <i class="fas fa-plus me-2"></i>Quick Action
                    </button>
                </div>
            </div>

            <!-- Stats Cards Row -->
            <div class="row g-4 mb-4">
                <!-- Total Population Card -->
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card slide-in" style="animation-delay: 0.1s;">
                        <div class="d-flex justify-content-between">
                            <div>
                                <p class="stats-label">Total Population</p>
                                <h3 class="stats-value"><?php echo number_format($totalPopulation); ?></h3>
                                <p class="stats-trend positive">
                                    <i class="fas fa-arrow-up me-1"></i>2.5% from last month
                                </p>
                            </div>
                            <div class="stats-icon bg-primary bg-opacity-10">
                                <i class="fas fa-users text-primary"></i>
                            </div>
                        </div>
                        <div class="stats-progress mt-3">
                            <div class="progress" style="height: 4px;">
                                <div class="progress-bar bg-primary" style="width: 75%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- At Risk Population Card -->
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card slide-in" style="animation-delay: 0.2s;">
                        <div class="d-flex justify-content-between">
                            <div>
                                <p class="stats-label">Population at Risk</p>
                                <h3 class="stats-value text-danger"><?php echo number_format($atRiskPopulation); ?></h3>
                                <p class="stats-trend negative">
                                    <i class="fas fa-arrow-down me-1"></i><?php echo $atRiskPercent; ?>% of total
                                </p>
                            </div>
                            <div class="stats-icon bg-danger bg-opacity-10">
                                <i class="fas fa-exclamation-triangle text-danger"></i>
                            </div>
                        </div>
                        <div class="stats-progress mt-3">
                            <div class="progress" style="height: 4px;">
                                <div class="progress-bar bg-danger" style="width: <?php echo $atRiskPercent; ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Active Hazards Card -->
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card slide-in" style="animation-delay: 0.3s;">
                        <div class="d-flex justify-content-between">
                            <div>
                                <p class="stats-label">Active Hazards</p>
                                <h3 class="stats-value text-warning"><?php echo $activeHazards; ?></h3>
                                <p class="stats-trend positive">
                                    <i class="fas fa-plus me-1"></i>3 new this week
                                </p>
                            </div>
                            <div class="stats-icon bg-warning bg-opacity-10">
                                <i class="fas fa-map-marked-alt text-warning"></i>
                            </div>
                        </div>
                        <div class="stats-progress mt-3">
                            <div class="progress" style="height: 4px;">
                                <div class="progress-bar bg-warning" style="width: 60%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Barangays Card -->
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card slide-in" style="animation-delay: 0.4s;">
                        <div class="d-flex justify-content-between">
                            <div>
                                <p class="stats-label">Total Barangays</p>
                                <h3 class="stats-value text-success"><?php echo $totalBarangays; ?></h3>
                                <p class="stats-trend">
                                    <i class="fas fa-check-circle me-1 text-success"></i>All active
                                </p>
                            </div>
                            <div class="stats-icon bg-success bg-opacity-10">
                                <i class="fas fa-city text-success"></i>
                            </div>
                        </div>
                        <div class="stats-progress mt-3">
                            <div class="progress" style="height: 4px;">
                                <div class="progress-bar bg-success" style="width: 100%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts and Map Row -->
            <div class="row g-4 mb-4">
                <!-- Risk Distribution Chart -->
                <div class="col-xl-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Risk Distribution Overview</h5>
                            <small class="text-muted">Population affected by risk level</small>
                        </div>
                        <div class="card-body">
                            <canvas id="riskChart" height="300"></canvas>
                        </div>
                        <div class="card-footer bg-transparent">
                            <div class="row g-2">
                                <div class="col-4">
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-danger me-2" style="width: 12px; height: 12px; padding: 0;"></span>
                                        <small>High Risk: <?php echo number_format($highRisk); ?></small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-warning me-2" style="width: 12px; height: 12px; padding: 0;"></span>
                                        <small>Medium Risk: <?php echo number_format($mediumRisk); ?></small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-success me-2" style="width: 12px; height: 12px; padding: 0;"></span>
                                        <small>Low Risk: <?php echo number_format($lowRisk); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Map Preview -->
                <div class="col-xl-6">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title mb-0">Risk Map Preview</h5>
                                <small class="text-muted">Last updated: <?php echo date('F j, Y'); ?></small>
                            </div>
                            <a href="map_view.php" class="btn btn-sm btn-outline-primary">View Full Map</a>
                        </div>
                        <div class="card-body p-0">
                            <div id="map-preview" style="height: 300px;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Announcements and Activity Row -->
            <div class="row g-4">
                <!-- Announcements Column -->
                <div class="col-xl-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-bullhorn me-2 text-primary"></i>Recent Announcements
                                </h5>
                            </div>
                            <a href="announcements.php" class="btn btn-sm btn-link">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php if (!empty($announcements)): ?>
                                    <?php foreach ($announcements as $index => $announcement): 
                                        $typeClass = match(strtolower($announcement['announcement_type'] ?? 'info')) {
                                            'urgent' => 'danger',
                                            'important' => 'warning',
                                            default => 'info'
                                        };
                                    ?>
                                        <div class="list-group-item announcement-item border-0 border-bottom py-3">
                                            <div class="d-flex">
                                                <div class="announcement-icon me-3">
                                                    <div class="rounded-circle p-2 bg-<?php echo $typeClass; ?> bg-opacity-10">
                                                        <i class="fas fa-<?php echo $typeClass == 'danger' ? 'exclamation-circle' : ($typeClass == 'warning' ? 'star' : 'info-circle'); ?> text-<?php echo $typeClass; ?>"></i>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1 fw-semibold">
                                                        <?php echo htmlspecialchars($announcement['title'] ?? 'Untitled'); ?>
                                                        <span class="badge bg-<?php echo $typeClass; ?> ms-2" style="font-size: 0.6rem;">
                                                            <?php echo ucfirst($announcement['announcement_type'] ?? 'Info'); ?>
                                                        </span>
                                                    </h6>
                                                    <p class="mb-1 small text-muted announcement-preview">
                                                        <?php echo substr(htmlspecialchars($announcement['message'] ?? ''), 0, 100); ?>...
                                                    </p>
                                                    <small class="text-muted">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?php echo date('M j, Y g:i A', strtotime($announcement['created_at'] ?? date('Y-m-d'))); ?>
                                                    </small>
                                                </div>
                                                <button class="btn btn-sm btn-link" data-bs-toggle="collapse" data-bs-target="#announcement-<?php echo $announcement['id'] ?? $index; ?>">
                                                    <i class="fas fa-chevron-down"></i>
                                                </button>
                                            </div>
                                            <div class="collapse mt-2" id="announcement-<?php echo $announcement['id'] ?? $index; ?>">
                                                <div class="p-3 bg-light rounded">
                                                    <?php echo nl2br(htmlspecialchars($announcement['message'] ?? '')); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-bullhorn fa-3x text-muted mb-3"></i>
                                        <h6>No Announcements</h6>
                                        <p class="text-muted small">There are no active announcements at the moment.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Include Footer -->
    <?php include 'footer.php'; ?>
</div>

<!-- Quick Action Modal -->
<div class="modal fade" id="quickActionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Quick Actions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-grid gap-2">
                    <a href="add_hazard.php" class="btn btn-outline-danger text-start">
                        <i class="fas fa-exclamation-triangle me-2"></i>Report New Hazard
                    </a>
                    <a href="update_population.php" class="btn btn-outline-primary text-start">
                        <i class="fas fa-users me-2"></i>Update Population Data
                    </a>
                    <a href="create_announcement.php" class="btn btn-outline-warning text-start">
                        <i class="fas fa-bullhorn me-2"></i>Create Announcement
                    </a>
                    <a href="generate_report.php" class="btn btn-outline-success text-start">
                        <i class="fas fa-file-alt me-2"></i>Generate Report
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Risk Distribution Chart
    const ctx = document.getElementById('riskChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['High Risk', 'Medium Risk', 'Low Risk'],
            datasets: [{
                data: [<?php echo $highRisk; ?>, <?php echo $mediumRisk; ?>, <?php echo $lowRisk; ?>],
                backgroundColor: ['#ef4444', '#f59e0b', '#10b981'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            cutout: '70%'
        }
    });
});

function refreshActivity() {
    location.reload();
}
</script>