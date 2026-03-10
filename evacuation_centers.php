<?php
// evacuation_centers.php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'division_chief') {
    header('Location: login.php');
    exit;
}

// Handle form submissions
if ($_POST) {
    if (isset($_POST['add_evac_center'])) {
        $name = $_POST['name'];
        $barangay_id = $_POST['barangay_id'];
        $capacity = $_POST['capacity'];
        $current_occupancy = $_POST['current_occupancy'] ?? 0;
        $latitude = $_POST['latitude'];
        $longitude = $_POST['longitude'];
        $facilities = $_POST['facilities'];
        $contact_person = $_POST['contact_person'];
        $contact_number = $_POST['contact_number'];
        $status = $_POST['status'];

        $stmt = $pdo->prepare("INSERT INTO evacuation_centers 
                              (name, barangay_id, capacity, current_occupancy, latitude, longitude, 
                               facilities, contact_person, contact_number, status) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $barangay_id, $capacity, $current_occupancy, $latitude, $longitude, 
                       $facilities, $contact_person, $contact_number, $status]);
        
        $success = "Evacuation center added successfully!";
    }
    
    if (isset($_POST['update_evac_center'])) {
        $center_id = $_POST['center_id'];
        $name = $_POST['name'];
        $barangay_id = $_POST['barangay_id'];
        $capacity = $_POST['capacity'];
        $current_occupancy = $_POST['current_occupancy'];
        $latitude = $_POST['latitude'];
        $longitude = $_POST['longitude'];
        $facilities = $_POST['facilities'];
        $contact_person = $_POST['contact_person'];
        $contact_number = $_POST['contact_number'];
        $status = $_POST['status'];

        $stmt = $pdo->prepare("UPDATE evacuation_centers 
                              SET name = ?, barangay_id = ?, capacity = ?, current_occupancy = ?, 
                                  latitude = ?, longitude = ?, facilities = ?, contact_person = ?, 
                                  contact_number = ?, status = ?, updated_at = NOW() 
                              WHERE id = ?");
        $stmt->execute([$name, $barangay_id, $capacity, $current_occupancy, $latitude, $longitude, 
                       $facilities, $contact_person, $contact_number, $status, $center_id]);
        
        $success = "Evacuation center updated successfully!";
    }
}

// Handle delete action
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    
    $stmt = $pdo->prepare("DELETE FROM evacuation_centers WHERE id = ?");
    $stmt->execute([$delete_id]);
    
    header('Location: evacuation_centers.php?deleted=1');
    exit;
}

