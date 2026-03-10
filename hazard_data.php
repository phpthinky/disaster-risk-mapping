<?php
// hazard_data.php
// Hazard zones = permanent pre-mapped risk assessment areas.
// Drawing a polygon here auto-computes the total population living in the danger zone.
// This is the RISK ASSESSMENT figure (who lives in the zone, not who was affected by an event).
// For actual disaster event tracking, use incident_reports.php.

session_start();
require_once 'config.php';
require_once __DIR__ . '/functions/population_functions.php';

// Ensure polygon_geojson column exists on hazard_zones
try {
    $pdo->exec("ALTER TABLE hazard_zones ADD COLUMN IF NOT EXISTS polygon_geojson LONGTEXT DEFAULT NULL");
} catch (PDOException $e) { /* already exists */ }

// AJAX: compute population from drawn hazard zone polygon
if (isset($_POST['action']) && $_POST['action'] === 'compute_hazard_population') {
    header('Content-Type: application/json');
    $geojson_str = $_POST['polygon_geojson'] ?? '';
    $geojson = json_decode($geojson_str, true);
    if (!$geojson) { echo json_encode(['success'=>false,'message'=>'Invalid GeoJSON']); exit; }

    // Extract ring
    $ring = [];
    $geo = $geojson;
    if ($geojson['type'] === 'FeatureCollection' && !empty($geojson['features'])) $geo = $geojson['features'][0]['geometry'];
    elseif ($geojson['type'] === 'Feature') $geo = $geojson['geometry'];
    if (isset($geo['type']) && $geo['type'] === 'Polygon') $ring = $geo['coordinates'][0];

    if (empty($ring)) { echo json_encode(['success'=>false,'message'=>'No polygon found']); exit; }

    // Count all households in the zone
    $hh_stmt = $pdo->query("
        SELECT family_members, pwd_count, senior_count, infant_count, minor_count, pregnant_count, latitude, longitude
        FROM households
        WHERE latitude BETWEEN 12.50 AND 13.20 AND longitude BETWEEN 120.50 AND 121.20
    ");
    $total_hh = 0; $total_pop = 0;
    foreach ($hh_stmt->fetchAll(PDO::FETCH_ASSOC) as $hh) {
        if (point_in_polygon((float)$hh['latitude'], (float)$hh['longitude'], $ring)) {
            $total_hh++;
            $total_pop += (int)$hh['family_members'];
        }
    }
    echo json_encode(['success'=>true, 'total_households'=>$total_hh, 'total_population'=>$total_pop]);
    exit;
}

// Helper to extract population from polygon
function compute_population_in_polygon($pdo, $polygon_geojson_str) {
    if (!$polygon_geojson_str) return null;
    $geojson = json_decode($polygon_geojson_str, true);
    if (!$geojson) return null;
    $geo = $geojson;
    if ($geojson['type'] === 'FeatureCollection' && !empty($geojson['features'])) $geo = $geojson['features'][0]['geometry'];
    elseif ($geojson['type'] === 'Feature') $geo = $geojson['geometry'];
    if (!isset($geo['type']) || $geo['type'] !== 'Polygon') return null;
    $ring = $geo['coordinates'][0];

    $hh_stmt = $pdo->query("SELECT family_members, latitude, longitude FROM households
        WHERE latitude BETWEEN 12.50 AND 13.20 AND longitude BETWEEN 120.50 AND 121.20");
    $total = 0;
    foreach ($hh_stmt->fetchAll(PDO::FETCH_ASSOC) as $hh) {
        if (point_in_polygon((float)$hh['latitude'], (float)$hh['longitude'], $ring)) {
            $total += (int)$hh['family_members'];
        }
    }
    return $total;
}

// Handle form submission
if ($_POST) {
    if (isset($_POST['submit_hazard'])) {
        $hazard_type_id  = $_POST['hazard_type_id'];
        $barangay_id     = ($_SESSION['role'] == 'barangay_staff') ? $_SESSION['barangay_id'] : $_POST['barangay_id'];
        $risk_level      = $_POST['risk_level'];
        $area_km2        = $_POST['area_km2'];
        $description     = $_POST['description'];
        $coordinates     = $_POST['coordinates'];
        $polygon_geojson = $_POST['polygon_geojson'] ?? null;

        // Auto-compute affected_population from polygon if drawn; else use manual entry
        if ($polygon_geojson) {
            $affected_population = compute_population_in_polygon($pdo, $polygon_geojson) ?? (int)$_POST['affected_population'];
        } else {
            $affected_population = (int)$_POST['affected_population'];
        }

        $stmt = $pdo->prepare("INSERT INTO hazard_zones
                              (hazard_type_id, barangay_id, risk_level, area_km2, affected_population, description, coordinates, polygon_geojson)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$hazard_type_id, $barangay_id, $risk_level, $area_km2, $affected_population, $description, $coordinates, $polygon_geojson]);

        $success = "Hazard zone added! Population in danger zone: " . number_format($affected_population);
    }

    // Update hazard zone
    if (isset($_POST['update_hazard'])) {
        $hazard_id       = $_POST['hazard_id'];
        $hazard_type_id  = $_POST['hazard_type_id'];
        $barangay_id     = ($_SESSION['role'] == 'barangay_staff') ? $_SESSION['barangay_id'] : $_POST['barangay_id'];
        $risk_level      = $_POST['risk_level'];
        $area_km2        = $_POST['area_km2'];
        $description     = $_POST['description'];
        $coordinates     = $_POST['coordinates'];
        $polygon_geojson = $_POST['polygon_geojson'] ?? null;

        if ($polygon_geojson) {
            $affected_population = compute_population_in_polygon($pdo, $polygon_geojson) ?? (int)$_POST['affected_population'];
        } else {
            $affected_population = (int)$_POST['affected_population'];
        }

        $stmt = $pdo->prepare("UPDATE hazard_zones
                              SET hazard_type_id = ?, barangay_id = ?, risk_level = ?, area_km2 = ?,
                                  affected_population = ?, description = ?, coordinates = ?,
                                  polygon_geojson = ?, updated_at = NOW()
                              WHERE id = ?");
        $stmt->execute([$hazard_type_id, $barangay_id, $risk_level, $area_km2, $affected_population, $description, $coordinates, $polygon_geojson, $hazard_id]);

        $success = "Hazard zone updated! Population in danger zone: " . number_format($affected_population);
    }
}

// Handle edit action - fetch hazard data for editing
$edit_hazard = null;
if (isset($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    
    if ($_SESSION['role'] == 'barangay_staff') {
        $barangay_id = $_SESSION['barangay_id'];
        $stmt = $pdo->prepare("
            SELECT hz.*, ht.name as hazard_name, b.name as barangay_name 
            FROM hazard_zones hz 
            JOIN hazard_types ht ON hz.hazard_type_id = ht.id 
            JOIN barangays b ON hz.barangay_id = b.id 
            WHERE hz.id = ? AND hz.barangay_id = ?
        ");
        $stmt->execute([$edit_id, $barangay_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT hz.*, ht.name as hazard_name, b.name as barangay_name 
            FROM hazard_zones hz 
            JOIN hazard_types ht ON hz.hazard_type_id = ht.id 
            JOIN barangays b ON hz.barangay_id = b.id 
            WHERE hz.id = ?
        ");
        $stmt->execute([$edit_id]);
    }
    
    $edit_hazard = $stmt->fetch();
    
    // If barangay staff tries to edit a hazard from another barangay, redirect
    if ($_SESSION['role'] == 'barangay_staff' && !$edit_hazard) {
        header('Location: hazard_data.php?error=access_denied');
        exit;
    }
}

// Handle delete action
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    
    if ($_SESSION['role'] == 'barangay_staff') {
        $barangay_id = $_SESSION['barangay_id'];
        // Check if the hazard belongs to the barangay staff's barangay
        $check_stmt = $pdo->prepare("SELECT id FROM hazard_zones WHERE id = ? AND barangay_id = ?");
        $check_stmt->execute([$delete_id, $barangay_id]);
        $hazard_exists = $check_stmt->fetch();
        
        if (!$hazard_exists) {
            header('Location: hazard_data.php?error=access_denied');
            exit;
        }
    }
    
    $stmt = $pdo->prepare("DELETE FROM hazard_zones WHERE id = ?");
    $stmt->execute([$delete_id]);
    
    header('Location: hazard_data.php?deleted=1');
    exit;
}

// Get data for dropdowns - restrict barangay staff to their barangay only
if ($_SESSION['role'] == 'barangay_staff') {
    $barangay_id = $_SESSION['barangay_id'];
    $stmt = $pdo->prepare("SELECT * FROM barangays WHERE id = ?");
    $stmt->execute([$barangay_id]);
    $barangays = $stmt->fetchAll();
} else {
    $barangays = $pdo->query("SELECT * FROM barangays")->fetchAll();
}

$hazard_types = $pdo->query("SELECT * FROM hazard_types")->fetchAll();

// Get hazard zones data - restrict barangay staff to their barangay only
if ($_SESSION['role'] == 'barangay_staff') {
    $barangay_id = $_SESSION['barangay_id'];
    $stmt = $pdo->prepare("
        SELECT hz.*, ht.name as hazard_name, ht.color, b.name as barangay_name 
        FROM hazard_zones hz 
        JOIN hazard_types ht ON hz.hazard_type_id = ht.id 
        JOIN barangays b ON hz.barangay_id = b.id 
        WHERE hz.barangay_id = ?
        ORDER BY hz.created_at DESC
    ");
    $stmt->execute([$barangay_id]);
    $hazard_zones = $stmt->fetchAll();
} else {
    $hazard_zones = $pdo->query("
        SELECT hz.*, ht.name as hazard_name, ht.color, b.name as barangay_name 
        FROM hazard_zones hz 
        JOIN hazard_types ht ON hz.hazard_type_id = ht.id 
        JOIN barangays b ON hz.barangay_id = b.id 
        ORDER BY hz.created_at DESC
    ")->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hazard Data - Sablayan Risk Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css">
    <style>
        .risk-badge-high { background-color: #dc3545; color: white; }
        .risk-badge-moderate { background-color: #ffc107; color: black; }
        .risk-badge-low { background-color: #28a745; color: white; }
        .risk-badge-not { background-color: #6c757d; color: white; }
        .risk-badge-generally { background-color: #17a2b8; color: white; }
        .risk-badge-peis { background-color: #6610f2; color: white; }
    </style>
    
    <style>
/* Export button styling */
#exportHouseholdRiskBtn {
    background: linear-gradient(145deg, #28a745, #218838);
    border: none;
    padding: 0.5rem 1.5rem;
    font-weight: 500;
    box-shadow: 0 4px 6px rgba(40, 167, 69, 0.2);
    transition: all 0.3s ease;
}

#exportHouseholdRiskBtn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(40, 167, 69, 0.3);
}

/* Modal styling */
.modal-header.bg-success {
    background: linear-gradient(135deg, #28a745, #20c997) !important;
}

.modal .card {
    border: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    transition: all 0.2s ease;
}

.modal .card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.modal .card-header {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
}

/* Form controls */
.modal .form-check {
    padding: 0.5rem;
    border-radius: 6px;
    transition: background-color 0.2s;
}

.modal .form-check:hover {
    background-color: #f8f9fa;
}

.modal .form-check-input:checked {
    background-color: #28a745;
    border-color: #28a745;
}

/* Format buttons */
.btn-group .btn-outline-success,
.btn-group .btn-outline-primary,
.btn-group .btn-outline-danger {
    padding: 0.75rem;
    font-weight: 500;
}

.btn-group .btn-check:checked + .btn-outline-success {
    background: linear-gradient(145deg, #28a745, #218838);
    border-color: #28a745;
}

/* Notification styling */
.alert {
    border: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Tooltip styling */
.tooltip .tooltip-inner {
    background-color: #28a745;
    padding: 0.5rem 1rem;
    font-size: 0.85rem;
}

.tooltip.bs-tooltip-top .tooltip-arrow::before {
    border-top-color: #28a745;
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
                    <h1 class="h2">Hazard Data Management</h1>
                </div>
                
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Hazard Data Management</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-success me-2" id="exportHouseholdRiskBtn" data-bs-toggle="tooltip" title="Export comprehensive household risk data">
            <i class="fas fa-file-excel me-2"></i>Export Household Risk Data
        </button>
    </div>
</div>

<!-- Export Options Modal -->
<div class="modal fade" id="exportOptionsModal" tabindex="-1" aria-labelledby="exportOptionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="exportOptionsModalLabel">
                    <i class="fas fa-file-excel me-2"></i>Export Household Risk Data
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            This will export all households with their member details and hazard risk assessments.
                        </div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Export Options</h6>
                            </div>
                            <div class="card-body">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="includeHouseholdHead" checked>
                                    <label class="form-check-label" for="includeHouseholdHead">
                                        <strong>Household Head Info</strong><br>
                                        <small class="text-muted">Name, age, gender, location</small>
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="includeMembers" checked>
                                    <label class="form-check-label" for="includeMembers">
                                        <strong>Family Members</strong><br>
                                        <small class="text-muted">Names, ages, relationships, vulnerabilities</small>
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="includeHazards" checked>
                                    <label class="form-check-label" for="includeHazards">
                                        <strong>Hazard Risks</strong><br>
                                        <small class="text-muted">All hazards affecting each household</small>
                                    </label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="includeVulnerability" checked>
                                    <label class="form-check-label" for="includeVulnerability">
                                        <strong>Vulnerability Summary</strong><br>
                                        <small class="text-muted">PWD, pregnant, senior, infant, minor counts</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Filter Options</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Filter by Barangay</label>
                                    <select class="form-select" id="exportBarangayFilter">
                                        <option value="">All Barangays</option>
                                        <?php foreach ($barangays as $barangay): ?>
                                            <option value="<?php echo $barangay['id']; ?>">
                                                <?php echo $barangay['name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Risk Level Filter</label>
                                    <select class="form-select" id="exportRiskFilter">
                                        <option value="">All Risk Levels</option>
                                        <option value="High">High Risk Only</option>
                                        <option value="Moderate">Moderate Risk Only</option>
                                        <option value="Low">Low Risk Only</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Date Range</label>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <input type="date" class="form-control" id="exportDateFrom" placeholder="From">
                                        </div>
                                        <div class="col-6">
                                            <input type="date" class="form-control" id="exportDateTo" placeholder="To">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="mb-3">Export Format</h6>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="exportFormat" id="formatExcel" value="excel" checked>
                                    <label class="btn btn-outline-success" for="formatExcel">
                                        <i class="fas fa-file-excel me-1"></i>Excel (CSV)
                                    </label>
                                    
                                    <!--<input type="radio" class="btn-check" name="exportFormat" id="formatJSON" value="json">-->
                                    <!--<label class="btn btn-outline-primary" for="formatJSON">-->
                                    <!--    <i class="fas fa-file-code me-1"></i>JSON-->
                                    <!--</label>-->
                                    
                                    <!--<input type="radio" class="btn-check" name="exportFormat" id="formatPDF" value="pdf" disabled>-->
                                    <!--<label class="btn btn-outline-danger" for="formatPDF">-->
                                    <!--    <i class="fas fa-file-pdf me-1"></i>PDF (Soon)-->
                                    <!--</label>-->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-success" id="confirmExport">
                    <i class="fas fa-download me-1"></i>Generate Export
                </button>
            </div>
        </div>
    </div>
</div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <?php if (isset($_GET['deleted'])): ?>
                    <div class="alert alert-success">Hazard zone deleted successfully!</div>
                <?php endif; ?>

                <?php if (isset($_GET['error']) && $_GET['error'] == 'access_denied'): ?>
                    <div class="alert alert-danger">Access denied. You can only manage hazards from your assigned barangay.</div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <?php echo $edit_hazard ? 'Edit Hazard Zone' : 'Add Hazard Zone'; ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <?php if ($edit_hazard): ?>
                                        <input type="hidden" name="hazard_id" value="<?php echo $edit_hazard['id']; ?>">
                                    <?php endif; ?>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Hazard Type</label>
                                        <select name="hazard_type_id" class="form-select" required>
                                            <option value="">Select Hazard Type</option>
                                            <?php foreach ($hazard_types as $type): ?>
                                                <option value="<?php echo $type['id']; ?>" 
                                                    <?php echo ($edit_hazard && $edit_hazard['hazard_type_id'] == $type['id']) ? 'selected' : ''; ?>>
                                                    <?php echo $type['name']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Barangay</label>
                                        <?php if ($_SESSION['role'] == 'barangay_staff'): ?>
                                            <?php 
                                            $barangay_id = $_SESSION['barangay_id'];
                                            $barangay_stmt = $pdo->prepare("SELECT name FROM barangays WHERE id = ?");
                                            $barangay_stmt->execute([$barangay_id]);
                                            $assigned_barangay = $barangay_stmt->fetch();
                                            ?>
                                            <input type="hidden" name="barangay_id" value="<?php echo $barangay_id; ?>">
                                            <input type="text" class="form-control" value="<?php echo $assigned_barangay['name']; ?>" readonly>
                                            <small class="text-muted">You can only add hazards to your assigned barangay.</small>
                                        <?php else: ?>
                                            <select name="barangay_id" class="form-select" required>
                                                <option value="">Select Barangay</option>
                                                <?php foreach ($barangays as $barangay): ?>
                                                    <option value="<?php echo $barangay['id']; ?>" 
                                                        <?php echo ($edit_hazard && $edit_hazard['barangay_id'] == $barangay['id']) ? 'selected' : ''; ?>>
                                                        <?php echo $barangay['name']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Risk Level</label>
                                        <select name="risk_level" id="riskLevelSelect" class="form-select" required>
                                            <option value="">Select Risk Level</option>
                                            <!-- Options will be populated by JavaScript based on hazard type -->
                                        </select>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Area (km²)</label>
                                                <input type="number" step="0.1" name="area_km2" class="form-control" 
                                                    value="<?php echo $edit_hazard ? $edit_hazard['area_km2'] : ''; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Estimated Affected Population</label>
                                                <input type="number" name="affected_population" class="form-control"
                                                    value="<?php echo $edit_hazard ? $edit_hazard['affected_population'] : ''; ?>"
                                                    placeholder="Risk assessment estimate">
                                                <small class="text-muted">Manual estimate based on risk assessment. Actual affected population from real events is computed in Incident Reports.</small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Hazard zone boundary polygon — draw to auto-compute population in danger zone -->
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">
                                            <i class="fas fa-draw-polygon text-primary me-1"></i>
                                            Danger Zone Polygon
                                        </label>
                                        <div class="alert alert-info py-2 small mb-2">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Draw the polygon boundary of this hazard zone. The system will automatically count all households whose GPS coordinates fall inside and compute the <strong>total population living in the danger zone</strong>.
                                            This is a risk assessment figure — for actual disaster events, use <a href="incident_reports.php" class="alert-link">Incident Reports</a>.
                                        </div>
                                        <div id="hazardMapPicker" style="height:260px; border:2px solid #dee2e6; border-radius:6px;"></div>
                                        <small class="text-muted">Use the polygon tool in the toolbar. Draw, then the population count updates automatically.</small>

                                        <!-- Live computation preview -->
                                        <div id="hazardPopResult" class="mt-2" style="display:none;">
                                            <div class="card border-primary">
                                                <div class="card-body py-2 small">
                                                    <i class="fas fa-users text-primary me-1"></i>
                                                    <strong>Households in zone:</strong> <span id="hzHH">0</span> &nbsp;|&nbsp;
                                                    <strong>Total population in danger zone:</strong> <span id="hzPop">0</span>
                                                    <small class="text-muted d-block">Will be saved as Estimated Affected Population.</small>
                                                </div>
                                            </div>
                                        </div>

                                        <input type="hidden" name="polygon_geojson" id="polygonGeoJSON"
                                            value="<?php echo $edit_hazard ? htmlspecialchars($edit_hazard['polygon_geojson'] ?? '') : ''; ?>">
                                        <input type="hidden" name="coordinates" id="hazardCoordinates"
                                            value="<?php echo $edit_hazard ? htmlspecialchars($edit_hazard['coordinates']) : ''; ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea name="description" class="form-control" rows="3"><?php echo $edit_hazard ? $edit_hazard['description'] : ''; ?></textarea>
                                    </div>

                                    <div class="d-flex gap-2">
                                        <?php if ($edit_hazard): ?>
                                            <button type="submit" name="update_hazard" class="btn btn-warning">
                                                <i class="fas fa-save me-2"></i>Update Hazard Data
                                            </button>
                                            <a href="hazard_data.php" class="btn btn-secondary">
                                                <i class="fas fa-times me-2"></i>Cancel
                                            </a>
                                        <?php else: ?>
                                            <button type="submit" name="submit_hazard" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i>Save Hazard Data
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    Hazard Zones 
                                    <?php if ($_SESSION['role'] == 'barangay_staff'): ?>
                                        - <?php echo $assigned_barangay['name']; ?>
                                    <?php endif; ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Hazard</th>
                                                <th>Barangay</th>
                                                <th>Risk Level</th>
                                                <th>Area</th>
                                                <!--<th>Affected</th>-->
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($hazard_zones as $zone): ?>
                                                <tr>
                                                    <td>
                                                        <?php 
                                                        // Map hazard type based on the images
                                                        $hazard_display = '';
                                                        switch(strtolower($zone['hazard_name'])) {
                                                            case 'flooding':
                                                                $hazard_display = 'Flood Hazard';
                                                                break;
                                                            case 'storm surge':
                                                                $hazard_display = 'Storm Surge';
                                                                break;
                                                            case 'tsunami':
                                                                $hazard_display = 'Tsunami Hazard';
                                                                break;
                                                            case 'liquefaction':
                                                                $hazard_display = 'Liquefaction';
                                                                break;
                                                            case 'ground shaking':
                                                                $hazard_display = 'Ground Shaking';
                                                                break;
                                                            case 'landslide':
                                                                $hazard_display = 'Landslide Hazard';
                                                                break;
                                                            default:
                                                                $hazard_display = $zone['hazard_name'];
                                                        }
                                                        echo $hazard_display;
                                                        ?>
                                                    </td>
                                                    <td><?php echo $zone['barangay_name']; ?></td>
                                                    <td>
                                                        <?php
                                                        $risk_class = '';
                                                        if (strpos($zone['risk_level'], 'High') !== false) {
                                                            $risk_class = 'risk-badge-high';
                                                        } elseif (strpos($zone['risk_level'], 'Moderate') !== false) {
                                                            $risk_class = 'risk-badge-moderate';
                                                        } elseif (strpos($zone['risk_level'], 'Low') !== false) {
                                                            $risk_class = 'risk-badge-low';
                                                        } elseif (strpos($zone['risk_level'], 'Not') !== false) {
                                                            $risk_class = 'risk-badge-not';
                                                        } elseif (strpos($zone['risk_level'], 'Generally') !== false) {
                                                            $risk_class = 'risk-badge-generally';
                                                        } elseif (strpos($zone['risk_level'], 'PEIS') !== false) {
                                                            $risk_class = 'risk-badge-peis';
                                                        } elseif (strpos($zone['risk_level'], 'Prone') !== false) {
                                                            $risk_class = 'risk-badge-high';
                                                        } elseif (strpos($zone['risk_level'], 'General Inundation') !== false) {
                                                            $risk_class = 'risk-badge-moderate';
                                                        } else {
                                                            $risk_class = 'bg-secondary text-white';
                                                        }
                                                        ?>
                                                        <span class="badge <?php echo $risk_class; ?> p-2">
                                                            <?php echo $zone['risk_level']; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $zone['area_km2']; ?> km²</td>
                                                    <!--<td><?php echo number_format($zone['affected_population']); ?></td>-->
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="hazard_data.php?edit_id=<?php echo $zone['id']; ?>" 
                                                               class="btn btn-outline-primary" title="Edit">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <button type="button" class="btn btn-outline-info view-households-btn" 
                                                                    data-id="<?php echo $zone['id']; ?>"
                                                                    data-hazard="<?php echo $hazard_display; ?>"
                                                                    data-barangay="<?php echo $zone['barangay_name']; ?>"
                                                                    data-risk="<?php echo $zone['risk_level']; ?>"
                                                                    data-coordinates="<?php echo $zone['coordinates']; ?>"
                                                                    data-area="<?php echo $zone['area_km2']; ?>"
                                                                    title="View Affected Households">
                                                                <i class="fas fa-users"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-outline-danger delete-btn" 
                                                                    data-id="<?php echo $zone['id']; ?>" 
                                                                    data-name="<?php echo $hazard_display . ' - ' . $zone['barangay_name']; ?>"
                                                                    title="Delete">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Hazard Statistics Dashboard -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-pie me-2"></i>Hazard Susceptibility Dashboard
                </h5>
            </div>
            <div class="card-body">
                <!-- Hazard Type Tabs -->
                <ul class="nav nav-tabs mb-4" id="hazardTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="flood-tab" data-bs-toggle="tab" data-bs-target="#flood" type="button" role="tab">
                            <i class="fas fa-water me-2"></i>Flood
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="storm-tab" data-bs-toggle="tab" data-bs-target="#storm" type="button" role="tab">
                            <i class="fas fa-wind me-2"></i>Storm Surge
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tsunami-tab" data-bs-toggle="tab" data-bs-target="#tsunami" type="button" role="tab">
                            <i class="fas fa-water me-2"></i>Tsunami
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="liquefaction-tab" data-bs-toggle="tab" data-bs-target="#liquefaction" type="button" role="tab">
                            <i class="fas fa-mud me-2"></i>Liquefaction
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="groundshaking-tab" data-bs-toggle="tab" data-bs-target="#groundshaking" type="button" role="tab">
                            <i class="fas fa-hill-rockslide me-2"></i>Ground Shaking
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="landslide-tab" data-bs-toggle="tab" data-bs-target="#landslide" type="button" role="tab">
                            <i class="fas fa-mountain me-2"></i>Landslide
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="hazardTabContent">
<?php
// Get statistics for each hazard type - UPDATED TO USE ACTUAL HOUSEHOLD DATA
$hazardStats = [];
$hazardTypeNames = [];

// Get hazard type IDs and names
$hazardTypes = $pdo->query("SELECT id, name FROM hazard_types")->fetchAll();
foreach ($hazardTypes as $type) {
    $hazardTypeNames[$type['id']] = $type['name'];
}

// Get all hazard zones with their coordinates and area
$allHazardZones = $pdo->query("
    SELECT 
        hz.*,
        ht.name as hazard_name,
        b.name as barangay_name
    FROM hazard_zones hz
    JOIN hazard_types ht ON hz.hazard_type_id = ht.id
    JOIN barangays b ON hz.barangay_id = b.id
")->fetchAll();

// Initialize stats array
foreach ($hazardTypes as $type) {
    $hazardStats[$type['name']] = [];
}

// For each hazard zone, calculate actual affected households
foreach ($allHazardZones as $zone) {
    if (empty($zone['coordinates'])) continue;
    
    // Parse hazard coordinates
    $coords = explode(',', $zone['coordinates']);
    if (count($coords) < 2) continue;
    
    $hazardLat = floatval(trim($coords[0]));
    $hazardLng = floatval(trim($coords[1]));
    $areaKm2 = floatval($zone['area_km2']);
    $barangayName = $zone['barangay_name'];
    
    // Calculate radius from area (assuming circular area)
    $radiusKm = $areaKm2 > 0 ? sqrt($areaKm2 / M_PI) : 1;
    
    // Get barangay ID
    $barangayStmt = $pdo->prepare("SELECT id FROM barangays WHERE name = ?");
    $barangayStmt->execute([$barangayName]);
    $barangay = $barangayStmt->fetch();
    if (!$barangay) continue;
    
    $barangayId = $barangay['id'];
    
    // Query to get affected households within radius
    $householdQuery = "
        SELECT 
            SUM(family_members) as total_members,
            COUNT(*) as household_count,
            SUM(pwd_count) as total_pwd,
            SUM(pregnant_count) as total_pregnant,
            SUM(senior_count) as total_senior,
            SUM(infant_count) as total_infant,
            SUM(minor_count) as total_minor
        FROM (
            SELECT 
                family_members,
                pwd_count,
                pregnant_count,
                senior_count,
                infant_count,
                minor_count,
                (
                    6371 * ACOS(
                        COS(RADIANS(?)) * 
                        COS(RADIANS(latitude)) * 
                        COS(RADIANS(longitude) - RADIANS(?)) + 
                        SIN(RADIANS(?)) * 
                        SIN(RADIANS(latitude))
                    )
                ) AS distance_km
            FROM households 
            WHERE barangay_id = ? 
                AND latitude IS NOT NULL 
                AND longitude IS NOT NULL
                AND latitude != 0 
                AND longitude != 0
        ) AS subquery
        WHERE distance_km <= ? OR distance_km IS NULL OR distance_km = 0
    ";
    
    $stmt = $pdo->prepare($householdQuery);
    $stmt->execute([$hazardLat, $hazardLng, $hazardLat, $barangayId, $radiusKm]);
    $affectedData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $totalAffected = intval($affectedData['total_members'] ?? 0);
    $hazardName = $zone['hazard_name'];
    $riskLevel = $zone['risk_level'];
    
    // Add to stats
    if (!isset($hazardStats[$hazardName][$riskLevel])) {
        $hazardStats[$hazardName][$riskLevel] = [
            'total_affected' => 0,
            'household_count' => 0,
            'total_pwd' => 0,
            'total_pregnant' => 0,
            'total_senior' => 0,
            'total_infant' => 0,
            'total_minor' => 0
        ];
    }
    
    $hazardStats[$hazardName][$riskLevel]['total_affected'] += $totalAffected;
    $hazardStats[$hazardName][$riskLevel]['household_count'] += intval($affectedData['household_count'] ?? 0);
    $hazardStats[$hazardName][$riskLevel]['total_pwd'] += intval($affectedData['total_pwd'] ?? 0);
    $hazardStats[$hazardName][$riskLevel]['total_pregnant'] += intval($affectedData['total_pregnant'] ?? 0);
    $hazardStats[$hazardName][$riskLevel]['total_senior'] += intval($affectedData['total_senior'] ?? 0);
    $hazardStats[$hazardName][$riskLevel]['total_infant'] += intval($affectedData['total_infant'] ?? 0);
    $hazardStats[$hazardName][$riskLevel]['total_minor'] += intval($affectedData['total_minor'] ?? 0);
}

// Calculate totals for each hazard type
function calculateTotals($stats) {
    $totals = [];
    $grandTotal = 0;
    $demographics = [
        'households' => 0,
        'pwd' => 0,
        'pregnant' => 0,
        'senior' => 0,
        'infant' => 0,
        'minor' => 0
    ];
    
    foreach ($stats as $riskLevel => $data) {
        $totals[$riskLevel] = $data['total_affected'];
        $grandTotal += $data['total_affected'];
        $demographics['households'] += $data['household_count'];
        $demographics['pwd'] += $data['total_pwd'];
        $demographics['pregnant'] += $data['total_pregnant'];
        $demographics['senior'] += $data['total_senior'];
        $demographics['infant'] += $data['total_infant'];
        $demographics['minor'] += $data['total_minor'];
    }
    
    return ['totals' => $totals, 'grandTotal' => $grandTotal, 'demographics' => $demographics];
}

// Calculate stats for each hazard type
$floodStats = calculateTotals($hazardStats['Flooding'] ?? []);
$stormStats = calculateTotals($hazardStats['Storm Surge'] ?? []);
$tsunamiStats = calculateTotals($hazardStats['Tsunami'] ?? []);
$liquefactionStats = calculateTotals($hazardStats['Liquefaction'] ?? []);
$groundShakingStats = calculateTotals($hazardStats['Ground Shaking'] ?? []);
$landslideStats = calculateTotals($hazardStats['Landslide'] ?? []);

// Get manually entered affected population for comparison
$manualStats = [];
foreach ($hazardTypes as $type) {
    $stmt = $pdo->prepare("
        SELECT 
            risk_level,
            SUM(affected_population) as total_affected,
            COUNT(*) as zone_count
        FROM hazard_zones 
        WHERE hazard_type_id = ?
        GROUP BY risk_level
    ");
    $stmt->execute([$type['id']]);
    $manualStats[$type['name']] = $stmt->fetchAll();
}
?>
                    
                    <!-- Flood Tab -->
                    <div class="tab-pane fade show active" id="flood" role="tabpanel">
                        <div class="row">
                            <div class="col-md-8">
                                <canvas id="floodChart" style="max-height: 400px;"></canvas>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">Flood Susceptibility</h6>
                                        <?php
                                        $floodData = [
                                            'High Susceptible' => $floodStats['totals']['High Susceptible'] ?? 0,
                                            'Moderate Susceptible' => $floodStats['totals']['Moderate Susceptible'] ?? 0,
                                            'Low Susceptible' => $floodStats['totals']['Low Susceptible'] ?? 0,
                                            'Not Susceptible' => $floodStats['totals']['Not Susceptible'] ?? 0
                                        ];
                                        $floodTotal = array_sum($floodData);
                                        ?>
                                        <div class="list-group list-group-flush">
                                            <?php foreach ($floodData as $level => $count): ?>
                                                <?php if ($count > 0): ?>
                                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                                        <?php echo $level; ?>
                                                        <div>
                                                            <span class="badge bg-primary rounded-pill me-2"><?php echo number_format($count); ?></span>
                                                            <span class="badge bg-secondary rounded-pill"><?php echo $floodTotal > 0 ? round(($count / $floodTotal) * 100) : 0; ?>%</span>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="mt-3">
                                            <strong>Total Affected: <?php echo number_format($floodTotal); ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Storm Surge Tab -->
                    <div class="tab-pane fade" id="storm" role="tabpanel">
                        <div class="row">
                            <div class="col-md-8">
                                <canvas id="stormChart" style="max-height: 400px;"></canvas>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">Storm Surge Susceptibility</h6>
                                        <?php
                                        $stormData = [
                                            'Prone' => $stormStats['totals']['Prone'] ?? 0,
                                            'Not Susceptible' => $stormStats['totals']['Not Susceptible'] ?? 0
                                        ];
                                        $stormTotal = array_sum($stormData);
                                        ?>
                                        <div class="list-group list-group-flush">
                                            <?php foreach ($stormData as $level => $count): ?>
                                                <?php if ($count > 0): ?>
                                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                                        <?php echo $level; ?>
                                                        <div>
                                                            <span class="badge bg-primary rounded-pill me-2"><?php echo number_format($count); ?></span>
                                                            <span class="badge bg-secondary rounded-pill"><?php echo $stormTotal > 0 ? round(($count / $stormTotal) * 100) : 0; ?>%</span>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="mt-3">
                                            <strong>Total Affected: <?php echo number_format($stormTotal); ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tsunami Tab -->
                    <div class="tab-pane fade" id="tsunami" role="tabpanel">
                        <div class="row">
                            <div class="col-md-8">
                                <canvas id="tsunamiChart" style="max-height: 400px;"></canvas>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">Tsunami Susceptibility</h6>
                                        <?php
                                        $tsunamiData = [
                                            'General Inundation' => $tsunamiStats['totals']['General Inundation'] ?? 0,
                                            'Not Susceptible' => $tsunamiStats['totals']['Not Susceptible'] ?? 0
                                        ];
                                        $tsunamiTotal = array_sum($tsunamiData);
                                        ?>
                                        <div class="list-group list-group-flush">
                                            <?php foreach ($tsunamiData as $level => $count): ?>
                                                <?php if ($count > 0): ?>
                                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                                        <?php echo $level; ?>
                                                        <div>
                                                            <span class="badge bg-primary rounded-pill me-2"><?php echo number_format($count); ?></span>
                                                            <span class="badge bg-secondary rounded-pill"><?php echo $tsunamiTotal > 0 ? round(($count / $tsunamiTotal) * 100) : 0; ?>%</span>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="mt-3">
                                            <strong>Total Affected: <?php echo number_format($tsunamiTotal); ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Liquefaction Tab -->
                    <div class="tab-pane fade" id="liquefaction" role="tabpanel">
                        <div class="row">
                            <div class="col-md-8">
                                <canvas id="liquefactionChart" style="max-height: 400px;"></canvas>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">Liquefaction Susceptibility</h6>
                                        <?php
                                        $liquefactionData = [
                                            'High Susceptible' => $liquefactionStats['totals']['High Susceptible'] ?? 0,
                                            'Moderate Susceptible' => $liquefactionStats['totals']['Moderate Susceptible'] ?? 0,
                                            'Generally Susceptible' => $liquefactionStats['totals']['Generally Susceptible'] ?? 0,
                                            'Low Susceptible' => $liquefactionStats['totals']['Low Susceptible'] ?? 0,
                                            'Not Susceptible' => $liquefactionStats['totals']['Not Susceptible'] ?? 0
                                        ];
                                        $liquefactionTotal = array_sum($liquefactionData);
                                        ?>
                                        <div class="list-group list-group-flush">
                                            <?php foreach ($liquefactionData as $level => $count): ?>
                                                <?php if ($count > 0): ?>
                                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                                        <?php echo $level; ?>
                                                        <div>
                                                            <span class="badge bg-primary rounded-pill me-2"><?php echo number_format($count); ?></span>
                                                            <span class="badge bg-secondary rounded-pill"><?php echo $liquefactionTotal > 0 ? round(($count / $liquefactionTotal) * 100) : 0; ?>%</span>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="mt-3">
                                            <strong>Total Affected: <?php echo number_format($liquefactionTotal); ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Ground Shaking Tab -->
                    <div class="tab-pane fade" id="groundshaking" role="tabpanel">
                        <div class="row">
                            <div class="col-md-8">
                                <canvas id="groundShakingChart" style="max-height: 400px;"></canvas>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">Ground Shaking Susceptibility</h6>
                                        <?php
                                        $groundShakingData = [
                                            'PEIS VIII - Very destructive to devastating ground shaking' => $groundShakingStats['totals']['PEIS VIII - Very destructive to devastating ground shaking'] ?? 0,
                                            'PEIS VII - Destructive ground shaking' => $groundShakingStats['totals']['PEIS VII - Destructive ground shaking'] ?? 0,
                                            'Not Susceptible' => $groundShakingStats['totals']['Not Susceptible'] ?? 0
                                        ];
                                        $groundShakingTotal = array_sum($groundShakingData);
                                        ?>
                                        <div class="list-group list-group-flush">
                                            <?php foreach ($groundShakingData as $level => $count): ?>
                                                <?php if ($count > 0): ?>
                                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                                        <?php echo $level; ?>
                                                        <div>
                                                            <span class="badge bg-primary rounded-pill me-2"><?php echo number_format($count); ?></span>
                                                            <span class="badge bg-secondary rounded-pill"><?php echo $groundShakingTotal > 0 ? round(($count / $groundShakingTotal) * 100) : 0; ?>%</span>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="mt-3">
                                            <strong>Total Affected: <?php echo number_format($groundShakingTotal); ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Landslide Tab -->
                    <div class="tab-pane fade" id="landslide" role="tabpanel">
                        <div class="row">
                            <div class="col-md-8">
                                <canvas id="landslideChart" style="max-height: 400px;"></canvas>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">Landslide Susceptibility</h6>
                                        <?php
                                        $landslideData = [
                                            'High Susceptible' => $landslideStats['totals']['High Susceptible'] ?? 0,
                                            'Moderate Susceptible' => $landslideStats['totals']['Moderate Susceptible'] ?? 0,
                                            'Low Susceptible' => $landslideStats['totals']['Low Susceptible'] ?? 0,
                                            'Not Susceptible' => $landslideStats['totals']['Not Susceptible'] ?? 0
                                        ];
                                        $landslideTotal = array_sum($landslideData);
                                        ?>
                                        <div class="list-group list-group-flush">
                                            <?php foreach ($landslideData as $level => $count): ?>
                                                <?php if ($count > 0): ?>
                                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                                        <?php echo $level; ?>
                                                        <div>
                                                            <span class="badge bg-primary rounded-pill me-2"><?php echo number_format($count); ?></span>
                                                            <span class="badge bg-secondary rounded-pill"><?php echo $landslideTotal > 0 ? round(($count / $landslideTotal) * 100) : 0; ?>%</span>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="mt-3">
                                            <strong>Total Affected: <?php echo number_format($landslideTotal); ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<!-- Add Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Store chart instances
    const charts = {};
    
    // Function to initialize a chart
    function initChart(chartId, chartConfig) {
        const canvas = document.getElementById(chartId);
        if (!canvas) return null;
        
        // Check if chart already exists and destroy it
        if (charts[chartId]) {
            charts[chartId].destroy();
        }
        
        // Create new chart
        charts[chartId] = new Chart(canvas, chartConfig);
        return charts[chartId];
    }
    
    // Chart configurations (will be initialized when tab is shown)
    const chartConfigs = {
// Flood Chart
floodChart: {
    type: 'doughnut',
    data: {
        labels: ['High Susceptible', 'Moderate Susceptible', 'Low Susceptible', 'Not Susceptible'],
        datasets: [{
            data: [
                <?php echo $floodStats['totals']['High Susceptible'] ?? 0; ?>,
                <?php echo $floodStats['totals']['Moderate Susceptible'] ?? 0; ?>,
                <?php echo $floodStats['totals']['Low Susceptible'] ?? 0; ?>,
                <?php echo $floodStats['totals']['Not Susceptible'] ?? 0; ?>
            ],
            backgroundColor: ['#dc3545', '#ffc107', '#28a745', '#6c757d'],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { position: 'bottom' },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.label || '';
                        let value = context.raw || 0;
                        let total = context.dataset.data.reduce((a, b) => a + b, 0);
                        let percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                        return `${label}: ${value.toLocaleString()} affected (${percentage}%)`;
                    }
                }
            }
        }
    }
},

// Storm Surge Chart
stormChart: {
    type: 'doughnut',
    data: {
        labels: ['Prone', 'Not Susceptible'],
        datasets: [{
            data: [
                <?php echo $stormStats['totals']['Prone'] ?? 0; ?>,
                <?php echo $stormStats['totals']['Not Susceptible'] ?? 0; ?>
            ],
            backgroundColor: ['#dc3545', '#6c757d'],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { position: 'bottom' },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.label || '';
                        let value = context.raw || 0;
                        let total = context.dataset.data.reduce((a, b) => a + b, 0);
                        let percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                        return `${label}: ${value.toLocaleString()} affected (${percentage}%)`;
                    }
                }
            }
        }
    }
},

// Tsunami Chart
tsunamiChart: {
    type: 'doughnut',
    data: {
        labels: ['General Inundation', 'Not Susceptible'],
        datasets: [{
            data: [
                <?php echo $tsunamiStats['totals']['General Inundation'] ?? 0; ?>,
                <?php echo $tsunamiStats['totals']['Not Susceptible'] ?? 0; ?>
            ],
            backgroundColor: ['#17a2b8', '#6c757d'],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { position: 'bottom' },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.label || '';
                        let value = context.raw || 0;
                        let total = context.dataset.data.reduce((a, b) => a + b, 0);
                        let percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                        return `${label}: ${value.toLocaleString()} affected (${percentage}%)`;
                    }
                }
            }
        }
    }
},

// Liquefaction Chart
liquefactionChart: {
    type: 'doughnut',
    data: {
        labels: ['High Susceptible', 'Moderate Susceptible', 'Generally Susceptible', 'Low Susceptible', 'Not Susceptible'],
        datasets: [{
            data: [
                <?php echo $liquefactionStats['totals']['High Susceptible'] ?? 0; ?>,
                <?php echo $liquefactionStats['totals']['Moderate Susceptible'] ?? 0; ?>,
                <?php echo $liquefactionStats['totals']['Generally Susceptible'] ?? 0; ?>,
                <?php echo $liquefactionStats['totals']['Low Susceptible'] ?? 0; ?>,
                <?php echo $liquefactionStats['totals']['Not Susceptible'] ?? 0; ?>
            ],
            backgroundColor: ['#dc3545', '#ffc107', '#17a2b8', '#28a745', '#6c757d'],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { position: 'bottom' },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.label || '';
                        let value = context.raw || 0;
                        let total = context.dataset.data.reduce((a, b) => a + b, 0);
                        let percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                        return `${label}: ${value.toLocaleString()} affected (${percentage}%)`;
                    }
                }
            }
        }
    }
},

// Ground Shaking Chart
groundShakingChart: {
    type: 'doughnut',
    data: {
        labels: ['PEIS VIII', 'PEIS VII', 'Not Susceptible'],
        datasets: [{
            data: [
                <?php echo $groundShakingStats['totals']['PEIS VIII - Very destructive to devastating ground shaking'] ?? 0; ?>,
                <?php echo $groundShakingStats['totals']['PEIS VII - Destructive ground shaking'] ?? 0; ?>,
                <?php echo $groundShakingStats['totals']['Not Susceptible'] ?? 0; ?>
            ],
            backgroundColor: ['#8e44ad', '#9b59b6', '#6c757d'],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { position: 'bottom' },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.label || '';
                        let value = context.raw || 0;
                        let total = context.dataset.data.reduce((a, b) => a + b, 0);
                        let percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                        return `${label}: ${value.toLocaleString()} affected (${percentage}%)`;
                    }
                }
            }
        }
    }
},

// Landslide Chart
landslideChart: {
    type: 'doughnut',
    data: {
        labels: ['High Susceptible', 'Moderate Susceptible', 'Low Susceptible', 'Not Susceptible'],
        datasets: [{
            data: [
                <?php echo $landslideStats['totals']['High Susceptible'] ?? 0; ?>,
                <?php echo $landslideStats['totals']['Moderate Susceptible'] ?? 0; ?>,
                <?php echo $landslideStats['totals']['Low Susceptible'] ?? 0; ?>,
                <?php echo $landslideStats['totals']['Not Susceptible'] ?? 0; ?>
            ],
            backgroundColor: ['#dc3545', '#ffc107', '#28a745', '#6c757d'],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { position: 'bottom' },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.label || '';
                        let value = context.raw || 0;
                        let total = context.dataset.data.reduce((a, b) => a + b, 0);
                        let percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                        return `${label}: ${value.toLocaleString()} affected (${percentage}%)`;
                    }
                }
            }
        }
    }
}
    };
    
    // Initialize first chart (flood) immediately
    setTimeout(() => {
        initChart('floodChart', chartConfigs.floodChart);
    }, 100);
    
    // Handle tab changes
    const tabEl = document.querySelectorAll('button[data-bs-toggle="tab"]');
    tabEl.forEach(tab => {
        tab.addEventListener('shown.bs.tab', function (event) {
            const targetId = event.target.getAttribute('data-bs-target').replace('#', '');
            
            // Map tab IDs to chart IDs
            const chartMap = {
                'flood': 'floodChart',
                'storm': 'stormChart',
                'tsunami': 'tsunamiChart',
                'liquefaction': 'liquefactionChart',
                'groundshaking': 'groundShakingChart',
                'landslide': 'landslideChart'
            };
            
            const chartId = chartMap[targetId];
            if (chartId && chartConfigs[chartId]) {
                // Small delay to ensure the tab is fully visible
                setTimeout(() => {
                    initChart(chartId, chartConfigs[chartId]);
                }, 50);
            }
        });
    });
    
    // Handle window resize
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            // Find which tab is active
            const activeTab = document.querySelector('.tab-pane.active');
            if (activeTab) {
                const activeId = activeTab.id;
                const chartMap = {
                    'flood': 'floodChart',
                    'storm': 'stormChart',
                    'tsunami': 'tsunamiChart',
                    'liquefaction': 'liquefactionChart',
                    'groundshaking': 'groundShakingChart',
                    'landslide': 'landslideChart'
                };
                
                const chartId = chartMap[activeId];
                if (chartId && chartConfigs[chartId]) {
                    initChart(chartId, chartConfigs[chartId]);
                }
            }
        }, 250);
    });
});
</script>

<style>
/* Additional styles for the dashboard */
.nav-tabs .nav-link {
    color: #495057;
    font-weight: 500;
}

.nav-tabs .nav-link.active {
    color: #007bff;
    font-weight: 600;
}

.list-group-item {
    background-color: transparent;
    border: none;
    padding: .5rem 0;
}

.bg-purple {
    background-color: #8e44ad !important;
}

.card-header.bg-primary {
    background: linear-gradient(45deg, #007bff, #0056b3) !important;
}
</style>

<style>
/* Chart container styles */
.tab-pane canvas {
    max-height: 400px;
    width: 100% !important;
    height: auto !important;
    display: block;
}

.tab-pane.active {
    display: block !important;
}

.tab-pane {
    min-height: 450px;
}

/* Ensure the chart canvas maintains aspect ratio */
.tab-pane .row {
    align-items: center;
}

@media (max-width: 768px) {
    .tab-pane canvas {
        max-height: 300px;
        margin-bottom: 20px;
    }
}
</style>

<!-- View Households Modal -->
<div class="modal fade" id="householdsModal" tabindex="-1" aria-labelledby="householdsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="householdsModalLabel">
                    <i class="fas fa-users me-2"></i>Affected Households
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-8">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title">Hazard Information</h6>
                                <div class="row">
                                    <div class="col-md-3">
                                        <strong>Hazard Type:</strong> <span id="modalHazardType"></span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Barangay:</strong> <span id="modalBarangay"></span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Risk Level:</strong> <span id="modalRiskLevel"></span>
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Area:</strong> <span id="modalArea"></span> km²
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h6 class="card-title">Total Affected</h6>
                                <h3 id="totalAffectedCount">0</h3>
                                <small>Households</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary Statistics -->
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Demographic Summary</h6>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-md-2">
                                        <div class="border rounded p-2">
                                            <strong>Total Members</strong>
                                            <h4 id="totalMembers">0</h4>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="border rounded p-2 bg-warning text-dark">
                                            <strong>PWD</strong>
                                            <h4 id="totalPWD">0</h4>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="border rounded p-2 bg-danger text-white">
                                            <strong>Pregnant</strong>
                                            <h4 id="totalPregnant">0</h4>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="border rounded p-2 bg-primary text-white">
                                            <strong>Senior</strong>
                                            <h4 id="totalSenior">0</h4>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="border rounded p-2 bg-info text-white">
                                            <strong>Infant</strong>
                                            <h4 id="totalInfant">0</h4>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="border rounded p-2 bg-secondary text-white">
                                            <strong>Minor</strong>
                                            <h4 id="totalMinor">0</h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Households Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-hover" id="householdsTable">
                <!-- In the householdsTable thead section, update to: -->
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Household Head</th>
                        <th>Gender</th>
                        <th>Age</th>
                        <th>House Type</th>
                        <th>Family Members</th>
                        <th>PWD</th>
                        <th>Pregnant</th>
                        <th>Senior</th>
                        <th>Infant</th>
                        <th>Minor</th>
                        <th>Coordinates</th>
                        <th>Distance</th>
                    </tr>
                </thead>
                        <tbody id="householdsTableBody">
                            <tr>
                                <td colspan="12" class="text-center">Loading households...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" id="exportAffectedBtn">
                    <i class="fas fa-download me-1"></i>Export List
                </button>
            </div>
        </div>
    </div>
</div>
                
            </main>
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
                    <p>Are you sure you want to delete the hazard zone: <strong id="deleteHazardName"></strong>?</p>
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
            const barangaySelect = document.querySelector('select[name="barangay_id"]');
            const coordinatesInput = document.querySelector('input[name="coordinates"]');
            
            // Create a mapping of barangay IDs to coordinates
            const barangayCoordinates = {
                <?php foreach ($barangays as $barangay): ?>
                    <?php echo $barangay['id']; ?>: '<?php echo $barangay['coordinates'] ?? ''; ?>',
                <?php endforeach; ?>
            };
            
            // Auto-fill coordinates for barangay staff (they only have one barangay)
            <?php if ($_SESSION['role'] == 'barangay_staff'): ?>
                const barangayId = <?php echo $_SESSION['barangay_id']; ?>;
                if (barangayCoordinates[barangayId]) {
                    coordinatesInput.value = barangayCoordinates[barangayId];
                }
            <?php else: ?>
                // Add event listener to barangay select for admin users
                if (barangaySelect) {
                    barangaySelect.addEventListener('change', function() {
                        const selectedBarangayId = this.value;
                        
                        if (selectedBarangayId && barangayCoordinates[selectedBarangayId]) {
                            // Auto-fill coordinates if available
                            coordinatesInput.value = barangayCoordinates[selectedBarangayId];
                        } else {
                            // Clear coordinates if no barangay selected or no coordinates available
                            coordinatesInput.value = '';
                        }
                    });
                    
                    // Also trigger change event on page load if a barangay is already selected
                    if (barangaySelect.value) {
                        barangaySelect.dispatchEvent(new Event('change'));
                    }
                }
            <?php endif; ?>

            // Delete confirmation modal
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            const deleteHazardName = document.getElementById('deleteHazardName');
            const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
            
            document.querySelectorAll('.delete-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const hazardId = this.getAttribute('data-id');
                    const hazardName = this.getAttribute('data-name');
                    
                    deleteHazardName.textContent = hazardName;
                    confirmDeleteBtn.href = `hazard_data.php?delete_id=${hazardId}`;
                    deleteModal.show();
                });
            });
        });
    </script>
    
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const hazardTypeSelect = document.querySelector('select[name="hazard_type_id"]');
    const riskLevelSelect = document.getElementById('riskLevelSelect');
    
    // Risk level mappings for each hazard type
    const riskLevels = {
        // Flooding
        'Flooding': [
            'High Susceptible',
            'Moderate Susceptible', 
            'Low Susceptible',
            'Not Susceptible'
        ],
        // Storm Surge
        'Storm Surge': [
            'Prone',
            'Not Susceptible'
        ],
        // Tsunami
        'Tsunami': [
            'General Inundation',
            'Not Susceptible'
        ],
        // Liquefaction
        'Liquefaction': [
            'Generally Susceptible',
            'High Susceptible',
            'Low Susceptible',
            'Moderate Susceptible',
            'Not Susceptible'
        ],
        // Ground Shaking
        'Ground Shaking': [
            'Not Susceptible',
            'PEIS VII - Destructive ground shaking',
            'PEIS VIII - Very destructive to devastating ground shaking'
        ],
        // Landslide
        'Landslide': [
            'High Susceptible',
            'Moderate Susceptible',
            'Low Susceptible',
            'Not Susceptible'
        ]
    };
    
    // Function to update risk level dropdown
    function updateRiskLevels() {
        const selectedOption = hazardTypeSelect.options[hazardTypeSelect.selectedIndex];
        const hazardType = selectedOption ? selectedOption.text : '';
        
        // Clear current options
        riskLevelSelect.innerHTML = '<option value="">Select Risk Level</option>';
        
        // Get risk levels for selected hazard type
        const levels = riskLevels[hazardType] || [];
        
        // Add new options
        levels.forEach(level => {
            const option = document.createElement('option');
            option.value = level;
            option.textContent = level;
            
            // If editing, preselect the current value
            <?php if ($edit_hazard): ?>
            if (level === '<?php echo $edit_hazard['risk_level']; ?>') {
                option.selected = true;
            }
            <?php endif; ?>
            
            riskLevelSelect.appendChild(option);
        });
        
        // If no options found for this hazard type, add all options as fallback
        if (levels.length === 0) {
            const allLevels = [
                'High Susceptible',
                'Moderate Susceptible',
                'Low Susceptible', 
                'Not Susceptible',
                'Prone',
                'Generally Susceptible',
                'PEIS VIII - Very destructive to devastating ground shaking',
                'PEIS VII - Destructive ground shaking',
                'General Inundation'
            ];
            
            allLevels.forEach(level => {
                const option = document.createElement('option');
                option.value = level;
                option.textContent = level;
                
                <?php if ($edit_hazard): ?>
                if (level === '<?php echo $edit_hazard['risk_level']; ?>') {
                    option.selected = true;
                }
                <?php endif; ?>
                
                riskLevelSelect.appendChild(option);
            });
        }
    }
    
    // Update when hazard type changes
    hazardTypeSelect.addEventListener('change', updateRiskLevels);
    
    // Initial update if a hazard type is already selected (for edit mode)
    <?php if ($edit_hazard): ?>
    // Wait a bit for the hazard type to be selected by PHP
    setTimeout(() => {
        // Find and select the hazard type that matches the edited hazard
        const options = hazardTypeSelect.options;
        for (let i = 0; i < options.length; i++) {
            <?php
            // Get the hazard type name for the edited hazard
            $stmt = $pdo->prepare("SELECT name FROM hazard_types WHERE id = ?");
            $stmt->execute([$edit_hazard['hazard_type_id']]);
            $hazardTypeName = $stmt->fetchColumn();
            ?>
            if (options[i].text === '<?php echo $hazardTypeName; ?>') {
                hazardTypeSelect.selectedIndex = i;
                break;
            }
        }
        updateRiskLevels();
    }, 100);
    <?php else: ?>
    // Initial update based on first hazard type
    if (hazardTypeSelect.options.length > 0) {
        updateRiskLevels();
    }
    <?php endif; ?>
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // View households button click
    document.querySelectorAll('.view-households-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const hazardId = this.getAttribute('data-id');
            const hazardType = this.getAttribute('data-hazard');
            const barangay = this.getAttribute('data-barangay');
            const riskLevel = this.getAttribute('data-risk');
            const area = this.getAttribute('data-area');
            const coordinates = this.getAttribute('data-coordinates');
            
            // Set modal header info
            document.getElementById('modalHazardType').textContent = hazardType;
            document.getElementById('modalBarangay').textContent = barangay;
            document.getElementById('modalRiskLevel').innerHTML = `<span class="badge bg-${getRiskBadgeColor(riskLevel)}">${riskLevel}</span>`;
            document.getElementById('modalArea').textContent = area;
            
            // Show loading
            document.getElementById('householdsTableBody').innerHTML = '<tr><td colspan="12" class="text-center"><i class="fas fa-spinner fa-spin me-2"></i>Loading affected households...</td></tr>';
            
            // Fetch affected households
            fetchAffectedHouseholds(hazardId, coordinates, area);
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('householdsModal'));
            modal.show();
        });
    });
    
    // Export button
    document.getElementById('exportAffectedBtn').addEventListener('click', function() {
        exportAffectedHouseholds();
    });
});

