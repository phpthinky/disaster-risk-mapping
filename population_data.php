<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// population_data.php
session_start();
require_once 'config.php';

$brgy_id = $_SESSION['barangay_id'];

// Archive function - UPDATED
function archivePopulationData($pdo, $data, $changeType, $username) {
    $stmt = $pdo->prepare("INSERT INTO population_data_archive 
                          (original_id, barangay_id, total_population, households, elderly_count, 
                           children_count, pwd_count, ips_count, solo_parent_count, widow_count, 
                           data_date, archived_by, change_type) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    return $stmt->execute([
        $data['id'] ?? null,
        $data['barangay_id'],
        $data['total_population'],
        $data['households'],
        $data['elderly_count'],
        $data['children_count'],
        $data['pwd_count'],
        $data['ips_count'] ?? 0,
        $data['solo_parent_count'] ?? 0,
        $data['widow_count'] ?? 0,
        $data['data_date'] ?? date('Y-m-d'),
        $username,
        $changeType
    ]);
}

// Handle AJAX requests for archive data
if (isset($_GET['get_archive'])) {
    $barangay_id = $_GET['barangay_id'];
    $stmt = $pdo->prepare("SELECT pa.*, b.name as barangay_name 
                          FROM population_data_archive pa 
                          JOIN barangays b ON pa.barangay_id = b.id 
                          WHERE pa.barangay_id = ? 
                          ORDER BY pa.archived_at DESC");
    $stmt->execute([$barangay_id]);
    $archive_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($archive_data);
    exit();
}

// Handle export archive request
if (isset($_GET['export_archive'])) {
    $barangay_id = $_GET['barangay_id'];
    $stmt = $pdo->prepare("SELECT * FROM population_data_archive 
                          WHERE barangay_id = ? 
                          ORDER BY archived_at DESC");
    $stmt->execute([$barangay_id]);
    $export_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($export_data);
    exit();
}

// GOLDEN RULE: Population data is now auto-computed from households.
// Manual population entry is disabled. All forms redirect to computed view.
// Redirect manual submissions to household management
if ($_POST && isset($_POST['submit_population'])) {
    header('Location: population_data.php?notice=computed');
    exit;
}
if (false && isset($_POST['submit_population_DISABLED'])) {
    $barangay_id = $_POST['barangay_id'];
    $total_population = $_POST['total_population'];
    $households = $_POST['households'];
    $elderly_count = $_POST['elderly_count'];
    $children_count = $_POST['children_count'];
    $pwd_count = $_POST['pwd_count'];
    $ips_count = $_POST['ips_count'] ?? 0;
    $solo_parent_count = $_POST['solo_parent_count'] ?? 0;
    $widow_count = $_POST['widow_count'] ?? 0;
    
    // Check if data already exists for this barangay and date
    $stmt = $pdo->prepare("SELECT * FROM population_data 
                          WHERE barangay_id = ? AND data_date = CURDATE()");
    $stmt->execute([$barangay_id]);
    $existing_data = $stmt->fetch();
    
    if ($existing_data) {
        // Archive existing data before updating
        if (archivePopulationData($pdo, $existing_data, 'UPDATE', $_SESSION['username'])) {
            // Update existing record - UPDATED
            $stmt = $pdo->prepare("UPDATE population_data 
                                  SET total_population = ?, households = ?, elderly_count = ?, 
                                      children_count = ?, pwd_count = ?, ips_count = ?, 
                                      solo_parent_count = ?, widow_count = ?, entered_by = ?
                                  WHERE id = ?");
            $stmt->execute([$total_population, $households, $elderly_count, 
                           $children_count, $pwd_count, $ips_count, $solo_parent_count, 
                           $widow_count, $_SESSION['username'], $existing_data['id']]);
        }
    } else {
        // Insert new record - UPDATED
        $stmt = $pdo->prepare("INSERT INTO population_data 
                              (barangay_id, total_population, households, elderly_count, 
                               children_count, pwd_count, ips_count, solo_parent_count, 
                               widow_count, data_date, entered_by) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?)");
        $stmt->execute([$barangay_id, $total_population, $households, 
                       $elderly_count, $children_count, $pwd_count, $ips_count, 
                       $solo_parent_count, $widow_count, $_SESSION['username']]);
    }
    
    // Log the data entry
    $stmt = $pdo->prepare("INSERT INTO data_entries (user_id, data_type, description) 
                          VALUES (?, 'population', ?)");
    $stmt->execute([$_SESSION['user_id'], "Population data update for barangay ID: $barangay_id"]);
    
    $success = "Population data updated successfully!" . 
               ($existing_data ? " Previous data archived." : "");
}

// Handle delete request
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    
    // Get data for archiving before deletion
    $stmt = $pdo->prepare("SELECT * FROM population_data WHERE id = ?");
    $stmt->execute([$delete_id]);
    $deleted_data = $stmt->fetch();
    
    if ($deleted_data) {
        // Archive the data
        archivePopulationData($pdo, $deleted_data, 'DELETE', $_SESSION['username']);
        
        // Delete the record
        $stmt = $pdo->prepare("DELETE FROM population_data WHERE id = ?");
        $stmt->execute([$delete_id]);
        
        // Log the deletion
        $stmt = $pdo->prepare("INSERT INTO data_entries (user_id, data_type, description) 
                              VALUES (?, 'population', ?)");
        $stmt->execute([$_SESSION['user_id'], 
                       "Population data deleted for barangay ID: " . $deleted_data['barangay_id'] . ". Data archived."]);
        
        $success = "Population data deleted successfully! Data has been archived.";
    }
    
    header("Location: population_data.php");
    exit();
}

// Manual edit disabled — population is auto-computed
if ($_POST && isset($_POST['update_population'])) {
    header('Location: population_data.php?notice=computed');
    exit;
}
if (false && isset($_POST['update_population_DISABLED'])) {
    $id = $_POST['edit_id'];
    $barangay_id = $_POST['barangay_id'];
    $total_population = $_POST['total_population'];
    $households = $_POST['households'];
    $elderly_count = $_POST['elderly_count'];
    $children_count = $_POST['children_count'];
    $pwd_count = $_POST['pwd_count'];
    $ips_count = $_POST['ips_count'] ?? 0;
    $solo_parent_count = $_POST['solo_parent_count'] ?? 0;
    $widow_count = $_POST['widow_count'] ?? 0;
    
    // Get current data for archiving
    $stmt = $pdo->prepare("SELECT * FROM population_data WHERE id = ?");
    $stmt->execute([$id]);
    $current_data = $stmt->fetch();
    
    if ($current_data) {
        // Archive current data before update
        if (archivePopulationData($pdo, $current_data, 'UPDATE', $_SESSION['username'])) {
            // Update the record - UPDATED
            $stmt = $pdo->prepare("UPDATE population_data 
                                  SET barangay_id = ?, total_population = ?, households = ?, 
                                      elderly_count = ?, children_count = ?, pwd_count = ?, 
                                      ips_count = ?, solo_parent_count = ?, widow_count = ?
                                  WHERE id = ?");
            $stmt->execute([$barangay_id, $total_population, $households, 
                           $elderly_count, $children_count, $pwd_count, $ips_count, 
                           $solo_parent_count, $widow_count, $id]);
            
            // Log the update
            $stmt = $pdo->prepare("INSERT INTO data_entries (user_id, data_type, description) 
                                  VALUES (?, 'population', ?)");
            $stmt->execute([$_SESSION['user_id'], 
                           "Population data updated for barangay ID: $barangay_id. Previous data archived."]);
            
            $success = "Population data updated successfully! Previous data archived.";
        }
    }
}

// ===============================
// ROLE BASED DISPLAY
// ===============================

if ($_SESSION['role'] == 'admin') {

    // ADMIN → show ALL barangays
    $barangays = $pdo->query("SELECT * FROM barangays ORDER BY name ASC")->fetchAll();

    // ADMIN → show ALL population data
    $population_data = $pdo->query("SELECT pd.*, b.name as barangay_name 
                                    FROM population_data pd
                                    JOIN barangays b ON pd.barangay_id = b.id
                                    ORDER BY pd.created_at DESC")->fetchAll();

} else {

    // NON-ADMIN → only their barangay
    $stmt = $pdo->prepare("SELECT * FROM barangays WHERE id = ?");
    $stmt->execute([$brgy_id]);
    $barangays = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT pd.*, b.name as barangay_name
                           FROM population_data pd
                           JOIN barangays b ON pd.barangay_id = b.id
                           WHERE pd.barangay_id = ?
                           ORDER BY pd.created_at DESC");
    $stmt->execute([$brgy_id]);
    $population_data = $stmt->fetchAll();
}


// Get data for edit modal if requested
$edit_data = null;
if (isset($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    $stmt = $pdo->prepare("SELECT pd.*, b.name as barangay_name 
                          FROM population_data pd 
                          JOIN barangays b ON pd.barangay_id = b.id 
                          WHERE pd.id = ?");
    $stmt->execute([$edit_id]);
    $edit_data = $stmt->fetch();
}

// Get total archive count for statistics
$total_archives = $pdo->query("SELECT COUNT(*) as count FROM population_data_archive")->fetch()['count'];

// Calculate statistics
$total_population = 0;
$total_h = 0;
$total_elderly = 0;
$total_pwd = 0;
$total_children = 0;
$total_ips = 0;
$total_solo_parent = 0;
$total_widow = 0;

foreach($population_data as $data) {
    $total_population += $data['total_population'];
    $total_h += $data['households'];
    $total_elderly += $data['elderly_count'];
    $total_pwd += $data['pwd_count'];
    $total_children += $data['children_count'];
    $total_ips += $data['ips_count'] ?? 0;
    $total_solo_parent += $data['solo_parent_count'] ?? 0;
    $total_widow += $data['widow_count'] ?? 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Population Data - Sablayan Risk Assessment</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
    :root {
        --primary-color: #3498db;
        --secondary-color: #2c3e50;
        --success-color: #27ae60;
        --danger-color: #e74c3c;
        --warning-color: #f39c12;
        --info-color: #17a2b8;
        --archive-color: #8e44ad;
    }
    
    body {
        background-color: #f8f9fa;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    .card {
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease-in-out;
        border: none;
        margin-bottom: 20px;
    }
    
    .card:hover {
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        transform: translateY(-5px);
    }
    
    .card-header {
        border-radius: 10px 10px 0 0 !important;
        font-weight: 600;
    }
    
    .form-icon {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #6c757d;
    }
    
    .tooltip-inner {
        max-width: 200px;
        text-align: left;
    }
    
    .btn-action {
        padding: 5px 10px;
        margin: 0 2px;
        border-radius: 5px;
    }
    
    .stat-card {
        text-align: center;
        padding: 15px;
        border-radius: 8px;
        color: white;
        margin-bottom: 15px;
    }
    
    .stat-card i {
        font-size: 2rem;
        margin-bottom: 10px;
    }
    
    .stat-card .number {
        font-size: 1.8rem;
        font-weight: bold;
    }
    
    .stat-card .label {
        font-size: 0.9rem;
        opacity: 0.9;
    }
    
    .table th {
        border-top: none;
        font-weight: 600;
        color: var(--secondary-color);
    }
    
    .table-hover tbody tr:hover {
        background-color: rgba(52, 152, 219, 0.05);
    }
    
    .modal-header {
        background-color: var(--primary-color);
        color: white;
    }
    
    .modal-footer {
        border-top: 1px solid #dee2e6;
    }
    
    .search-box {
        position: relative;
    }
    
    .search-box i {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #6c757d;
    }
    
    .search-box input {
        padding-left: 40px;
    }
    
    .badge-population {
        background-color: var(--primary-color);
    }
    
    .badge-households {
        background-color: var(--success-color);
    }
    
    .badge-elderly {
        background-color: var(--warning-color);
    }
    
    .badge-children {
        background-color: var(--info-color);
    }
    
    .badge-pwd {
        background-color: var(--secondary-color);
    }
    
    .badge-archive {
        background-color: var(--archive-color);
    }
    
    .btn-archive {
        background-color: var(--archive-color);
        color: white;
        border: none;
    }
    
    .btn-archive:hover {
        background-color: #7d3c98;
        color: white;
    }
    
    .archive-table {
        font-size: 0.85rem;
    }
    
    .archive-table .change-update {
        background-color: rgba(52, 152, 219, 0.1);
    }
    
    .archive-table .change-delete {
        background-color: rgba(231, 76, 60, 0.1);
    }
    
    .modal-xl {
        max-width: 90%;
    }
    
    /* Add to existing CSS */
.badge-ips {
    background-color: #9b59b6;
}

.badge-solo-parent {
    background-color: #2ecc71;
}

.badge-widow {
    background-color: #e67e22;
}

/* Update form icons */
.fa-mountain {
    color: #9b59b6;
}

.fa-user-injured {
    color: #e67e22;
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
                <h1 class="h2"><i class="fas fa-users me-2"></i>Population Data Management</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button class="btn btn-sm btn-outline-secondary me-2" data-bs-toggle="modal" data-bs-target="#archiveHelpModal">
                        <i class="fas fa-archive me-1"></i>Archive Info
                    </button>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#helpModal">
                        <i class="fas fa-question-circle me-1"></i>Help
                    </button>
                </div>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
                    <i class="fas fa-check-circle me-2 fs-4"></i>
                    <div><?php echo $success; ?></div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards - UPDATED -->
            <div class="row mb-4">
                <div class="col-md-2 col-sm-4">
                    <div class="stat-card bg-primary">
                        <i class="fas fa-users"></i>
                        <div class="number"><?php echo count($population_data); ?></div>
                        <div class="label">Total Records</div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4">
                    <div class="stat-card bg-success">
                        <i class="fas fa-map-marker-alt"></i>
                        <div class="number"><?php echo count($barangays); ?></div>
                        <div class="label">Barangays</div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4">
                    <div class="stat-card bg-warning">
                        <i class="fas fa-user-friends"></i>
                        <div class="number"><?php echo number_format($total_population); ?></div>
                        <div class="label">Total Population</div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4">
                    <div class="stat-card bg-info">
                        <i class="fas fa-home"></i>
                        <div class="number"><?php echo number_format($total_h); ?></div>
                        <div class="label">Total Households</div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4">
                    <div class="stat-card" style="background-color: #9b59b6;">
                        <i class="fas fa-mountain"></i>
                        <div class="number"><?php echo number_format($total_ips); ?></div>
                        <div class="label">IPS Population</div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4">
                    <div class="stat-card" style="background-color: var(--archive-color);">
                        <i class="fas fa-archive"></i>
                        <div class="number"><?php echo number_format($total_archives); ?></div>
                        <div class="label">Archived Records</div>
                    </div>
                </div>
            </div>
            
            <!-- Additional Statistics Cards Row -->
            <div class="row mb-4">
                <div class="col-md-2 col-sm-4">
                    <div class="stat-card bg-danger">
                        <i class="fas fa-blind"></i>
                        <div class="number"><?php echo number_format($total_elderly); ?></div>
                        <div class="label">Elderly Population</div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4">
                    <div class="stat-card bg-secondary">
                        <i class="fas fa-wheelchair"></i>
                        <div class="number"><?php echo number_format($total_pwd); ?></div>
                        <div class="label">PWD Population</div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4">
                    <div class="stat-card" style="background-color: #2ecc71;">
                        <i class="fas fa-user-friends"></i>
                        <div class="number"><?php echo number_format($total_solo_parent); ?></div>
                        <div class="label">Solo Parents</div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4">
                    <div class="stat-card" style="background-color: #e67e22;">
                        <i class="fas fa-user-injured"></i>
                        <div class="number"><?php echo number_format($total_widow); ?></div>
                        <div class="label">Widow/Widower</div>
                    </div>
                </div>
                <?php
                $total_children = 0;
                foreach($population_data as $data) {
                    $total_children += $data['children_count'];
                }
                ?>
                <div class="col-md-2 col-sm-4">
                    <div class="stat-card" style="background-color: #3498db;">
                        <i class="fas fa-child"></i>
                        <div class="number"><?php echo number_format($total_children); ?></div>
                        <div class="label">Children</div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Population Form Card - UPDATED -->
                <div class="col-md-5">
                    <div class="card border-primary">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Update Population Data</h5>
                            <span class="badge bg-light text-primary"><?php echo date('M j, Y'); ?></span>
                        </div>
                        <div class="card-body">
                            <form method="POST" class="position-relative">
                                <div class="mb-3 position-relative">
                                    <label class="form-label fw-semibold">Barangay</label>
                                    <select name="barangay_id" class="form-select" required>
                                        <option value="">Select Barangay</option>
                                        <?php foreach ($barangays as $barangay): ?>
                                            <option value="<?php echo $barangay['id']; ?>" <?php echo ($edit_data && $edit_data['barangay_id'] == $barangay['id']) ? 'selected' : ''; ?>>
                                                <?php echo $barangay['name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class="fas fa-map-marker-alt form-icon" data-bs-toggle="tooltip" title="Select the barangay for the data entry"></i>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative">
                                            <label class="form-label fw-semibold">Total Population</label>
                                            <input type="number" name="total_population" class="form-control" required 
                                                   value="<?php echo $edit_data ? $edit_data['total_population'] : ''; ?>">
                                            <i class="fas fa-users form-icon" data-bs-toggle="tooltip" title="Enter total population"></i>
                                        </div>
                                        <div class="mb-3 position-relative">
                                            <label class="form-label fw-semibold">Households</label>
                                            <input type="number" name="households" class="form-control" required 
                                                   value="<?php echo $edit_data ? $edit_data['households'] : ''; ?>">
                                            <i class="fas fa-home form-icon" data-bs-toggle="tooltip" title="Enter number of households"></i>
                                        </div>
                                        <div class="mb-3 position-relative">
                                            <label class="form-label fw-semibold">Elderly (60+)</label>
                                            <input type="number" name="elderly_count" class="form-control" required 
                                                   value="<?php echo $edit_data ? $edit_data['elderly_count'] : ''; ?>">
                                            <i class="fas fa-blind form-icon" data-bs-toggle="tooltip" title="Number of elderly residents"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative">
                                            <label class="form-label fw-semibold">Children</label>
                                            <input type="number" name="children_count" class="form-control" required 
                                                   value="<?php echo $edit_data ? $edit_data['children_count'] : ''; ?>">
                                            <i class="fas fa-child form-icon" data-bs-toggle="tooltip" title="Number of young children"></i>
                                        </div>
                                        <div class="mb-3 position-relative">
                                            <label class="form-label fw-semibold">PWD Count</label>
                                            <input type="number" name="pwd_count" class="form-control" required 
                                                   value="<?php echo $edit_data ? $edit_data['pwd_count'] : ''; ?>">
                                            <i class="fas fa-wheelchair form-icon" data-bs-toggle="tooltip" title="Number of persons with disabilities"></i>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- NEW FIELDS ROW -->
                                <div class="row mt-3 border-top pt-3">
                                    <div class="col-md-4">
                                        <div class="mb-3 position-relative">
                                            <label class="form-label fw-semibold">IPS Count <span class="text-muted">(Indigenous People)</span></label>
                                            <input type="number" name="ips_count" class="form-control" value="<?php echo $edit_data ? $edit_data['ips_count'] : '0'; ?>">
                                            <i class="fas fa-mountain form-icon" data-bs-toggle="tooltip" title="Number of Indigenous People"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3 position-relative">
                                            <label class="form-label fw-semibold">Solo Parent</label>
                                            <input type="number" name="solo_parent_count" class="form-control" value="<?php echo $edit_data ? $edit_data['solo_parent_count'] : '0'; ?>">
                                            <i class="fas fa-user-friends form-icon" data-bs-toggle="tooltip" title="Number of solo parents"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3 position-relative">
                                            <label class="form-label fw-semibold">Widow/Widower</label>
                                            <input type="number" name="widow_count" class="form-control" value="<?php echo $edit_data ? $edit_data['widow_count'] : '0'; ?>">
                                            <i class="fas fa-user-injured form-icon" data-bs-toggle="tooltip" title="Number of widows/widowers"></i>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($edit_data): ?>
                                    <input type="hidden" name="edit_id" value="<?php echo $edit_data['id']; ?>">
                                    <button type="submit" name="update_population" class="btn btn-success w-100 mt-3">
                                        <i class="fas fa-sync-alt me-2"></i>Update Data
                                    </button>
                                    <a href="population_data.php" class="btn btn-secondary w-100 mt-2">
                                        <i class="fas fa-times me-2"></i>Cancel Edit
                                    </a>
                                <?php else: ?>
                                    <button type="submit" name="submit_population" class="btn btn-primary w-100 mt-3">
                                        <i class="fas fa-save me-2"></i>Save Data
                                    </button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Population Data Table - UPDATED -->
                <div class="col-md-7">
                    <div class="card border-success">
                        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-table me-2"></i>Population Data Records</h5>
                            <div class="d-flex">
                                <div class="search-box me-2">
                                    <i class="fas fa-search"></i>
                                    <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search...">
                                </div>
                                <button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#dataTable" aria-expanded="true" aria-controls="dataTable">
                                    <i class="fas fa-expand-alt"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body collapse show" id="dataTable">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped" id="populationTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Barangay</th>
                                            <th>Pop</th>
                                            <th>HH</th>
                                            <th>Elderly</th>
                                            <th>Children</th>
                                            <th>PWD</th>
                                            <th>IPS</th>
                                            <th>Solo</th>
                                            <th>Widow</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($population_data as $data): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold"><?php echo $data['barangay_name']; ?></div>
                                                    <small class="text-muted">By: <?php echo $data['entered_by']; ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo number_format($data['total_population']); ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success"><?php echo number_format($data['households']); ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-warning"><?php echo number_format($data['elderly_count']); ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo number_format($data['children_count']); ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?php echo number_format($data['pwd_count']); ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge" style="background-color: #9b59b6;"><?php echo number_format($data['ips_count'] ?? 0); ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge" style="background-color: #2ecc71;"><?php echo number_format($data['solo_parent_count'] ?? 0); ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge" style="background-color: #e67e22;"><?php echo number_format($data['widow_count'] ?? 0); ?></span>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($data['data_date'])); ?></td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="?edit_id=<?php echo $data['id']; ?>" class="btn btn-sm btn-outline-primary btn-action" data-bs-toggle="tooltip" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-sm btn-outline-info btn-action" 
                                                                data-bs-toggle="modal" data-bs-target="#viewArchiveModal"
                                                                data-barangay-id="<?php echo $data['barangay_id']; ?>"
                                                                data-barangay-name="<?php echo $data['barangay_name']; ?>"
                                                                data-bs-toggle="tooltip" title="View Archive History">
                                                            <i class="fas fa-history"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger btn-action" data-bs-toggle="modal" data-bs-target="#deleteModal" 
                                                                data-id="<?php echo $data['id']; ?>" data-barangay="<?php echo $data['barangay_name']; ?>" data-bs-toggle="tooltip" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if (empty($population_data)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-database fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No population data available. Add your first record using the form.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the population data for <strong id="barangayName"></strong>?</p>
                <div class="alert alert-warning">
                    <i class="fas fa-archive me-2"></i>
                    <strong>Note:</strong> This data will be archived before deletion and can be viewed in the archive history.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Cancel</button>
                <a id="deleteConfirm" href="#" class="btn btn-danger"><i class="fas fa-trash me-1"></i>Delete</a>
            </div>
        </div>
    </div>
</div>

<!-- View Archive Modal -->
<div class="modal fade" id="viewArchiveModal" tabindex="-1" aria-labelledby="viewArchiveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background-color: var(--archive-color); color: white;">
                <h5 class="modal-title" id="viewArchiveModalLabel">
                    <i class="fas fa-history me-2"></i>Archive History for <span id="archiveBarangayName"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="archiveLoading" class="text-center py-5">
                    <div class="spinner-border" style="color: var(--archive-color);" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading archive data...</p>
                </div>
                <div id="archiveContent" style="display: none;">
                    <div class="table-responsive">
                        <table class="table table-hover archive-table">
                            <thead>
                                <tr>
                                    <th>Archive Date</th>
                                    <th>Pop</th>
                                    <th>HH</th>
                                    <th>Elderly</th>
                                    <th>Children</th>
                                    <th>PWD</th>
                                    <th>IPS</th>
                                    <th>Solo</th>
                                    <th>Widow</th>
                                    <th>Data Date</th>
                                    <th>Change Type</th>
                                    <th>Archived By</th>
                                </tr>
                            </thead>
                            <tbody id="archiveTableBody">
                                <!-- Archive data will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                    <div id="noArchiveData" class="text-center py-4" style="display: none;">
                        <i class="fas fa-archive fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No archive history found for this barangay.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-archive" id="exportArchiveBtn">
                    <i class="fas fa-download me-1"></i>Export Archive
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Archive Help Modal -->
<div class="modal fade" id="archiveHelpModal" tabindex="-1" aria-labelledby="archiveHelpModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background-color: var(--archive-color); color: white;">
                <h5 class="modal-title" id="archiveHelpModalLabel">
                    <i class="fas fa-archive me-2"></i>Archive System Information
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6><i class="fas fa-history me-2"></i>Automatic Archiving</h6>
                <p>The system automatically archives population data when:</p>
                <ul>
                    <li><strong>Updating Data:</strong> When you edit existing population data, the previous version is automatically saved to the archive.</li>
                    <li><strong>Deleting Data:</strong> When you delete population data, it's moved to the archive for record-keeping.</li>
                    <li><strong>Daily Updates:</strong> If you enter data for the same barangay on the same day, previous data is archived.</li>
                </ul>
                
                <h6><i class="fas fa-eye me-2"></i>Viewing Archive</h6>
                <p>To view archive history for a barangay:</p>
                <ol>
                    <li>Find the barangay in the table</li>
                    <li>Click the <i class="fas fa-history text-info"></i> (history) icon</li>
                    <li>View all previous versions of the data</li>
                </ol>
                
                <h6><i class="fas fa-download me-2"></i>Archive Information</h6>
                <p>Each archive record includes:</p>
                <ul>
                    <li>Original data values</li>
                    <li>Date and time of archiving</li>
                    <li>User who made the change</li>
                    <li>Type of change (UPDATE or DELETE)</li>
                </ul>
                
                <!-- Inside helpModal body - Add to existing help -->
                <h6><i class="fas fa-user-tag me-2" style="color: #9b59b6;"></i>Additional Categories</h6>
                <p>The system now tracks additional population categories:</p>
                <ul>
                    <li><strong>IPS (Indigenous People):</strong> Number of Indigenous People in the community</li>
                    <li><strong>Solo Parent:</strong> Number of single parents raising children alone</li>
                    <li><strong>Widow/Widower:</strong> Number of individuals who have lost their spouse</li>
                </ul>
                
                <p>All these categories are included in the total population count and are automatically archived when updated.</p>
                
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-archive" data-bs-dismiss="modal">Got it!</button>
            </div>
        </div>
    </div>
</div>

<!-- Help Modal -->
<div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="helpModalLabel"><i class="fas fa-question-circle me-2"></i>Population Data Management Help</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6><i class="fas fa-edit me-2 text-primary"></i>Adding Population Data</h6>
                <p>Use the form on the left to add new population data for a barangay. Fill in all required fields:</p>
                <ul>
                    <li><strong>Barangay:</strong> Select the barangay from the dropdown</li>
                    <li><strong>Total Population:</strong> Enter the total number of residents</li>
                    <li><strong>Households:</strong> Enter the number of households</li>
                    <li><strong>Elderly (60+):</strong> Number of residents aged 60 and above</li>
                    <li><strong>Children:</strong> Number of children</li>
                    <li><strong>PWD Count:</strong> Number of persons with disabilities</li>
                </ul>
                
                <h6><i class="fas fa-table me-2 text-success"></i>Managing Records</h6>
                <p>The table displays all population data records. You can:</p>
                <ul>
                    <li><strong>Edit:</strong> Click the <i class="fas fa-edit text-primary"></i> icon to edit a record</li>
                    <li><strong>View Archive:</strong> Click the <i class="fas fa-history text-info"></i> icon to view archive history</li>
                    <li><strong>Delete:</strong> Click the <i class="fas fa-trash text-danger"></i> icon to delete a record</li>
                    <li><strong>Search:</strong> Use the search box to filter records</li>
                </ul>
                
                <h6><i class="fas fa-chart-bar me-2 text-warning"></i>Statistics</h6>
                <p>The statistics cards at the top provide an overview of the population data:</p>
                <ul>
                    <li>Total number of records</li>
                    <li>Number of barangays with data</li>
                    <li>Total population across all barangays</li>
                    <li>Total number of households</li>
                    <li>Total elderly population</li>
                    <li>Total archived records</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Got it!</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
    // Enable tooltips
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]')
    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))
    
    // Delete modal handler
    const deleteModal = document.getElementById('deleteModal')
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget
            const id = button.getAttribute('data-id')
            const barangay = button.getAttribute('data-barangay')
            
            document.getElementById('barangayName').textContent = barangay
            document.getElementById('deleteConfirm').href = `?delete_id=${id}`
        })
    }
    
    // Archive modal handler
    const archiveModal = document.getElementById('viewArchiveModal')
    if (archiveModal) {
        archiveModal.addEventListener('show.bs.modal', async (event) => {
            const button = event.relatedTarget
            const barangayId = button.getAttribute('data-barangay-id')
            const barangayName = button.getAttribute('data-barangay-name')
            
            // Update modal title
            document.getElementById('archiveBarangayName').textContent = barangayName
            
            // Show loading, hide content
            document.getElementById('archiveLoading').style.display = 'block'
            document.getElementById('archiveContent').style.display = 'none'
            document.getElementById('noArchiveData').style.display = 'none'
            
            try {
                // Fetch archive data via AJAX
                const response = await fetch(`population_data.php?get_archive=1&barangay_id=${barangayId}`)
                const data = await response.json()
                
                // Hide loading
                document.getElementById('archiveLoading').style.display = 'none'
                
                if (data.length > 0) {
                    // Populate table
                    const tableBody = document.getElementById('archiveTableBody')
                    tableBody.innerHTML = ''
                    
                // Inside archive modal handler
                data.forEach(record => {
                    const row = document.createElement('tr')
                    row.className = record.change_type === 'UPDATE' ? 'change-update' : 'change-delete'
                    
                    row.innerHTML = `
                        <td>${new Date(record.archived_at).toLocaleString()}</td>
                        <td><span class="badge bg-primary">${parseInt(record.total_population).toLocaleString()}</span></td>
                        <td><span class="badge bg-success">${parseInt(record.households).toLocaleString()}</span></td>
                        <td><span class="badge bg-warning">${parseInt(record.elderly_count).toLocaleString()}</span></td>
                        <td><span class="badge bg-info">${parseInt(record.children_count).toLocaleString()}</span></td>
                        <td><span class="badge bg-secondary">${parseInt(record.pwd_count).toLocaleString()}</span></td>
                        <td><span class="badge" style="background-color: #9b59b6;">${parseInt(record.ips_count || 0).toLocaleString()}</span></td>
                        <td><span class="badge" style="background-color: #2ecc71;">${parseInt(record.solo_parent_count || 0).toLocaleString()}</span></td>
                        <td><span class="badge" style="background-color: #e67e22;">${parseInt(record.widow_count || 0).toLocaleString()}</span></td>
                        <td>${new Date(record.data_date).toLocaleDateString()}</td>
                        <td>
                            <span class="badge ${record.change_type === 'UPDATE' ? 'bg-warning' : 'bg-danger'}">
                                ${record.change_type}
                            </span>
                        </td>
                        <td>${record.archived_by}</td>
                    `
                    tableBody.appendChild(row)
                })
                    
                    document.getElementById('archiveContent').style.display = 'block'
                } else {
                    document.getElementById('noArchiveData').style.display = 'block'
                }
                
                // Set export button data
                document.getElementById('exportArchiveBtn').dataset.barangayId = barangayId
                document.getElementById('exportArchiveBtn').dataset.barangayName = barangayName
                
            } catch (error) {
                console.error('Error loading archive:', error)
                document.getElementById('archiveLoading').style.display = 'none'
                document.getElementById('noArchiveData').style.display = 'block'
                document.getElementById('noArchiveData').innerHTML = `
                    <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                    <p class="text-danger">Error loading archive data. Please try again.</p>
                `
            }
        })
    }
    
    // Export archive function - UPDATED
    document.getElementById('exportArchiveBtn').addEventListener('click', async function() {
        const barangayId = this.dataset.barangayId
        const barangayName = this.dataset.barangayName
        
        try {
            const response = await fetch(`population_data.php?export_archive=1&barangay_id=${barangayId}`)
            const data = await response.json()
            
            if (data.length > 0) {
                // Create worksheet - UPDATED
                const ws = XLSX.utils.json_to_sheet(data.map(record => ({
                    'Archive Date': record.archived_at,
                    'Population': record.total_population,
                    'Households': record.households,
                    'Elderly Count': record.elderly_count,
                    'Children Count': record.children_count,
                    'PWD Count': record.pwd_count,
                    'IPS Count': record.ips_count || 0,
                    'Solo Parent Count': record.solo_parent_count || 0,
                    'Widow Count': record.widow_count || 0,
                    'Data Date': record.data_date,
                    'Change Type': record.change_type,
                    'Archived By': record.archived_by
                })))
                
                // Create workbook
                const wb = XLSX.utils.book_new()
                XLSX.utils.book_append_sheet(wb, ws, 'Archive')
                
                // Generate filename
                const filename = `Population_Archive_${barangayName.replace(/\s+/g, '_')}_${new Date().toISOString().split('T')[0]}.xlsx`
                
                // Save file
                XLSX.writeFile(wb, filename)
            }
        } catch (error) {
            console.error('Error exporting archive:', error)
            alert('Error exporting archive data.')
        }
    })
    
    // Search functionality
    document.getElementById('searchInput').addEventListener('keyup', function() {
        const value = this.value.toLowerCase()
        const rows = document.querySelectorAll('#populationTable tbody tr')
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase()
            row.style.display = text.includes(value) ? '' : 'none'
        })
    })
    
    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert')
        alerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert)
            bsAlert.close()
        })
    }, 5000)
</script>
</body>
</html>