// Handle edit action
$edit_center = null;
if (isset($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    $stmt = $pdo->prepare("
        SELECT ec.*, b.name as barangay_name 
        FROM evacuation_centers ec 
        JOIN barangays b ON ec.barangay_id = b.id 
        WHERE ec.id = ?
    ");
    $stmt->execute([$edit_id]);
    $edit_center = $stmt->fetch();
}

// Get data for dropdowns
$barangays = $pdo->query("SELECT * FROM barangays")->fetchAll();

// Get evacuation centers data
$evac_centers = $pdo->query("
    SELECT ec.*, b.name as barangay_name 
    FROM evacuation_centers ec 
    JOIN barangays b ON ec.barangay_id = b.id 
    ORDER BY ec.created_at DESC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evacuation Centers - Sablayan Risk Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
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
        #evacMap {
            height: 500px;
            width: 100%;
            border-radius: 8px;
        }
        .evac-card {
            cursor: pointer;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            height: 100%;
        }
        .evac-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .evac-card.selected {
            border-left-color: #2196f3;
            background-color: #f0f8ff;
        }
        .capacity-bar {
            height: 8px;
            border-radius: 4px;
            background-color: #e9ecef;
            overflow: hidden;
        }
        .capacity-fill {
            height: 100%;
            border-radius: 4px;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .evac-card-header {
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding-bottom: 0.75rem;
            margin-bottom: 0.75rem;
        }
        .evac-details {
            font-size: 0.875rem;
            color: #6c757d;
        }
        .evac-actions {
            position: absolute;
            top: 15px;
            right: 15px;
            opacity: 0;
            transition: opacity 0.2s;
        }
        .evac-card:hover .evac-actions {
            opacity: 1;
        }
        .grid-view {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        @media (max-width: 768px) {
            .grid-view {
                grid-template-columns: 1fr;
            }
        }
        
        .view-map {
    color: #0dcaf0;
    border-color: #0dcaf0;
}
.view-map:hover {
    background-color: #0dcaf0;
    color: white;
}

#detailMap {
    border-radius: 8px 0 0 8px;
}

@media (max-width: 768px) {
    #detailMap {
        height: 300px !important;
        border-radius: 8px 8px 0 0;
    }
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
                    <h1 class="h2">Evacuation Centers Management</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEvacModal">
                        <i class="fas fa-plus me-2"></i>Add New Center
                    </button>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <?php if (isset($_GET['deleted'])): ?>
                    <div class="alert alert-success">Evacuation center deleted successfully!</div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-12">
                        <!-- Evacuation Centers Map -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-map me-2"></i> Evacuation Centers Map
                                </h5>
                            </div>
                            <div class="card-body">
                                <div id="evacMap"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-12">
                        <!-- Evacuation Centers Cards -->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">All Evacuation Centers</h5>
                                <span class="badge bg-primary"><?php echo count($evac_centers); ?> centers</span>
                            </div>
                            <div class="card-body">
                                <div class="grid-view">
                                    <?php foreach ($evac_centers as $center): 
                                        $occupancy_rate = $center['capacity'] > 0 ? ($center['current_occupancy'] / $center['capacity']) * 100 : 0;
                                        $capacity_color = $occupancy_rate >= 80 ? 'danger' : ($occupancy_rate >= 50 ? 'warning' : 'success');
                                        $status_color = $center['status'] == 'operational' ? 'success' : 
                                                       ($center['status'] == 'maintenance' ? 'warning' : 'danger');
                                    ?>
                                        <div class="card evac-card"
                                            data-center-id="<?php echo $center['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($center['name']); ?>"
                                            data-barangay="<?php echo htmlspecialchars($center['barangay_name']); ?>"
                                            data-barangay-id="<?php echo $center['barangay_id']; ?>"
                                            data-capacity="<?php echo $center['capacity']; ?>"
                                            data-occupancy="<?php echo $center['current_occupancy']; ?>"
                                            data-latitude="<?php echo $center['latitude']; ?>"
                                            data-longitude="<?php echo $center['longitude']; ?>"
                                            data-facilities="<?php echo htmlspecialchars($center['facilities']); ?>"
                                            data-contact-person="<?php echo htmlspecialchars($center['contact_person']); ?>"
                                            data-contact-number="<?php echo htmlspecialchars($center['contact_number']); ?>"
                                            data-status="<?php echo $center['status']; ?>">
                                            
                                            <div class="card-body">
                                                <div class="evac-actions">
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-info view-map" 
                                                                data-center-id="<?php echo $center['id']; ?>"
                                                                title="View on Map">
                                                            <i class="fas fa-map-marker-alt"></i>
                                                        </button>
                                                        <button class="btn btn-outline-primary edit-evac" 
                                                                data-center-id="<?php echo $center['id']; ?>"
                                                                title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-outline-danger delete-evac" style="margin-right: 100px;"
                                                                data-center-id="<?php echo $center['id']; ?>" 
                                                                data-name="<?php echo htmlspecialchars($center['name']); ?>"
                                                                title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                
                                                <div class="evac-card-header">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <h5 class="card-title mb-1"><?php echo htmlspecialchars($center['name']); ?></h5>
                                                        <span class="badge bg-<?php echo $status_color; ?> status-badge">
                                                            <?php echo ucfirst($center['status']); ?>
                                                        </span>
                                                    </div>
                                                    <div class="evac-details">
                                                        <i class="fas fa-map-marker-alt me-1"></i>
                                                        <?php echo htmlspecialchars($center['barangay_name']); ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <span class="text-muted small">Capacity Utilization</span>
                                                        <span class="small fw-bold"><?php echo number_format($center['current_occupancy']); ?>/<?php echo number_format($center['capacity']); ?></span>
                                                    </div>
                                                    <div class="capacity-bar">
                                                        <div class="capacity-fill bg-<?php echo $capacity_color; ?>" 
                                                             style="width: <?php echo min($occupancy_rate, 100); ?>%"></div>
                                                    </div>
                                                    <div class="text-end small text-muted mt-1">
                                                        <?php echo number_format($occupancy_rate, 1); ?>% occupied
                                                    </div>
                                                </div>
                                                
                                                <div class="row g-2 mb-3">
                                                    <div class="col-12">
                                                        <div class="evac-details">
                                                            <i class="fas fa-user me-1"></i>
                                                            <strong>Contact:</strong> <?php echo htmlspecialchars($center['contact_person']); ?>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="evac-details">
                                                            <i class="fas fa-phone me-1"></i>
                                                            <strong>Phone:</strong> <?php echo htmlspecialchars($center['contact_number']); ?>
                                                        </div>
                                                    </div>
                                                    <?php if (!empty($center['facilities'])): ?>
                                                    <div class="col-12">
                                                        <div class="evac-details">
                                                            <i class="fas fa-list me-1"></i>
                                                            <strong>Facilities:</strong> 
                                                            <?php 
                                                                $facilities = explode(',', $center['facilities']);
                                                                echo htmlspecialchars(trim($facilities[0]));
                                                                if (count($facilities) > 1) echo ' +' . (count($facilities) - 1) . ' more';
                                                            ?>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="text-end small text-muted">
                                                    <i class="fas fa-clock me-1"></i>
                                                    Last updated: <?php echo date('M d, Y', strtotime($center['updated_at'] ?? $center['created_at'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Evacuation Center Modal -->
    <div class="modal fade" id="addEvacModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Evacuation Center</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Center Name</label>
                                    <input type="text" name="name" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Barangay</label>
                                    <select name="barangay_id" class="form-select" required>
                                        <option value="">Select Barangay</option>
                                        <?php foreach ($barangays as $barangay): ?>
                                            <option value="<?php echo $barangay['id']; ?>"><?php echo $barangay['name']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Capacity</label>
                                    <input type="number" name="capacity" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Current Occupancy</label>
                                    <input type="number" name="current_occupancy" class="form-control" value="0">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Latitude</label>
                                    <input type="text" name="latitude" class="form-control" placeholder="e.g., 12.834567">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Longitude</label>
                                    <input type="text" name="longitude" class="form-control" placeholder="e.g., 120.768901">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Facilities</label>
                            <textarea name="facilities" class="form-control" rows="3" placeholder="List available facilities..."></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Contact Person</label>
                                    <input type="text" name="contact_person" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Contact Number</label>
                                    <input type="text" name="contact_number" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" required>
                                <option value="operational">Operational</option>
                                <option value="maintenance">Under Maintenance</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_evac_center" class="btn btn-primary">Add Center</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Evacuation Center Modal -->
    <div class="modal fade" id="editEvacModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Evacuation Center</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="center_id" id="editCenterId">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Center Name</label>
                                    <input type="text" name="name" class="form-control" id="editName" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Barangay</label>
                                    <select name="barangay_id" class="form-select" id="editBarangayId" required>
                                        <option value="">Select Barangay</option>
                                        <?php foreach ($barangays as $barangay): ?>
                                            <option value="<?php echo $barangay['id']; ?>"><?php echo $barangay['name']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Capacity</label>
                                    <input type="number" name="capacity" class="form-control" id="editCapacity" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Current Occupancy</label>
                                    <input type="number" name="current_occupancy" class="form-control" id="editOccupancy">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Latitude</label>
                                    <input type="text" name="latitude" class="form-control" id="editLatitude">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Longitude</label>
                                    <input type="text" name="longitude" class="form-control" id="editLongitude">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Facilities</label>
                            <textarea name="facilities" class="form-control" rows="3" id="editFacilities"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Contact Person</label>
                                    <input type="text" name="contact_person" class="form-control" id="editContactPerson">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Contact Number</label>
                                    <input type="text" name="contact_number" class="form-control" id="editContactNumber">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" id="editStatus" required>
                                <option value="operational">Operational</option>
                                <option value="maintenance">Under Maintenance</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_evac_center" class="btn btn-warning">Update Center</button>
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
                    <p>Are you sure you want to delete evacuation center: <strong id="deleteCenterName"></strong>?</p>
                    <p class="text-danger"><small>This action cannot be undone.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- View on Map Modal -->
<div class="modal fade" id="viewMapModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-map-marked-alt me-2"></i>
                    <span id="viewMapTitle">Evacuation Center Location</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="row g-0">
                    <div class="col-md-8">
                        <div id="detailMap" style="height: 500px; width: 100%;"></div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3">
                            <h6 class="border-bottom pb-2 mb-3">Center Details</h6>
                            <div class="mb-3">
                                <strong class="d-block">Center Name:</strong>
                                <span id="viewCenterName" class="text-primary"></span>
                            </div>
                            <div class="mb-3">
                                <strong class="d-block">Barangay:</strong>
                                <span id="viewBarangay"></span>
                            </div>
                            <div class="mb-3">
                                <strong class="d-block">Capacity:</strong>
                                <span id="viewCapacity"></span> persons
                            </div>
                            <div class="mb-3">
                                <strong class="d-block">Current Occupancy:</strong>
                                <span id="viewOccupancy"></span> persons
                            </div>
                            <div class="mb-3">
                                <strong class="d-block">Utilization Rate:</strong>
                                <span id="viewUtilization" class="badge bg-success"></span>
                            </div>
                            <div class="mb-3">
                                <strong class="d-block">Status:</strong>
                                <span id="viewStatus" class="badge"></span>
                            </div>
                            <div class="mb-3">
                                <strong class="d-block">Contact Person:</strong>
                                <span id="viewContactPerson"></span>
                            </div>
                            <div class="mb-3">
                                <strong class="d-block">Contact Number:</strong>
                                <span id="viewContactNumber"></span>
                            </div>
                            <div class="mb-3">
                                <strong class="d-block">Facilities:</strong>
                                <span id="viewFacilities"></span>
                            </div>
                            <div class="mt-4">
                                <strong class="d-block">Coordinates:</strong>
                                <span id="viewCoordinates" class="text-muted small"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" id="getDirectionsBtn" class="btn btn-primary" target="_blank">
                    <i class="fas fa-directions me-1"></i> Get Directions
                </a>
            </div>
        </div>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Map and markers variables
        let map;
        let evacMarkers = [];

        // Initialize the map
        function initMap() {
            // Default coordinates for Sablayan
            const sablayanCoords = [12.8333, 120.7667];
            
            // Initialize the map
            map = L.map('evacMap').setView(sablayanCoords, 12);
            
            // Base tile layers
            const streetTileE = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors', maxZoom: 19
            });
            const satelliteTileE = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Tiles &copy; Esri', maxZoom: 19
            });
            satelliteTileE.addTo(map);
            L.control.layers({ 'Satellite': satelliteTileE, 'Street': streetTileE }, {}, { position: 'topright' }).addTo(map);

            // Add evacuation centers to map
            const evacCenters = <?php echo json_encode($evac_centers); ?>;
            
            evacCenters.forEach(center => {
                if (center.latitude && center.longitude) {
                    const lat = parseFloat(center.latitude);
                    const lng = parseFloat(center.longitude);
                    const occupancyRate = center.capacity > 0 ? (center.current_occupancy / center.capacity) * 100 : 0;
                    
                    let markerColor = 'green';
                    if (center.status === 'maintenance') markerColor = 'orange';
                    if (center.status === 'closed') markerColor = 'red';
                    if (occupancyRate >= 80) markerColor = 'red';
                    else if (occupancyRate >= 50) markerColor = 'orange';
                    
                    const marker = L.marker([lat, lng], {
                        icon: L.divIcon({
                            className: 'custom-div-icon',
                            html: `<div style="background-color: ${markerColor}; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white;"></div>`,
                            iconSize: [20, 20],
                            iconAnchor: [10, 10]
                        })
                    }).addTo(map);
                    
                    marker.bindPopup(`
                        <strong>${center.name}</strong><br>
                        Barangay: ${center.barangay_name}<br>
                        Capacity: ${center.capacity.toLocaleString()}<br>
                        Occupancy: ${center.current_occupancy.toLocaleString()} (${Math.round(occupancyRate)}%)<br>
                        Status: ${center.status}<br>
                        Contact: ${center.contact_person} - ${center.contact_number}
                    `);
                    
                    evacMarkers.push(marker);
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize map
            initMap();
            
            // Modals
            const addEvacModal = new bootstrap.Modal(document.getElementById('addEvacModal'));
            const editEvacModal = new bootstrap.Modal(document.getElementById('editEvacModal'));
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));

            // Edit evacuation center buttons
            document.querySelectorAll('.edit-evac').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    
                    const centerId = this.getAttribute('data-center-id');
                    const card = this.closest('.evac-card');
                    
                    document.getElementById('editCenterId').value = centerId;
                    document.getElementById('editName').value = card.getAttribute('data-name');
                    document.getElementById('editBarangayId').value = card.getAttribute('data-barangay-id') || '';
                    document.getElementById('editCapacity').value = card.getAttribute('data-capacity');
                    document.getElementById('editOccupancy').value = card.getAttribute('data-occupancy');
                    document.getElementById('editLatitude').value = card.getAttribute('data-latitude');
                    document.getElementById('editLongitude').value = card.getAttribute('data-longitude');
                    document.getElementById('editFacilities').value = card.getAttribute('data-facilities');
                    document.getElementById('editContactPerson').value = card.getAttribute('data-contact-person');
                    document.getElementById('editContactNumber').value = card.getAttribute('data-contact-number');
                    document.getElementById('editStatus').value = card.getAttribute('data-status');
                    
                    editEvacModal.show();
                });
            });

            // Delete evacuation center buttons
            document.querySelectorAll('.delete-evac').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    
                    const centerId = this.getAttribute('data-center-id');
                    const centerName = this.getAttribute('data-name');
                    
                    document.getElementById('deleteCenterName').textContent = centerName;
                    document.getElementById('confirmDeleteBtn').href = `evacuation_centers.php?delete_id=${centerId}`;
                    deleteModal.show();
                });
            });

            // Card click event to show details
            document.querySelectorAll('.evac-card').forEach(card => {
                card.addEventListener('click', function(e) {
                    // Don't trigger if clicking on action buttons
                    if (e.target.closest('.btn-group')) {
                        return;
                    }
                    
                    // Remove selected class from all cards
                    document.querySelectorAll('.evac-card').forEach(c => {
                        c.classList.remove('selected');
                    });
                    
                    // Add selected class to clicked card
                    this.classList.add('selected');
                    
                    // Center map on this evacuation center
                    const lat = parseFloat(this.getAttribute('data-latitude'));
                    const lng = parseFloat(this.getAttribute('data-longitude'));
                    
                    if (!isNaN(lat) && !isNaN(lng)) {
                        map.setView([lat, lng], 15);
                    }
                });
            });

            // Auto-fill coordinates when barangay is selected
            const barangaySelect = document.querySelector('#addEvacModal select[name="barangay_id"]');
            const latitudeInput = document.querySelector('#addEvacModal input[name="latitude"]');
            const longitudeInput = document.querySelector('#addEvacModal input[name="longitude"]');
            
            // Create mapping of barangay names to approximate coordinates
            const barangayCoordinates = {
                <?php foreach ($barangays as $barangay): ?>
                    '<?php echo $barangay['id']; ?>': '<?php echo $barangay['coordinates'] ?? ''; ?>',
                <?php endforeach; ?>
            };
            
            if (barangaySelect) {
                barangaySelect.addEventListener('change', function() {
                    const selectedBarangayId = this.value;
                    
                    if (selectedBarangayId && barangayCoordinates[selectedBarangayId]) {
                        const coords = barangayCoordinates[selectedBarangayId].split(',');
                        if (coords.length === 2) {
                            latitudeInput.value = coords[0].trim();
                            longitudeInput.value = coords[1].trim();
                        }
                    } else {
                        latitudeInput.value = '';
                        longitudeInput.value = '';
                    }
                });
            }
        });
    </script>
    
    <script>
        // View on Map functionality
