<?php
// barangay_profile.php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'barangay_staff') {
    header('Location: login.php');
    exit;
}

$barangay_id = $_SESSION['barangay_id'];

// Get barangay information
$stmt = $pdo->prepare("
    SELECT b.*, 
           COALESCE(pd.total_population, b.population) as current_population,
           pd.elderly_count,
           pd.children_count,
           pd.pwd_count,
           pd.data_date as last_population_update
    FROM barangays b
    LEFT JOIN (
        SELECT barangay_id, 
               MAX(total_population) as total_population,
               MAX(elderly_count) as elderly_count,
               MAX(children_count) as children_count,
               MAX(pwd_count) as pwd_count,
               MAX(data_date) as data_date
        FROM population_data 
        WHERE barangay_id = ?
        GROUP BY barangay_id
    ) as pd ON b.id = pd.barangay_id
    WHERE b.id = ?
");
$stmt->execute([$barangay_id, $barangay_id]);
$barangay = $stmt->fetch();

// Get hazard statistics
$hazard_stats = $pdo->prepare("
    SELECT 
        COUNT(*) as total_hazards,
        COUNT(CASE WHEN risk_level = 'high' THEN 1 END) as high_risk,
        COUNT(CASE WHEN risk_level = 'medium' THEN 1 END) as medium_risk,
        COUNT(CASE WHEN risk_level = 'low' THEN 1 END) as low_risk,
        SUM(affected_population) as total_affected,
        SUM(area_km2) as total_area
    FROM hazard_zones 
    WHERE barangay_id = ?
");
$hazard_stats->execute([$barangay_id]);
$hazards = $hazard_stats->fetch();

// Handle population update
if (isset($_POST['update_population'])) {
    $total_population = $_POST['total_population'];
    $households = $_POST['households'];
    $elderly_count = $_POST['elderly_count'];
    $children_count = $_POST['children_count'];
    $pwd_count = $_POST['pwd_count'];
    
    $stmt = $pdo->prepare("INSERT INTO population_data 
                          (barangay_id, total_population, households, elderly_count, children_count, pwd_count, data_date, entered_by) 
                          VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?)");
    $stmt->execute([$barangay_id, $total_population, $households, $elderly_count, $children_count, $pwd_count, $_SESSION['username']]);
    
    $success = "Population data updated successfully!";
    // Refresh page to show updated data
    header('Location: barangay_profile.php?updated=1');
    exit;
}

// Handle barangay info update
if (isset($_POST['update_barangay_info'])) {
    $name = $_POST['barangay_name'];
    $area = $_POST['area_km2'];
    $coordinates = $_POST['coordinates'];

    $stmt = $pdo->prepare("UPDATE barangays SET name = ?, area_km2 = ?, coordinates = ? WHERE id = ?");
    $stmt->execute([$name, $area, $coordinates, $barangay_id]);

    $success = "Barangay information updated successfully!";
    header('Location: barangay_profile.php?updated=1');
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Profile - Sablayan Risk Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Barangay Profile - <?php echo $barangay['name']; ?></h1>
                </div>

                <?php if (isset($_GET['updated'])): ?>
                    <div class="alert alert-success">Population data updated successfully!</div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6">
                        <!-- Barangay Information -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-info-circle me-2"></i> Barangay Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label"><strong>Barangay Name:</strong></label>
                                        </div>
                                        <div class="col-md-6">
                                            <input type="text" name="barangay_name" class="form-control" 
                                                value="<?php echo htmlspecialchars($barangay['name']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label"><strong>Total Area (km²):</strong></label>
                                        </div>
                                        <div class="col-md-6">
                                            <input type="number" step="0.01" name="area_km2" class="form-control"
                                                value="<?php echo $barangay['area_km2']; ?>" required>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label"><strong>Coordinates:</strong></label>
                                        </div>
                                        <div class="col-md-6">
                                            <input type="text" name="coordinates" class="form-control"
                                                value="<?php echo htmlspecialchars($barangay['coordinates'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <button type="submit" name="update_barangay_info" class="btn btn-primary w-100">
                                        <i class="fas fa-save me-2"></i>Update Barangay Info
                                    </button>
                                </form>
                            </div>
                        </div>


                        <!-- Population Update Form -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-users me-2"></i> Update Population Data
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Total Population</label>
                                                <input type="number" name="total_population" class="form-control" 
                                                    value="<?php echo $barangay['current_population']; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Households</label>
                                                <input type="number" name="households" class="form-control" 
                                                    value="<?php echo $barangay['households'] ?? ''; ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Elderly (65+)</label>
                                                <input type="number" name="elderly_count" class="form-control" 
                                                    value="<?php echo $barangay['elderly_count'] ?? 0; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Children (0-5)</label>
                                                <input type="number" name="children_count" class="form-control" 
                                                    value="<?php echo $barangay['children_count'] ?? 0; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">PWD Count</label>
                                                <input type="number" name="pwd_count" class="form-control" 
                                                    value="<?php echo $barangay['pwd_count'] ?? 0; ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" name="update_population" class="btn btn-primary w-100">
                                        <i class="fas fa-save me-2"></i>Update Population Data
                                    </button>
                                </form>
                                <?php if ($barangay['last_population_update']): ?>
                                    <small class="text-muted">
                                        Last updated: <?php echo date('M j, Y', strtotime($barangay['last_population_update'])); ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <!-- Hazard Statistics -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-exclamation-triangle me-2"></i> Hazard Statistics
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center mb-4">
                                    <div class="col-md-6">
                                        <h3 class="text-danger"><?php echo $hazards['total_hazards']; ?></h3>
                                        <small class="text-muted">Total Hazard Zones</small>
                                    </div>
                                    <div class="col-md-6">
                                        <h3 class="text-warning"><?php echo number_format($hazards['total_affected']); ?></h3>
                                        <small class="text-muted">Population Affected</small>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Risk Level Distribution:</strong>
                                    <div class="mt-2">
                                        <span class="badge bg-danger me-2">
                                            High: <?php echo $hazards['high_risk']; ?>
                                        </span>
                                        <span class="badge bg-warning me-2">
                                            Medium: <?php echo $hazards['medium_risk']; ?>
                                        </span>
                                        <span class="badge bg-success">
                                            Low: <?php echo $hazards['low_risk']; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Total Hazard Area:</strong>
                                    <?php echo number_format($hazards['total_area'], 2); ?> km²
                                </div>
                                
                                <div class="mt-3">
                                    <a href="hazard_data.php" class="btn btn-outline-warning btn-sm">
                                        <i class="fas fa-plus me-1"></i>Add New Hazard
                                    </a>
                                    <a href="barangay_reports.php" class="btn btn-outline-info btn-sm">
                                        <i class="fas fa-chart-bar me-1"></i>View Reports
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Links -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-link me-2"></i> Quick Links
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="hazard_data.php" class="btn btn-outline-primary">
                                        <i class="fas fa-exclamation-triangle me-2"></i>Manage Hazards
                                    </a>
                                    <a href="population_data.php" class="btn btn-outline-success">
                                        <i class="fas fa-users me-2"></i>Population Data
                                    </a>
                                    <a href="data_entry.php" class="btn btn-outline-info" style="display: none;">
                                        <i class="fas fa-edit me-2"></i>Data Entry
                                    </a>
                                    <a href="barangay_reports.php" class="btn btn-outline-warning">
                                        <i class="fas fa-file-alt me-2"></i>Generate Reports
                                    </a>
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