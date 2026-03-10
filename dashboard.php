<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// dashboard.php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
} else{
    $user_id = $_SESSION['user_id'];
}

// Redirect barangay staff to their dashboard
if ($_SESSION['role'] == 'barangay_staff') {
    header('Location: dashboard_barangay.php');
    exit;
}

// Redirect barangay staff to their dashboard
if ($_SESSION['role'] == 'division_chief') {
    header('Location: dashboard_division.php');
    exit;
}

// Get dashboard statistics from database
// Total Population (latest data for each barangay)
$stmt = $pdo->query("
    SELECT SUM(latest_population) as total_population 
    FROM (
        SELECT barangay_id, MAX(total_population) as latest_population 
        FROM population_data 
        GROUP BY barangay_id
    ) as latest_data
");
$totalPopulation = $stmt->fetch()['total_population'] ?? 0;

// Population at Risk (sum of affected population from all hazard zones)
// Population at Risk (avoid double counting per barangay)
$stmt = $pdo->query("
    SELECT SUM(max_affected) as total_at_risk
    FROM (
        SELECT barangay_id, MAX(affected_population) as max_affected
        FROM hazard_zones
        GROUP BY barangay_id
    ) as per_barangay
");
$atRiskPopulation = $stmt->fetch()['total_at_risk'] ?? 0;


// Active Hazards (count of all hazard zones)
$stmt = $pdo->query("SELECT COUNT(*) as total FROM hazard_zones");
$activeHazards = $stmt->fetch()['total'];

// Total Barangays
$stmt = $pdo->query("SELECT COUNT(*) as total FROM barangays");
$totalBarangays = $stmt->fetch()['total'];

// Get recent alerts
$alerts = $pdo->query("SELECT a.*, b.name as barangay_name FROM alerts a 
                       LEFT JOIN barangays b ON a.barangay_id = b.id 
                       WHERE a.is_active = 1 ORDER BY a.created_at DESC LIMIT 5")->fetchAll();

// Get risk distribution data
$riskDistribution = $pdo->query("
    SELECT 
        risk_level,
        SUM(affected_population) as total_affected
    FROM hazard_zones 
    GROUP BY risk_level
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Initialize risk counts
$highRisk = $riskDistribution['high'] ?? 0;
$mediumRisk = $riskDistribution['medium'] ?? 0;
$lowRisk = $riskDistribution['low'] ?? 0;

// Calculate percentages for progress bars
$totalRiskPopulation = $highRisk + $mediumRisk + $lowRisk;
$highRiskPercent = $totalRiskPopulation > 0 ? ($highRisk / $totalRiskPopulation) * 100 : 0;
$mediumRiskPercent = $totalRiskPopulation > 0 ? ($mediumRisk / $totalRiskPopulation) * 100 : 0;
$lowRiskPercent = $totalRiskPopulation > 0 ? ($lowRisk / $totalRiskPopulation) * 100 : 0;

// Check if data needs update (data older than 30 days)
$stmt = $pdo->query("
    SELECT COUNT(*) as outdated_count 
    FROM population_data 
    WHERE data_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
");
$outdatedCount = $stmt->fetch()['outdated_count'];
$dataNeedsUpdate = $outdatedCount > 0;

// Get barangays for map markers with coordinates
$barangays = $pdo->query("
    SELECT b.*, 
           COALESCE(SUM(hz.affected_population), 0) as total_affected,
           MAX(hz.risk_level) as highest_risk
    FROM barangays b
    LEFT JOIN hazard_zones hz ON b.id = hz.barangay_id
    GROUP BY b.id, b.name, b.coordinates
")->fetchAll();

// Get hazard zones for map
$hazardZones = $pdo->query("
    SELECT hz.*, ht.name as hazard_name, ht.color, b.name as barangay_name 
    FROM hazard_zones hz
    JOIN hazard_types ht ON hz.hazard_type_id = ht.id
    JOIN barangays b ON hz.barangay_id = b.id
")->fetchAll();

// Get active announcements (latest 5)
$stmt = $pdo->prepare("
    SELECT *
    FROM announcements
    WHERE is_active = 1 AND created_by != '$user_id'
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute();
$announcements = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sablayan Risk Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --success-color: #27ae60;
            --warning-color: #f39c12;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }
        
        .stat-card {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s;
            background: white;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 0;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            font-weight: 500;
        }
        
        #map-preview {
            height: 300px;
            width: 100%;
            border-radius: 8px;
        }
        
        .data-status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .status-outdated { 
            background-color: #f8d7da; 
            color: #721c24; 
        }
        
        .status-up-to-date { 
            background-color: #d4edda; 
            color: #155724; 
        }
        
        .risk-high { color: #e74c3c; font-weight: bold; }
        .risk-medium { color: #f39c12; font-weight: bold; }
        .risk-low { color: #27ae60; font-weight: bold; }
        
        .progress {
            height: 8px;
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
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid #eee;
            font-weight: 600;
            padding: 15px 20px;
        }
        
        .alert-notification {
            border-left: 4px solid;
        }
        
        .alert-warning {
            border-left-color: var(--warning-color);
        }
        
        .alert-info {
            border-left-color: var(--secondary-color);
        }
        
        .alert-danger {
            border-left-color: var(--accent-color);
        }
        
        .main-content {
            padding: 20px;
            height: calc(100vh - 56px);
            overflow-y: auto;
        }
        
        /* Announcement Card Enhancements */
        .announcement-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .announcement-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .announcement-item {
            transition: all 0.3s ease;
            background-color: #fdfdfd;
        }
        
        .announcement-item:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }
        
        .announcement-message {
            transition: all 0.3s ease;
        }

    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-4 border-bottom">
                    <h2>Risk Assessment Dashboard</h2>
                    <div>
                        <span class="data-status <?php echo $dataNeedsUpdate ? 'status-outdated' : 'status-up-to-date'; ?> me-3">
                            <i class="fas <?php echo $dataNeedsUpdate ? 'fa-exclamation-circle' : 'fa-check-circle'; ?> me-1"></i> 
                            <?php echo $dataNeedsUpdate ? 'Data needs update' : 'Data up to date'; ?>
                        </span>
                        <button class="btn btn-primary" id="updateDataBtn">
                            <i class="fas fa-sync-alt me-1"></i> Update Data
                        </button>
                    </div>
                </div>
                
<!-- Include Font Awesome (for icons) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- Stats Cards -->
<div class="row mb-4">
    <!-- Total Population -->
    <div class="col-md-4 mb-3">
        <div class="stat-card shadow-sm d-flex align-items-center justify-content-between p-4 rounded-4 bg-light hover-card">
            <div>
                <h3 class="stat-value mb-1 text-primary fw-bold">
                    <?php echo number_format($totalPopulation); ?>
                </h3>
                <p class="stat-label mb-0 text-secondary">Total Population</p>
            </div>
            <div class="icon-box bg-primary text-white rounded-circle d-flex align-items-center justify-content-center">
                <i class="fas fa-users fa-lg"></i>
            </div>
        </div>
    </div>

    <!-- Active Hazards -->
    <div class="col-md-4 mb-3">
        <div class="stat-card shadow-sm d-flex align-items-center justify-content-between p-4 rounded-4 bg-light hover-card">
            <div>
                <h3 class="stat-value mb-1 text-danger fw-bold">
                    <?php echo $activeHazards; ?>
                </h3>
                <p class="stat-label mb-0 text-secondary">Active Hazards</p>
            </div>
            <div class="icon-box bg-danger text-white rounded-circle d-flex align-items-center justify-content-center">
                <i class="fas fa-exclamation-triangle fa-lg"></i>
            </div>
        </div>
    </div>

    <!-- Barangays -->
    <div class="col-md-4 mb-3">
        <div class="stat-card shadow-sm d-flex align-items-center justify-content-between p-4 rounded-4 bg-light hover-card">
            <div>
                <h3 class="stat-value mb-1 text-success fw-bold">
                    <?php echo $totalBarangays; ?>
                </h3>
                <p class="stat-label mb-0 text-secondary">Barangays</p>
            </div>
            <div class="icon-box bg-success text-white rounded-circle d-flex align-items-center justify-content-center">
                <i class="fas fa-map-marker-alt fa-lg"></i>
            </div>
        </div>
    </div>
</div>

<!-- Add interactive CSS -->
<style>
.stat-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}
.icon-box {
    width: 50px;
    height: 50px;
}
.hover-card:hover .icon-box {
    transform: scale(1.1);
    transition: transform 0.3s ease;
}
.stat-value {
    font-size: 1.8rem;
}
</style>

                
                <!-- Map Preview -->
                <!--<div class="card mb-4">-->
                <!--    <div class="card-header d-flex justify-content-between align-items-center">-->
                <!--        <span>Risk Map Overview</span>-->
                <!--        <a href="map_view.php" class="btn btn-sm btn-primary">View Full Map</a>-->
                <!--    </div>-->
                <!--    <div class="card-body">-->
                <!--        <div id="map-preview"></div>-->
                <!--    </div>-->
                <!--</div>-->
                
                <!-- Recent Alerts and Announcements -->
                <div class="row">
                
                <!-- Announcements -->
                <div class="col-md-6">
                    <div class="card announcement-card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-bullhorn me-2 text-primary"></i> Announcements</span>
                            <span class="badge bg-secondary"><?php echo count($announcements); ?></span>
                        </div>
                        <div class="card-body">
                
                            <?php if (count($announcements) > 0): ?>
                                <?php foreach ($announcements as $announcement): 
                
                                    // Determine style based on type
                                    $type = strtolower($announcement['announcement_type']);
                
                                    if ($type == 'urgent') {
                                        $borderClass = 'border-danger';
                                        $icon = 'fa-triangle-exclamation';
                                        $badgeClass = 'bg-danger';
                                    } elseif ($type == 'important') {
                                        $borderClass = 'border-warning';
                                        $icon = 'fa-star';
                                        $badgeClass = 'bg-warning text-dark';
                                    } else {
                                        $borderClass = 'border-primary';
                                        $icon = 'fa-circle-info';
                                        $badgeClass = 'bg-primary';
                                    }
                
                                    $announcementId = "announcement_" . $announcement['id'];
                                ?>
                
                                <div class="announcement-item border-start border-4 <?php echo $borderClass; ?> p-3 mb-3 rounded shadow-sm">
                
                                    <div class="d-flex justify-content-between align-items-start">
                                        <h6 class="mb-1 fw-bold">
                                            <i class="fas <?php echo $icon; ?> me-2"></i>
                                            <?php echo htmlspecialchars($announcement['title']); ?>
                                        </h6>
                
                                        <span class="badge <?php echo $badgeClass; ?>">
                                            <?php echo ucfirst($type); ?>
                                        </span>
                                    </div>
                
                                    <!-- Collapsible Message -->
                                    <p class="text-muted mb-2 small announcement-message collapse" id="<?php echo $announcementId; ?>">
                                        <?php echo nl2br(htmlspecialchars($announcement['message'])); ?>
                                    </p>
                
                                    <div class="d-flex justify-content-between align-items-center">
                                        <button class="btn btn-sm btn-secondary"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#<?php echo $announcementId; ?>">
                                            <i class="fas fa-chevron-down me-1"></i> Read More
                                        </button>
                
                                        <small class="text-muted">
                                            <i class="fas fa-users me-1"></i>
                                            <?php echo htmlspecialchars($announcement['target_audience']); ?>
                                            • 
                                            <?php echo date('M j, Y g:i A', strtotime($announcement['created_at'])); ?>
                                        </small>
                                    </div>
                
                                </div>
                
                                <?php endforeach; ?>
                            <?php else: ?>
                
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-bullhorn fa-3x mb-3 text-secondary"></i>
                                    <p>No active announcements</p>
                                </div>
                
                            <?php endif; ?>
                
                        </div>
                    </div>
                </div>


                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-chart-line me-2"></i> Risk Statistics
                            </div>
                            <div class="card-body">
                                <h6>Population Distribution by Risk Level</h6>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>High Risk</span>
                                        <span class="risk-high"><?php echo number_format($highRisk); ?></span>
                                    </div>
                                    <div class="progress mb-2">
                                        <div class="progress-bar bg-danger" style="width: <?php echo $highRiskPercent; ?>%"></div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Medium Risk</span>
                                        <span class="risk-medium"><?php echo number_format($mediumRisk); ?></span>
                                    </div>
                                    <div class="progress mb-2">
                                        <div class="progress-bar bg-warning" style="width: <?php echo $mediumRiskPercent; ?>%"></div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Low Risk</span>
                                        <span class="risk-low"><?php echo number_format($lowRisk); ?></span>
                                    </div>
                                    <div class="progress mb-2">
                                        <div class="progress-bar bg-success" style="width: <?php echo $lowRiskPercent; ?>%"></div>
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
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
// Initialize the preview map with real data
function initMapPreview() {
    // Coordinates for Sablayan, Occidental Mindoro
    const sablayanCoords = [12.8333, 120.7667];
    
    // Initialize the preview map
    const previewMap = L.map('map-preview').setView(sablayanCoords, 11);
    
    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(previewMap);
    
    // Add barangay markers from database data
    const barangays = <?php echo json_encode($barangays); ?>;
    
    barangays.forEach(barangay => {
        // Use actual coordinates from database
        let lat, lng;
        if (barangay.coordinates) {
            const coords = barangay.coordinates.split(',');
            lat = parseFloat(coords[0].trim());
            lng = parseFloat(coords[1].trim());
        } else {
            // Fallback to approximate coordinates if none provided
            lat = 12.8333 + (Math.random() - 0.5) * 0.1;
            lng = 120.7667 + (Math.random() - 0.5) * 0.1;
        }
        
        let color = 'green';
        let riskLevel = 'low';
        
        if (barangay.highest_risk === 'high') {
            color = 'red';
            riskLevel = 'high';
        } else if (barangay.highest_risk === 'medium') {
            color = 'orange';
            riskLevel = 'medium';
        }
        
        const marker = L.circleMarker([lat, lng], {
            radius: 8 + (barangay.total_affected / 500), // Size based on affected population
            fillColor: color,
            color: '#000',
            weight: 1,
            opacity: 1,
            fillOpacity: 0.8
        }).addTo(previewMap);
        
        marker.bindPopup(`
            <strong>${barangay.name}</strong><br>
            Population: ${barangay.population?.toLocaleString() || 'N/A'}<br>
            Affected: ${barangay.total_affected?.toLocaleString() || 0}<br>
            Risk Level: <span class="risk-${riskLevel}">${riskLevel}</span>
        `);
        
        marker.bindTooltip(barangay.name);
    });
    
    // Add hazard zones if any
    const hazardZones = <?php echo json_encode($hazardZones); ?>;
    
    hazardZones.forEach(hazard => {
        // Use actual coordinates from database
        let lat, lng;
        if (hazard.coordinates) {
            const coords = hazard.coordinates.split(',');
            lat = parseFloat(coords[0].trim());
            lng = parseFloat(coords[1].trim());
        } else {
            // Fallback to barangay coordinates if hazard coordinates not available
            const barangay = barangays.find(b => b.name === hazard.barangay_name);
            if (barangay && barangay.coordinates) {
                const barangayCoords = barangay.coordinates.split(',');
                lat = parseFloat(barangayCoords[0].trim());
                lng = parseFloat(barangayCoords[1].trim());
            } else {
                // Final fallback to random coordinates
                lat = 12.8333 + (Math.random() - 0.5) * 0.1;
                lng = 120.7667 + (Math.random() - 0.5) * 0.1;
            }
        }
        
        const circle = L.circle([lat, lng], {
            color: hazard.color,
            fillColor: hazard.color,
            fillOpacity: 0.3,
            radius: hazard.area_km2 * 100 // Convert km² to meters for radius
        }).addTo(previewMap);
        
        circle.bindPopup(`
            <strong>${hazard.hazard_name}</strong><br>
            Barangay: ${hazard.barangay_name}<br>
            Risk Level: ${hazard.risk_level}<br>
            Area: ${hazard.area_km2} km²<br>
            Affected: ${hazard.affected_population} people
        `);
    });
}
        
        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initMapPreview();
            
            // Add interactive functionality for update button
            const updateButton = document.getElementById('updateDataBtn');
            updateButton.addEventListener('click', function() {
                // Show loading state
                const originalText = updateButton.innerHTML;
                updateButton.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Updating...';
                updateButton.disabled = true;
                
                // Simulate API call to update data
                setTimeout(() => {
                    // In production, this would be an actual API call
                    fetch('update_data.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update status indicator
                            const statusElement = document.querySelector('.data-status');
                            statusElement.innerHTML = '<i class="fas fa-check-circle me-1"></i> Data up to date';
                            statusElement.className = 'data-status status-up-to-date';
                            
                            // Show success message
                            alert('Data updated successfully!');
                            
                            // Reload page to show updated data
                            setTimeout(() => {
                                location.reload();
                            }, 1000);
                        }
                    })
                    .catch(error => {
                        console.error('Error updating data:', error);
                        alert('Error updating data. Please try again.');
                    })
                    .finally(() => {
                        // Restore button state
                        updateButton.innerHTML = originalText;
                        updateButton.disabled = false;
                    });
                }, 1500);
            });
            
            // Make cards interactive
            const cards = document.querySelectorAll('.card, .stat-card');
            cards.forEach(card => {
                card.addEventListener('click', function() {
                    if (this.querySelector('a')) {
                        this.querySelector('a').click();
                    }
                });
            });
        });
    </script>
</body>
</html>