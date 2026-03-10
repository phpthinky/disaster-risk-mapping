<?php
// risk_analysis.php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get risk analysis data with proper calculations
$risk_data = $pdo->query("
    SELECT 
        b.id,
        b.name as barangay_name,
        b.population as total_population,
        COALESCE(SUM(hz.affected_population), 0) as total_affected,
        COUNT(hz.id) as hazard_count,
        MAX(CASE WHEN hz.risk_level = 'high' THEN 1 ELSE 0 END) as has_high_risk,
        GROUP_CONCAT(DISTINCT hz.risk_level) as risk_levels
    FROM barangays b
    LEFT JOIN hazard_zones hz ON b.id = hz.barangay_id
    GROUP BY b.id, b.name, b.population
")->fetchAll();

// Calculate overall risk level (percentage of total population at risk)
$totalPopulation = 0;
$totalAtRisk = 0;

foreach ($risk_data as $data) {
    $totalPopulation += $data['total_population'] ?? 0;
    $totalAtRisk += $data['total_affected'] ?? 0;
}

// Calculate overall risk percentage (0-100%)
$overallRiskPercentage = $totalPopulation > 0 ? ($totalAtRisk / $totalPopulation) * 100 : 0;

// Count high risk barangays (barangays with high risk hazards)
$highRiskBarangays = $pdo->query("
    SELECT COUNT(DISTINCT barangay_id) as count 
    FROM hazard_zones 
    WHERE risk_level = 'high'
")->fetch()['count'];

// Calculate total high risk area
$highRiskArea = $pdo->query("
    SELECT COALESCE(SUM(area_km2), 0) as total_area 
    FROM hazard_zones 
    WHERE risk_level = 'high'
")->fetch()['total_area'];

// Calculate total people in high risk
$peopleInHighRisk = $pdo->query("
    SELECT COALESCE(SUM(affected_population), 0) as total 
    FROM hazard_zones 
    WHERE risk_level = 'high'
")->fetch()['total'];

// Get high risk areas with details
$high_risk_areas = $pdo->query("
    SELECT hz.*, ht.name as hazard_name, b.name as barangay_name 
    FROM hazard_zones hz
    JOIN hazard_types ht ON hz.hazard_type_id = ht.id
    JOIN barangays b ON hz.barangay_id = b.id
    WHERE hz.risk_level = 'high'
    ORDER BY hz.affected_population DESC
")->fetchAll();

// Calculate risk distribution by level
$riskDistribution = $pdo->query("
    SELECT 
        risk_level,
        SUM(affected_population) as total_affected
    FROM hazard_zones 
    GROUP BY risk_level
");

$highRisk = 0;
$mediumRisk = 0;
$lowRisk = 0;

while ($row = $riskDistribution->fetch(PDO::FETCH_ASSOC)) {
    switch ($row['risk_level']) {
        case 'high':
            $highRisk = $row['total_affected'];
            break;
        case 'medium':
            $mediumRisk = $row['total_affected'];
            break;
        case 'low':
            $lowRisk = $row['total_affected'];
            break;
    }
}

// Calculate percentages for progress bars
$totalRiskPopulation = $highRisk + $mediumRisk + $lowRisk;
$highRiskPercent = $totalRiskPopulation > 0 ? ($highRisk / $totalRiskPopulation) * 100 : 0;
$mediumRiskPercent = $totalRiskPopulation > 0 ? ($mediumRisk / $totalRiskPopulation) * 100 : 0;
$lowRiskPercent = $totalRiskPopulation > 0 ? ($lowRisk / $totalRiskPopulation) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Risk Analysis - Sablayan Risk Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .stat-card {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            background: white;
            margin-bottom: 20px;
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
                    <h1 class="h2">Risk Analysis & Assessment</h1>
                </div>

                                <!-- Risk Analysis Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card text-center p-3 shadow-card border-0">
                            <div class="card-body">
                                <i class="fas fa-exclamation-triangle fa-2x mb-2" style="color: #e74c3c;"></i>
                                <h3 class="fw-bold text-danger mb-1"><?php echo number_format($overallRiskPercentage, 1); ?>%</h3>
                                <p class="text-secondary fw-semibold mb-0">Overall Risk Level</p>
                            </div>
                        </div>
                    </div>
                
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card text-center p-3 shadow-card border-0">
                            <div class="card-body">
                                <i class="fas fa-map-marker-alt fa-2x mb-2" style="color: #f39c12;"></i>
                                <h3 class="fw-bold text-warning mb-1"><?php echo $highRiskBarangays; ?></h3>
                                <p class="text-secondary fw-semibold mb-0">High Risk Barangays</p>
                            </div>
                        </div>
                    </div>
                
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card text-center p-3 shadow-card border-0">
                            <div class="card-body">
                                <i class="fas fa-ruler-combined fa-2x mb-2" style="color: #3498db;"></i>
                                <h3 class="fw-bold text-primary mb-1"><?php echo number_format($highRiskArea, 1); ?></h3>
                                <p class="text-secondary fw-semibold mb-0">Km² High Risk Area</p>
                            </div>
                        </div>
                    </div>
                
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card text-center p-3 shadow-card border-0">
                            <div class="card-body">
                                <i class="fas fa-users fa-2x mb-2" style="color: #2ecc71;"></i>
                                <h3 class="fw-bold text-success mb-1"><?php echo number_format($peopleInHighRisk); ?></h3>
                                <p class="text-secondary fw-semibold mb-0">People in High Risk</p>
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
                    box-shadow: 0 6px 18px rgba(0,0,0,0.15);
                    transition: all 0.3s ease;
                }
                
                .shadow-card:hover {
                    transform: translateY(-8px);
                    box-shadow: 0 12px 28px rgba(0,0,0,0.25);
                    border-color: #007bff;
                    background: linear-gradient(180deg, #ffffff, #f8f9fa);
                }
                
                .stat-card i {
                    transition: transform 0.3s ease, text-shadow 0.3s ease;
                }
                
                .stat-card:hover i {
                    transform: scale(1.3);
                    text-shadow: 0 0 10px rgba(0,0,0,0.2);
                }
                
                .stat-card h3 {
                    font-size: 2.0rem;
                }
                
                .stat-card p {
                    letter-spacing: 0.5px;
                    font-size: 0.95rem;
                }
                </style>
                
                <!-- Font Awesome CDN -->
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">


                
                <div class="card mb-4" style="margin-top: -20px;">
                    <div class="card-header">
                        <i class="fas fa-chart-bar me-2"></i> Risk Assessment by Barangay
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Barangay</th>
                                        <th>Total Population</th>
                                        <th>Hazard Count</th>
                                        <th>Affected Population</th>
                                        <th>Risk Percentage</th>
                                        <th>Risk Level</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($risk_data as $data): 
                                        // Calculate risk percentage for this barangay
                                        $riskPercentage = ($data['total_population'] > 0) ? 
                                            ($data['total_affected'] / $data['total_population']) * 100 : 0;
                                        
                                        // Determine risk level based on percentage and hazard count
                                        $riskLevel = 'none';
                                        if ($data['has_high_risk']) {
                                            $riskLevel = 'high';
                                        } elseif ($data['hazard_count'] > 0) {
                                            $riskLevel = 'medium';
                                        } elseif ($riskPercentage > 0) {
                                            $riskLevel = 'low';
                                        }
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($data['barangay_name']); ?></td>
                                            <td><?php echo number_format($data['total_population'] ?? 0); ?></td>
                                            <td><?php echo $data['hazard_count']; ?></td>
                                            <td><?php echo number_format($data['total_affected']); ?></td>
                                            <td>
                                                <?php if ($data['total_population'] > 0): ?>
                                                    <?php echo number_format($riskPercentage, 1); ?>%
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($riskLevel !== 'none'): ?>
                                                    <span class="badge bg-<?php 
                                                        echo $riskLevel == 'high' ? 'danger' : 
                                                             ($riskLevel == 'medium' ? 'warning' : 'success'); 
                                                    ?>">
                                                        <?php echo ucfirst($riskLevel); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">No Data</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-exclamation-circle me-2"></i> High Risk Areas
                            </div>
                            <div class="card-body">
                                <?php if (count($high_risk_areas) > 0): ?>
                                    <div class="list-group">
                                        <?php foreach ($high_risk_areas as $area): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($area['barangay_name']); ?></h6>
                                                    <p class="mb-1 text-muted">
                                                        <?php echo htmlspecialchars($area['hazard_name']); ?> - 
                                                        <?php echo number_format($area['affected_population']); ?> people affected
                                                    </p>
                                                </div>
                                                <span class="risk-high">High Risk</span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                                        <p>No high risk areas found</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-chart-pie me-2"></i> Risk Distribution
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
                                    <small class="text-muted"><?php echo number_format($highRiskPercent, 1); ?>% of at-risk population</small>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Medium Risk</span>
                                        <span class="risk-medium"><?php echo number_format($mediumRisk); ?></span>
                                    </div>
                                    <div class="progress mb-2">
                                        <div class="progress-bar bg-warning" style="width: <?php echo $mediumRiskPercent; ?>%"></div>
                                    </div>
                                    <small class="text-muted"><?php echo number_format($mediumRiskPercent, 1); ?>% of at-risk population</small>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span>Low Risk</span>
                                        <span class="risk-low"><?php echo number_format($lowRisk); ?></span>
                                    </div>
                                    <div class="progress mb-2">
                                        <div class="progress-bar bg-success" style="width: <?php echo $lowRiskPercent; ?>%"></div>
                                    </div>
                                    <small class="text-muted"><?php echo number_format($lowRiskPercent, 1); ?>% of at-risk population</small>
                                </div>
                                <div class="mt-3">
                                    <small class="text-muted">
                                        Total at-risk population: <?php echo number_format($totalRiskPopulation); ?> 
                                        (<?php echo number_format($overallRiskPercentage, 1); ?>% of total population)
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <i class="fas fa-download me-2"></i> Export Risk Reports
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="d-grid gap-2">
                                    <button class="btn btn-outline-primary">
                                        <i class="fas fa-file-pdf me-2"></i> PDF Report
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-grid gap-2">
                                    <button class="btn btn-outline-success">
                                        <i class="fas fa-file-excel me-2"></i> Excel Data
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-grid gap-2">
                                    <button class="btn btn-outline-info">
                                        <i class="fas fa-chart-bar me-2"></i> Risk Map
                                    </button>
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