let detailMap = null;
let detailMarker = null;

// View on Map buttons
document.querySelectorAll('.view-map').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        
        const card = this.closest('.evac-card');
        const centerId = this.getAttribute('data-center-id');
        const centerName = card.getAttribute('data-name');
        const barangay = card.getAttribute('data-barangay');
        const capacity = parseInt(card.getAttribute('data-capacity'));
        const occupancy = parseInt(card.getAttribute('data-occupancy'));
        const latitude = parseFloat(card.getAttribute('data-latitude'));
        const longitude = parseFloat(card.getAttribute('data-longitude'));
        const facilities = card.getAttribute('data-facilities');
        const contactPerson = card.getAttribute('data-contact-person');
        const contactNumber = card.getAttribute('data-contact-number');
        const status = card.getAttribute('data-status');
        
        // Calculate utilization rate
        const utilizationRate = capacity > 0 ? Math.round((occupancy / capacity) * 100) : 0;
        let utilizationColor = 'success';
        if (utilizationRate >= 80) utilizationColor = 'danger';
        else if (utilizationRate >= 50) utilizationColor = 'warning';
        
        // Status color
        let statusColor = 'success';
        if (status === 'maintenance') statusColor = 'warning';
        if (status === 'closed') statusColor = 'danger';
        
        // Update modal content
        document.getElementById('viewMapTitle').textContent = centerName;
        document.getElementById('viewCenterName').textContent = centerName;
        document.getElementById('viewBarangay').textContent = barangay;
        document.getElementById('viewCapacity').textContent = capacity.toLocaleString();
        document.getElementById('viewOccupancy').textContent = occupancy.toLocaleString();
        document.getElementById('viewUtilization').textContent = utilizationRate + '%';
        document.getElementById('viewUtilization').className = `badge bg-${utilizationColor}`;
        document.getElementById('viewStatus').textContent = status.charAt(0).toUpperCase() + status.slice(1);
        document.getElementById('viewStatus').className = `badge bg-${statusColor}`;
        document.getElementById('viewContactPerson').textContent = contactPerson || 'N/A';
        document.getElementById('viewContactNumber').textContent = contactNumber || 'N/A';
        document.getElementById('viewFacilities').textContent = facilities || 'No facilities listed';
        document.getElementById('viewCoordinates').textContent = `${latitude}, ${longitude}`;
        
        // Update directions link
        document.getElementById('getDirectionsBtn').href = 
            `https://www.google.com/maps/dir/?api=1&destination=${latitude},${longitude}`;
        
        // Initialize or update the map
        if (detailMap) {
            detailMap.remove();
        }
        
        setTimeout(() => {
            detailMap = L.map('detailMap').setView([latitude, longitude], 15);
            
            // Base tile layers
            const streetTileD = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors', maxZoom: 19
            });
            const satelliteTileD = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Tiles &copy; Esri', maxZoom: 19
            });
            satelliteTileD.addTo(detailMap);
            L.control.layers({ 'Satellite': satelliteTileD, 'Street': streetTileD }, {}, { position: 'topright' }).addTo(detailMap);
            
            // Remove existing marker
            if (detailMarker) {
                detailMarker.remove();
            }
            
            // Add new marker
            detailMarker = L.marker([latitude, longitude], {
                icon: L.divIcon({
                    className: 'custom-div-icon',
                    html: `<div style="background-color: #2196f3; width: 30px; height: 30px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>`,
                    iconSize: [30, 30],
                    iconAnchor: [15, 15]
                })
            }).addTo(detailMap);
            
            // Add circle around the marker
            L.circle([latitude, longitude], {
                color: '#2196f3',
                fillColor: '#2196f3',
                fillOpacity: 0.1,
                radius: 200
            }).addTo(detailMap);
            
            // Add popup to marker
            detailMarker.bindPopup(`
                <strong>${centerName}</strong><br>
                ${barangay}<br>
                Capacity: ${capacity.toLocaleString()} persons<br>
                Occupancy: ${occupancy.toLocaleString()} persons (${utilizationRate}%)<br>
                Status: ${status}
            `).openPopup();
            
            // Add scale control
            L.control.scale().addTo(detailMap);
            
        }, 100);
        
        // Show the modal
        const viewMapModal = new bootstrap.Modal(document.getElementById('viewMapModal'));
        viewMapModal.show();
    });
});

// Initialize the detail map when modal is shown
document.getElementById('viewMapModal').addEventListener('shown.bs.modal', function () {
    if (detailMap) {
        detailMap.invalidateSize();
    }
});
    </script>
</body>
</html>