function getRiskBadgeColor(riskLevel) {
    if (riskLevel.includes('High') || riskLevel === 'Prone') return 'danger';
    if (riskLevel.includes('Moderate') || riskLevel.includes('General')) return 'warning';
    if (riskLevel.includes('Low')) return 'success';
    if (riskLevel.includes('PEIS')) return 'purple';
    return 'secondary';
}

function fetchAffectedHouseholds(hazardId, hazardCoordinates, areaKm2) {
    // Parse hazard coordinates
    let hazardLat = 0, hazardLng = 0;
    if (hazardCoordinates) {
        const coords = hazardCoordinates.split(',');
        hazardLat = parseFloat(coords[0].trim());
        hazardLng = parseFloat(coords[1].trim());
    }
    
    // Show loading with area info
    document.getElementById('householdsTableBody').innerHTML = 
        `<tr><td colspan="13" class="text-center">
            <i class="fas fa-spinner fa-spin me-2"></i>
            Scanning ${areaKm2} km² area for affected households...
        </td></tr>`;
    
    // Fetch households from the database
    fetch('get_households.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            hazard_id: hazardId,
            hazard_lat: hazardLat,
            hazard_lng: hazardLng,
            area_km2: parseFloat(areaKm2),
            barangay: document.getElementById('modalBarangay').textContent
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayHouseholds(data.households, data.summary, data);
            
            // Log the search area info for debugging
            console.log('Search radius:', data.radius_used);
            console.log('Bounding box:', data.bounds);
        } else {
            document.getElementById('householdsTableBody').innerHTML = 
                `<tr><td colspan="13" class="text-center text-danger">${data.message}</td></tr>`;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('householdsTableBody').innerHTML = 
            '<tr><td colspan="13" class="text-center text-danger">Error loading households</td></tr>';
    });
}

function displayHouseholds(households, summary, metadata) {
    const tbody = document.getElementById('householdsTableBody');
    const totalAffected = document.getElementById('totalAffectedCount');
    
    if (!households || households.length === 0) {
        tbody.innerHTML = `<tr><td colspan="13" class="text-center">
            No households found within ${metadata?.radius_km || 'the'} hazard area
        </td></tr>`;
        totalAffected.textContent = '0';
        return;
    }
    
    // Update summary
    document.getElementById('totalMembers').textContent = summary.total_members || 0;
    document.getElementById('totalPWD').textContent = summary.total_pwd || 0;
    document.getElementById('totalPregnant').textContent = summary.total_pregnant || 0;
    document.getElementById('totalSenior').textContent = summary.total_senior || 0;
    document.getElementById('totalInfant').textContent = summary.total_infant || 0;
    document.getElementById('totalMinor').textContent = summary.total_minor || 0;
    totalAffected.textContent = households.length;
    
    // Add info about search radius
    const infoRow = document.createElement('tr');
    infoRow.innerHTML = `<td colspan="13" class="text-muted small">
        <i class="fas fa-info-circle me-1"></i>
        Search radius: ${metadata?.radius_used || 'N/A'} | 
        Total households in barangay: ${metadata?.total_households_in_barangay || 'N/A'}
    </td>`;
    
    // Build table rows
    let html = '';
    households.forEach((h, index) => {
        const gender = h.sex || h.gender || 'N/A';
        const distance = h.distance_km ? parseFloat(h.distance_km).toFixed(2) + ' km' : 'N/A';
        
        // Determine if within core area (within 70% of radius) or buffer zone
        const isCoreArea = h.distance_km && h.distance_km <= (metadata?.radius_km ? parseFloat(metadata.radius_km) * 0.7 : 0);
        const rowClass = isCoreArea ? 'table-warning' : '';
        
        html += `<tr class="${rowClass}">
            <td>${index + 1}</td>
            <td>${h.household_head || 'N/A'}</td>
            <td>${gender}</td>
            <td>${h.age || 'N/A'}</td>
            <td>${h.house_type || 'N/A'}</td>
            <td>${h.family_members || 0}</td>
            <td>${h.pwd_count || 0}</td>
            <td>${h.pregnant_count || 0}</td>
            <td>${h.senior_count || 0}</td>
            <td>${h.infant_count || 0}</td>
            <td>${h.minor_count || 0}</td>
            <td><small>${h.latitude ? parseFloat(h.latitude).toFixed(4) : 'N/A'}, ${h.longitude ? parseFloat(h.longitude).toFixed(4) : 'N/A'}</small></td>
            <td>
                <span class="badge ${isCoreArea ? 'bg-danger' : 'bg-info'}">${distance}</span>
            </td>
        </tr>`;
    });
    
    tbody.innerHTML = html;
}

function exportAffectedHouseholds() {
    const table = document.getElementById('householdsTable');
    const rows = table.querySelectorAll('tr');
    const hazardType = document.getElementById('modalHazardType').textContent;
    const barangay = document.getElementById('modalBarangay').textContent;
    
    let csv = [];
    
    // Add header
    csv.push('"Affected Households Report"');
    csv.push(`"Hazard: ${hazardType}","Barangay: ${barangay}"`);
    csv.push('"Generated: ' + new Date().toLocaleString() + '"');
    csv.push('');
    
    // Add column headers
    const headers = [];
    table.querySelectorAll('thead th').forEach(th => {
        headers.push(`"${th.textContent}"`);
    });
    csv.push(headers.join(','));
    
    // Add data rows
    table.querySelectorAll('tbody tr').forEach(row => {
        if (row.cells.length > 1 && !row.textContent.includes('No households')) {
            const rowData = [];
            row.querySelectorAll('td').forEach(cell => {
                rowData.push(`"${cell.textContent.trim()}"`);
            });
            csv.push(rowData.join(','));
        }
    });
    
    // Download CSV
    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `affected_households_${hazardType}_${barangay}_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
}
</script>

<script>
// Export functionality
document.getElementById('exportHouseholdRiskBtn').addEventListener('click', function() {
    const modal = new bootstrap.Modal(document.getElementById('exportOptionsModal'));
    modal.show();
});

document.getElementById('confirmExport').addEventListener('click', function() {
    // Show loading state
    const btn = this;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Generating...';
    btn.disabled = true;
    
    // Gather export options
    const options = {
        includeHouseholdHead: document.getElementById('includeHouseholdHead').checked,
        includeMembers: document.getElementById('includeMembers').checked,
        includeHazards: document.getElementById('includeHazards').checked,
        includeVulnerability: document.getElementById('includeVulnerability').checked,
        barangayFilter: document.getElementById('exportBarangayFilter').value,
        riskFilter: document.getElementById('exportRiskFilter').value,
        dateFrom: document.getElementById('exportDateFrom').value,
        dateTo: document.getElementById('exportDateTo').value,
        format: document.querySelector('input[name="exportFormat"]:checked').value
    };
    
    // Fetch data and generate export
    fetchExportData(options).then(() => {
        // Reset button
        btn.innerHTML = originalText;
        btn.disabled = false;
        
        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('exportOptionsModal'));
        modal.hide();
    });
});

async function fetchExportData(options) {
    try {
        // Show loading toast or notification
        showNotification('Collecting household data...', 'info');
        
        // Fetch all households with their data
        const response = await fetch('get_households_export.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(options)
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Generate export based on format
            if (options.format === 'excel') {
                generateExcelExport(data, options);
            } else if (options.format === 'json') {
                generateJSONExport(data, options);
            }
            
            showNotification(`Export completed! ${data.summary.total_households} households exported.`, 'success');
        } else {
            showNotification('Error: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Export error:', error);
        showNotification('Failed to generate export', 'error');
    }
}

function generateExcelExport(data, options) {
    const rows = [];
    const summary = data.summary;
    
    // Add header information
    rows.push(['SABLAYAN RISK ASSESSMENT SYSTEM']);
    rows.push(['Household Risk Data Export']);
    rows.push(['Generated:', new Date().toLocaleString()]);
    rows.push(['Total Households:', summary.total_households]);
    rows.push(['Total Members:', summary.total_members]);
    rows.push(['Total Vulnerable:', summary.total_vulnerable]);
    rows.push(['Barangays Covered:', summary.barangays_covered]);
    rows.push(['']);
    
    // Add summary section
    rows.push(['SUMMARY STATISTICS']);
    rows.push(['Category', 'Count']);
    rows.push(['Total Households', summary.total_households]);
    rows.push(['Total Family Members', summary.total_members]);
    rows.push(['Total PWD', summary.total_pwd]);
    rows.push(['Total Pregnant', summary.total_pregnant]);
    rows.push(['Total Seniors (60+)', summary.total_senior]);
    rows.push(['Total Infants (0-1)', summary.total_infant]);
    rows.push(['Total Minors (2-17)', summary.total_minor]);
    rows.push(['']);
    
    // Add hazard summary
    rows.push(['HAZARD SUMMARY']);
    rows.push(['Hazard Type', 'Households Affected', 'Risk Level']);
    summary.hazard_summary.forEach(h => {
        rows.push([h.type, h.count, h.risk_level]);
    });
    rows.push(['']);
    
    // Add main data headers
    const headers = ['#', 'Barangay', 'Zone', 'Household Head', 'Age', 'Gender', 'House Type', 'Family Members'];
    
    if (options.includeVulnerability) {
        headers.push('PWD', 'Pregnant', 'Senior', 'Infant', 'Minor', 'Total Vulnerable');
    }
    
    if (options.includeMembers) {
        headers.push('Member Names', 'Member Ages', 'Member Relationships', 'Member Vulnerabilities');
    }
    
    if (options.includeHazards) {
        headers.push('Hazard Risks', 'Risk Levels', 'Distances (km)', 'Within Hazard Zone');
    }
    
    headers.push('Coordinates', 'Registration Date');
    rows.push(headers);
    
    // Add household data
    data.households.forEach((h, index) => {
        const row = [
            index + 1,
            h.barangay_name,
            h.zone || 'N/A',
            h.household_head,
            h.age,
            h.gender,
            h.house_type || 'N/A',
            h.family_members
        ];
        
        if (options.includeVulnerability) {
            const totalVulnerable = (parseInt(h.pwd_count) || 0) + 
                                   (parseInt(h.pregnant_count) || 0) + 
                                   (parseInt(h.senior_count) || 0) + 
                                   (parseInt(h.infant_count) || 0) + 
                                   (parseInt(h.minor_count) || 0);
            
            row.push(
                h.pwd_count || 0,
                h.pregnant_count || 0,
                h.senior_count || 0,
                h.infant_count || 0,
                h.minor_count || 0,
                totalVulnerable
            );
        }
        
        if (options.includeMembers && h.members && h.members.length > 0) {
            const memberNames = h.members.map(m => m.full_name).join('; ');
            const memberAges = h.members.map(m => m.age).join('; ');
            const memberRelationships = h.members.map(m => m.relationship).join('; ');
            const memberVulnerabilities = h.members.map(m => {
                const vuln = [];
                if (m.is_pwd) vuln.push('PWD');
                if (m.is_pregnant) vuln.push('Pregnant');
                if (m.is_senior) vuln.push('Senior');
                if (m.is_infant) vuln.push('Infant');
                if (m.is_minor) vuln.push('Minor');
                return vuln.join(',');
            }).join('; ');
            
            row.push(memberNames, memberAges, memberRelationships, memberVulnerabilities);
        } else if (options.includeMembers) {
            row.push('No members', '', '', '');
        }
        
        if (options.includeHazards && h.hazards && h.hazards.length > 0) {
            const hazardNames = h.hazards.map(ha => ha.hazard_name).join('; ');
            const hazardLevels = h.hazards.map(ha => ha.risk_level).join('; ');
            const hazardDistances = h.hazards.map(ha => ha.distance_km.toFixed(2)).join('; ');
            const withinZones = h.hazards.map(ha => ha.within_zone ? 'Yes' : 'No').join('; ');
            
            row.push(hazardNames, hazardLevels, hazardDistances, withinZones);
        } else if (options.includeHazards) {
            row.push('No hazards', '', '', '');
        }
        
        row.push(
            h.latitude && h.longitude ? `${h.latitude}, ${h.longitude}` : 'No coordinates',
            h.created_at ? new Date(h.created_at).toLocaleDateString() : 'N/A'
        );
        
        rows.push(row);
    });
    
    // Add footer
    rows.push(['']);
    rows.push(['END OF REPORT']);
    rows.push(['Generated by Sablayan Risk Assessment System']);
    
    // Convert to CSV
    const csvContent = rows.map(row => 
        row.map(cell => {
            if (typeof cell === 'string' && (cell.includes(',') || cell.includes('"') || cell.includes('\n'))) {
                return `"${cell.replace(/"/g, '""')}"`;
            }
            return cell;
        }).join(',')
    ).join('\n');
    
    // Download
    const blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `household_risk_data_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

function generateJSONExport(data, options) {
    const exportData = {
        metadata: {
            generated: new Date().toISOString(),
            system: 'Sablayan Risk Assessment System',
            version: '1.0',
            options: options,
            summary: data.summary
        },
        households: data.households
    };
    
    const jsonContent = JSON.stringify(exportData, null, 2);
    const blob = new Blob([jsonContent], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `household_risk_data_${new Date().toISOString().split('T')[0]}.json`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

function showNotification(message, type) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `alert alert-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    notification.style.zIndex = '9999';
    notification.style.maxWidth = '400px';
    notification.innerHTML = `
        <i class="fas ${type === 'error' ? 'fa-exclamation-circle' : type === 'success' ? 'fa-check-circle' : 'fa-info-circle'} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        notification.remove();
    }, 5000);
}

// Add tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<!-- Phase 4: Leaflet + Leaflet Draw for polygon incident mapping -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
<script>
(function() {
    const mapEl = document.getElementById('hazardMapPicker');
    if (!mapEl) return;

    const SABLAYAN = [12.8333, 120.7667];
    const hazardMap = L.map('hazardMapPicker').setView(SABLAYAN, 12);
    const streetTileH = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors', maxZoom: 19
    });
    const satelliteTileH = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Tiles &copy; Esri', maxZoom: 19
    });
    satelliteTileH.addTo(hazardMap);
    L.control.layers({ 'Satellite': satelliteTileH, 'Street': streetTileH }, {}, { position: 'topright' }).addTo(hazardMap);

    const drawnItems = new L.FeatureGroup();
    hazardMap.addLayer(drawnItems);

    const drawControl = new L.Control.Draw({
        draw: {
            polygon: {
                allowIntersection: false,
                shapeOptions: { color: '#dc3545', weight: 2, fillOpacity: 0.15 }
            },
            polyline: false, rectangle: false, circle: false,
            marker: false, circlemarker: false
        },
        edit: { featureGroup: drawnItems }
    });
    hazardMap.addControl(drawControl);

    const polyInput    = document.getElementById('polygonGeoJSON');
    const coordsInput  = document.getElementById('hazardCoordinates');
    const barangaySelect = document.querySelector('select[name="barangay_id"]') ||
                           document.querySelector('input[name="barangay_id"]');

    // Load existing polygon if editing
    if (polyInput && polyInput.value.trim()) {
        try {
            const gj = JSON.parse(polyInput.value);
            const layer = L.geoJSON(gj, {style: {color:'#dc3545',weight:2,fillOpacity:0.15}});
            layer.eachLayer(l => drawnItems.addLayer(l));
            hazardMap.fitBounds(drawnItems.getBounds());
        } catch(e) {}
    }

    function computeHazardPopulation(geojsonStr) {
        const formData = new FormData();
        formData.append('action', 'compute_hazard_population');
        formData.append('polygon_geojson', geojsonStr);
        fetch('hazard_data.php', {method:'POST', body: formData})
            .then(r => r.json())
            .then(function(res) {
                if (res.success) {
                    document.getElementById('hzHH').textContent  = res.total_households.toLocaleString();
                    document.getElementById('hzPop').textContent = res.total_population.toLocaleString();
                    document.getElementById('hazardPopResult').style.display = 'block';
                }
            });
    }

    function onLayerChange() {
        if (drawnItems.getLayers().length === 0) return;
        const geojsonData = drawnItems.toGeoJSON();
        const geojsonStr  = JSON.stringify(geojsonData);
        polyInput.value   = geojsonStr;
        const firstLayer = drawnItems.getLayers()[0];
        const center = firstLayer.getBounds().getCenter();
        if (coordsInput) coordsInput.value = center.lat.toFixed(6) + ',' + center.lng.toFixed(6);
        computeHazardPopulation(geojsonStr);
    }

    hazardMap.on(L.Draw.Event.CREATED, function(e) {
        drawnItems.clearLayers();
        drawnItems.addLayer(e.layer);
        onLayerChange();
    });

    hazardMap.on(L.Draw.Event.EDITED, function() { onLayerChange(); });
    hazardMap.on(L.Draw.Event.DELETED, function() {
        polyInput.value = '';
        document.getElementById('hazardPopResult').style.display = 'none';
    });

    // Zoom map to selected barangay
    if (barangaySelect) {
        barangaySelect.addEventListener('change', function() {
            const barangayData = <?php echo json_encode(array_combine(
                array_column($barangays, 'id'),
                array_map(function($b) { return $b['coordinates']; }, $barangays)
            )); ?>;
            const coords = barangayData[this.value];
            if (coords) {
                const parts = coords.split(',');
                if (parts.length >= 2) {
                    hazardMap.setView([parseFloat(parts[0]), parseFloat(parts[1])], 14);
                }
            }
        });
    }

    // Trigger initial barangay zoom if editing
    <?php if ($edit_hazard): ?>
    (function() {
        const coords = <?php echo json_encode($edit_hazard['coordinates']); ?>;
        if (coords) {
            const parts = coords.split(',');
            if (parts.length >= 2) hazardMap.setView([parseFloat(parts[0]), parseFloat(parts[1])], 14);
        }
    })();
    <?php endif; ?>
})();
</script>
</body>
</html>