<?php
// barangay_boundary.php — Admin only — Add/Edit Barangay + Draw Boundary (combined page)
session_start();
require_once 'config.php';
require_once 'sync_functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

// ── AJAX: save barangay + boundary together ──
if (isset($_GET['ajax']) && $_GET['ajax'] === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $id         = $_POST['id'] ?? null;
    $name       = trim($_POST['name'] ?? '');
    $area_km2   = $_POST['area_km2'] ?: null;
    $coords     = trim($_POST['coordinates'] ?? '');
    $staff_id   = $_POST['staff_user_id'] ?: null;
    $geojson    = $_POST['boundary_geojson'] ?? '';
    $calcArea   = isset($_POST['calculated_area_km2']) ? (float)$_POST['calculated_area_km2'] : null;

    if ($name === '') {
        echo json_encode(['ok' => false, 'msg' => 'Barangay name is required.']);
        exit;
    }

    // Validate boundary if provided
    if ($geojson && $geojson !== '' && $geojson !== 'null') {
        $decoded = json_decode($geojson, true);
        if (!$decoded || !isset($decoded['type'])) {
            echo json_encode(['ok' => false, 'msg' => 'Invalid GeoJSON boundary data.']);
            exit;
        }

        $bCoords = $decoded['coordinates'][0] ?? [];
        if (count($bCoords) < 4) {
            echo json_encode(['ok' => false, 'msg' => 'Polygon must have at least 3 points.']);
            exit;
        }

        // Server-side overlap check
        $excludeId = $id ? (int)$id : 0;
        $others = $pdo->prepare("SELECT id, name, boundary_geojson FROM barangays WHERE id != ? AND boundary_geojson IS NOT NULL AND boundary_geojson != ''");
        $others->execute([$excludeId]);
        $overlapping = [];
        while ($row = $others->fetch(PDO::FETCH_ASSOC)) {
            $otherGeo = json_decode($row['boundary_geojson'], true);
            if (!$otherGeo || !isset($otherGeo['coordinates'][0])) continue;
            if (polygons_overlap($decoded['coordinates'][0], $otherGeo['coordinates'][0])) {
                $overlapping[] = $row['name'];
            }
        }
        if (!empty($overlapping)) {
            echo json_encode(['ok' => false, 'msg' => 'Boundary overlaps with: ' . implode(', ', $overlapping) . '. Please redraw.']);
            exit;
        }
    } else {
        $geojson = null;
        $calcArea = null;
    }

    try {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE barangays SET name = ?, area_km2 = ?, calculated_area_km2 = ?, coordinates = ?, boundary_geojson = ? WHERE id = ?");
            $stmt->execute([$name, $area_km2, $calcArea, $coords, $geojson, $id]);

            // Unassign previous staff
            $pdo->prepare("UPDATE users SET barangay_id = NULL WHERE barangay_id = ? AND role = 'barangay_staff'")->execute([$id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO barangays (name, area_km2, calculated_area_km2, coordinates, boundary_geojson) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $area_km2, $calcArea, $coords, $geojson]);
            $id = $pdo->lastInsertId();
        }

        // Assign staff
        if ($staff_id) {
            $pdo->prepare("UPDATE users SET barangay_id = ? WHERE id = ? AND role = 'barangay_staff'")->execute([$id, $staff_id]);
        }

        // Log boundary action
        if ($geojson) {
            $pdo->prepare("INSERT INTO barangay_boundary_logs (barangay_id, action, drawn_by) VALUES (?, ?, ?)")
                ->execute([$id, $id == $pdo->lastInsertId() ? 'created' : 'updated', $_SESSION['user_id']]);
        }

        echo json_encode(['ok' => true, 'msg' => 'Barangay saved successfully.', 'id' => $id]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── AJAX: delete boundary only ──
if (isset($_GET['ajax']) && $_GET['ajax'] === 'delete_boundary' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $id = (int)$_POST['barangay_id'];
    if ($id) {
        $pdo->prepare("UPDATE barangays SET boundary_geojson = NULL, calculated_area_km2 = NULL WHERE id = ?")->execute([$id]);
        echo json_encode(['ok' => true, 'msg' => 'Boundary removed.']);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'No barangay specified.']);
    }
    exit;
}

// ── AJAX: get all existing boundaries for map overlay ──
if (isset($_GET['ajax']) && $_GET['ajax'] === 'boundaries') {
    header('Content-Type: application/json');
    $rows = $pdo->query("
        SELECT id, name, boundary_geojson
        FROM barangays
        WHERE boundary_geojson IS NOT NULL AND boundary_geojson != ''
        ORDER BY name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows);
    exit;
}

// ── AJAX: get staff users ──
if (isset($_GET['ajax']) && $_GET['ajax'] === 'staff_users') {
    header('Content-Type: application/json');
    $rows = $pdo->query("SELECT id, username FROM users WHERE role = 'barangay_staff' ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows);
    exit;
}

// ── AJAX: get single barangay ──
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $stmt = $pdo->prepare("
        SELECT b.*, u.id AS staff_user_id
        FROM barangays b
        LEFT JOIN users u ON u.barangay_id = b.id AND u.role = 'barangay_staff'
        WHERE b.id = ?
    ");
    $stmt->execute([$_GET['id']]);
    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
    exit;
}

/**
 * Check if two polygons overlap using point-in-polygon tests.
 */
function polygons_overlap(array $polyA, array $polyB): bool
{
    foreach ($polyA as $pt) {
        if (point_in_ring($pt[1], $pt[0], $polyB)) return true;
    }
    foreach ($polyB as $pt) {
        if (point_in_ring($pt[1], $pt[0], $polyA)) return true;
    }
    return false;
}

function point_in_ring(float $lat, float $lng, array $ring): bool
{
    $n = count($ring);
    $inside = false;
    for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
        $yi = $ring[$i][1]; $xi = $ring[$i][0];
        $yj = $ring[$j][1]; $xj = $ring[$j][0];
        if (($yi > $lat) !== ($yj > $lat) &&
            ($lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi)) {
            $inside = !$inside;
        }
    }
    return $inside;
}

// Determine mode: edit existing or add new
$editId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$pageTitle = $editId ? 'Edit Barangay' : 'Add New Barangay';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - Sablayan Risk Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css" />

<!-- Leaflet Fullscreen CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet.fullscreen@2.4.0/dist/leaflet.fullscreen.css" />
    <style>
        body { background: #f0f2f5; }
        .main-content { margin-left: 0; }
        @media(min-width:768px){ .main-content { margin-left: 16.666667%; } }
        #boundaryMap { height: 500px; border-radius: .5rem; border: 2px solid #dee2e6; }
        .area-card { border-left: 4px solid; border-radius: .5rem; }
        .area-card.manual { border-color: #6c757d; }
        .area-card.calculated { border-color: #0d6efd; }
        .overlap-alert { display: none; }
        .validation-msg { display: none; }
        .legend-box { display: inline-block; width: 14px; height: 14px; border-radius: 2px; margin-right: 6px; vertical-align: middle; }
    </style>

    <style type="text/css">
        
        /* Style for database boundary labels */
.database-boundary-label {
    background: rgba(230, 126, 34, 0.8);
    color: white !important;
    font-weight: bold;
    padding: 2px 6px;
    border-radius: 4px;
    border: 1px solid white;
    font-size: 11px;
    white-space: nowrap;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

/* Style for the legend */
.legend-box {
    display: inline-block;
    width: 16px;
    height: 16px;
    border-radius: 3px;
    margin-right: 6px;
    vertical-align: middle;
}

.legend-box.database {
    background: rgba(230, 126, 34, 0.3);
    border: 2px dashed #e67e22;
}

.legend-box.osm {
    background: rgba(0, 0, 0, 0.1);
    border: 2px solid #3388ff;
}

/* Fullscreen styles */
:-webkit-full-screen #boundaryMap {
    width: 100%;
    height: 100%;
}

:-moz-full-screen #boundaryMap {
    width: 100%;
    height: 100%;
}

:fullscreen #boundaryMap {
    width: 100%;
    height: 100%;
}

.leaflet-control-fullscreen a {
    background: #fff url('https://unpkg.com/leaflet.fullscreen/icon-fullscreen.png') no-repeat 0 0;
    background-size: 26px 52px;
    width: 34px;
    height: 34px;
    line-height: 34px;
    border-radius: 4px;
    box-shadow: 0 1px 5px rgba(0,0,0,0.65);
}

.leaflet-control-fullscreen a:hover {
    background-position: 0 -26px;
}

#boundaryMap {
    min-height: 500px;
    transition: all 0.3s ease;
}
    </style>
</head>
<body>
<div class="container-fluid">
<div class="row">
    <?php include 'sidebar.php'; ?>

    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h4 class="fw-bold">
                <i class="fas fa-<?= $editId ? 'edit' : 'plus-circle' ?> me-2"></i><?= $pageTitle ?>
            </h4>
            <a href="barangay_management.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Back to List
            </a>
        </div>

        <div class="row g-4">
            <!-- LEFT: Form -->
            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-1"></i> Barangay Information</h6>
                    </div>
                    <div class="card-body">
                        <form id="brgyForm">
                            <input type="hidden" name="id" id="brgyId" value="<?= $editId ?>">
                            <input type="hidden" name="boundary_geojson" id="boundaryGeoJSON" value="">
                            <input type="hidden" name="calculated_area_km2" id="calcAreaVal" value="">

                            <div class="mb-3">
                                <label class="form-label">Barangay Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" id="brgyName" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Official Area (km&sup2;) <span class="text-muted small">Manual</span></label>
                                <input type="number" step="0.01" class="form-control" name="area_km2" id="brgyArea" placeholder="Enter official area">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Center Coordinates <span class="text-muted small">lat, lng</span></label>
                                <input type="text" class="form-control" name="coordinates" id="brgyCoords" placeholder="e.g. 12.8435, 120.8754">
                                <div class="form-text">Used as a fallback center point. Auto-detected from boundary if drawn.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Assign Staff User</label>
                                <select class="form-select" name="staff_user_id" id="brgyStaff">
                                    <option value="">-- None --</option>
                                </select>
                            </div>

                            <hr>

                            <!-- Area Comparison -->
                            <h6 class="mb-2"><i class="fas fa-ruler-combined me-1 text-primary"></i> Area Comparison</h6>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <div class="card area-card manual p-2 text-center">
                                        <div class="text-muted" style="font-size:.7rem">Manual (Official)</div>
                                        <div class="fw-bold fs-6" id="dispManualArea">--</div>
                                        <div class="text-muted" style="font-size:.65rem">km&sup2;</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="card area-card calculated p-2 text-center">
                                        <div class="text-muted" style="font-size:.7rem">Calculated (Map)</div>
                                        <div class="fw-bold fs-6 text-primary" id="dispCalcArea">--</div>
                                        <div class="text-muted" style="font-size:.65rem">km&sup2;</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Overlap Warning -->
                            <div class="alert alert-warning small py-2 overlap-alert" id="overlapAlert">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                <strong>Overlap Detected!</strong>
                                <div id="overlapDetails"></div>
                            </div>

                            <!-- Validation Message -->
                            <div class="small validation-msg mb-2" id="validationMsg"></div>

                            <!-- Computed Stats (edit mode) -->
                            <div id="computedStats" class="d-none mb-3">
                                <hr>
                                <h6 class="text-muted small">Computed Statistics (read-only)</h6>
                                <div class="row g-1" id="statsGrid"></div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary" id="saveBtn">
                                    <i class="fas fa-save me-1"></i> Save Barangay
                                </button>
                                <a href="barangay_management.php" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Map -->
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-draw-polygon me-1"></i> Draw Boundary Polygon</h6>
                        <div class="small text-muted">
                            <span class="legend-box" style="background:rgba(230,126,34,0.3); border:2px solid #e67e22"></span>Existing
                            <span class="legend-box ms-2" style="background:rgba(13,110,253,0.15); border:2px solid #0d6efd"></span>Current
                        </div>
                    </div>
                    <div class="card-body p-2">
                        <div class="alert alert-info small py-2 mb-2">
                            <i class="fas fa-info-circle me-1"></i>
                            Use the polygon tool on the top-right of the map to draw the barangay boundary.
                            Existing boundaries are shown in <strong>orange</strong> for reference.
                            Your boundary must not overlap with any existing barangay.
                        </div>
                        <div id="boundaryMap"></div>
                        <div class="d-flex justify-content-between mt-2">
                            <div class="small text-muted" id="coordsDisplay">Click map to see coordinates</div>
                            <button type="button" class="btn btn-outline-danger btn-sm d-none" id="clearBoundaryBtn" onclick="clearBoundary()">
                                <i class="fas fa-trash me-1"></i> Clear Boundary
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>
<!-- Leaflet Fullscreen JS -->
<script src="https://cdn.jsdelivr.net/npm/leaflet.fullscreen@2.4.0/dist/Leaflet.fullscreen.min.js"></script>

<script>
const editId = <?= $editId ? $editId : 'null' ?>;
let existingBoundaries = [];
let hasOverlap = false;

// ── Tile layers with better boundary visibility ──

// Standard OpenStreetMap
const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
});

// Humanitarian style - SHOWS BOUNDARIES VERY CLEARLY (recommended)
const humanitarian = L.tileLayer('https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>, Tiles style by Humanitarian OpenStreetMap Team'
});

// OpenStreetMap France style - also good boundaries
const osmFrance = L.tileLayer('https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png', {
    maxZoom: 20,
    attribution: '&copy; OpenStreetMap France | &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
});

// Satellite only
const satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
    maxZoom: 19,
    attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community'
});

// Terrain
const terrain = L.tileLayer('https://tile.opentopomap.org/{z}/{x}/{y}.png', {
    maxZoom: 17,
    attribution: 'Map data: &copy; <a href="https://www.opentopomap.org">OpenTopoMap</a> contributors'
});

// FIXED HYBRID: Satellite with visible boundaries from Humanitarian layer
const hybrid = L.layerGroup([
    // Base satellite imagery
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19,
        attribution: 'Tiles &copy; Esri'
    }),
    // Humanitarian overlay (shows boundaries and labels clearly)
    L.tileLayer('https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png', {
        maxZoom: 19,
        opacity: 0.6,
        attribution: 'Boundaries &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    })
]);

// CartoDB Light (clean option with boundaries)
const cartoDB = L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>, &copy; CartoDB'
});

// Initialize map with Humanitarian layer (best for seeing boundaries)
const map = L.map('boundaryMap', { 
    layers: [humanitarian],  // Start with Humanitarian to see boundaries clearly
    center: [12.84, 120.87], 
    zoom: 11 
});

// Remove the L.control.fullscreen line and replace with this custom solution:

// Custom fullscreen button
var fullscreenControl = L.control({ position: 'topleft' });

fullscreenControl.onAdd = function(map) {
    var container = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-control-custom');
    
    container.innerHTML = '<a href="#" title="Toggle Fullscreen" style="background-color: white; width: 34px; height: 34px; line-height: 34px; text-align: center; font-size: 20px; display: block;">⛶</a>';
    
    container.onclick = function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var mapContainer = map.getContainer();
        
        if (!document.fullscreenElement) {
            // Enter fullscreen
            if (mapContainer.requestFullscreen) {
                mapContainer.requestFullscreen();
            } else if (mapContainer.mozRequestFullScreen) {
                mapContainer.mozRequestFullScreen();
            } else if (mapContainer.webkitRequestFullscreen) {
                mapContainer.webkitRequestFullscreen();
            } else if (mapContainer.msRequestFullscreen) {
                mapContainer.msRequestFullscreen();
            }
        } else {
            // Exit fullscreen
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.mozCancelFullScreen) {
                document.mozCancelFullScreen();
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            } else if (document.msExitFullscreen) {
                document.msExitFullscreen();
            }
        }
        
        // Invalidate map size after fullscreen change
        setTimeout(function() {
            map.invalidateSize();
        }, 100);
    };
    
    return container;
};

fullscreenControl.addTo(map);

// Listen for fullscreen change events
document.addEventListener('fullscreenchange', function() {
    setTimeout(function() { map.invalidateSize(); }, 100);
});

document.addEventListener('webkitfullscreenchange', function() {
    setTimeout(function() { map.invalidateSize(); }, 100);
});

document.addEventListener('mozfullscreenchange', function() {
    setTimeout(function() { map.invalidateSize(); }, 100);
});

document.addEventListener('MSFullscreenChange', function() {
    setTimeout(function() { map.invalidateSize(); }, 100);
});
// Add layer controls with descriptive names
L.control.layers({
    '🌍 Humanitarian (Best Boundaries)': humanitarian,
    '🗺️ OpenStreetMap Standard': osm,
    '🇫🇷 OpenStreetMap France': osmFrance,
    '🛰️ Satellite Only': satellite,
    '🗻 Terrain': terrain,
    '🛰️➕ Hybrid Satellite + Boundaries': hybrid,
    '☀️ CartoDB Light': cartoDB
}, null, {
    position: 'topright'
}).addTo(map);

// Drawing layer
const drawnItems = new L.FeatureGroup();
map.addLayer(drawnItems);

const drawControl = new L.Control.Draw({
    position: 'topright',
    draw: {
        polygon: {
            allowIntersection: false,
            shapeOptions: { color: '#0d6efd', weight: 3, fillOpacity: 0.15 }
        },
        polyline: false, 
        rectangle: false, 
        circle: false, 
        circlemarker: false, 
        marker: false
    },
    edit: { featureGroup: drawnItems }
});
map.addControl(drawControl);

// Existing boundaries overlay (orange) - YOUR DRAWN BOUNDARIES
const existingLayer = L.layerGroup().addTo(map);

// Mouse coordinate display
map.on('mousemove', function(e){
    $('#coordsDisplay').text('Lat: ' + e.latlng.lat.toFixed(6) + ', Lng: ' + e.latlng.lng.toFixed(6));
});

// Handle fullscreen change events
map.on('fullscreenchange', function() {
    if (map.isFullscreen()) {
        console.log('Entered fullscreen');
        setTimeout(function(){ map.invalidateSize(); }, 100);
    } else {
        console.log('Exited fullscreen');
        setTimeout(function(){ map.invalidateSize(); }, 100);
    }
});

// ── Area calculation (geodesic) ──
function calcPolygonAreaKm2(latlngs) {
    const R = 6371.0;
    const toRad = Math.PI / 180;
    let n = latlngs.length;
    if (n < 3) return 0;
    let total = 0;
    for (let i = 0; i < n; i++) {
        let j = (i + 1) % n;
        let lat1 = latlngs[i].lat * toRad, lng1 = latlngs[i].lng * toRad;
        let lat2 = latlngs[j].lat * toRad, lng2 = latlngs[j].lng * toRad;
        total += (lng2 - lng1) * (2 + Math.sin(lat1) + Math.sin(lat2));
    }
    return Math.abs(total * R * R / 2);
}

// ── Overlap detection (client-side) ──
function pointInPolygon(lat, lng, ring) {
    let inside = false;
    for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
        let yi = ring[i][1], xi = ring[i][0];
        let yj = ring[j][1], xj = ring[j][0];
        if ((yi > lat) !== (yj > lat) && (lng < (xj - xi) * (lat - yi) / (yj - yi) + xi)) {
            inside = !inside;
        }
    }
    return inside;
}

function checkOverlap(newGeoCoords) {
    let overlaps = [];
    existingBoundaries.forEach(b => {
        if (b.id === editId) return; // skip self
        try {
            let other = JSON.parse(b.boundary_geojson);
            let otherRing = other.coordinates[0];
            for (let pt of newGeoCoords) {
                if (pointInPolygon(pt[1], pt[0], otherRing)) { 
                    overlaps.push(b.name); 
                    return; 
                }
            }
            for (let pt of otherRing) {
                if (pointInPolygon(pt[1], pt[0], newGeoCoords)) { 
                    overlaps.push(b.name); 
                    return; 
                }
            }
        } catch(e){}
    });
    return overlaps;
}

// ── Validate drawn polygon ──
function validatePolygon(layer) {
    let latlngs = layer.getLatLngs()[0];
    if (!latlngs || latlngs.length < 3) {
        showValidation('Polygon must have at least 3 points.', 'danger');
        hasOverlap = true;
        return;
    }

    // Calculate area
    let areaKm2 = calcPolygonAreaKm2(latlngs);
    let calcArea = Math.round(areaKm2 * 10000) / 10000;
    $('#dispCalcArea').text(calcArea.toFixed(4));
    $('#calcAreaVal').val(calcArea);

    // Store GeoJSON
    let geojson = layer.toGeoJSON().geometry;
    $('#boundaryGeoJSON').val(JSON.stringify(geojson));

    // Auto-calculate center coordinates
    let bounds = layer.getBounds();
    let center = bounds.getCenter();
    if (!$('#brgyCoords').val()) {
        $('#brgyCoords').val(center.lat.toFixed(6) + ', ' + center.lng.toFixed(6));
    }

    // Overlap check
    let geoCoords = geojson.coordinates[0];
    let overlaps = checkOverlap(geoCoords);
    if (overlaps.length > 0) {
        hasOverlap = true;
        $('#overlapDetails').text('Overlaps with: ' + overlaps.join(', ') + '. Please redraw.');
        $('#overlapAlert').slideDown();
        showValidation('Cannot save: boundary overlaps with existing barangays.', 'danger');
        $('#saveBtn').prop('disabled', true);
    } else {
        hasOverlap = false;
        $('#overlapAlert').slideUp();
        showValidation('Polygon valid (' + latlngs.length + ' points, ' + calcArea.toFixed(4) + ' km²). Ready to save.', 'success');
        $('#saveBtn').prop('disabled', false);
    }

    $('#clearBoundaryBtn').removeClass('d-none');
}

function showValidation(msg, type) {
    let color = type === 'danger' ? '#dc3545' : '#198754';
    let icon = type === 'danger' ? 'exclamation-circle' : 'check-circle';
    $('#validationMsg').html(`<i class="fas fa-${icon}" style="color:${color}"></i> <span style="color:${color}">${msg}</span>`).show();
}

function clearBoundary() {
    if (!confirm('Remove the drawn boundary?')) return;
    drawnItems.clearLayers();
    $('#boundaryGeoJSON').val('');
    $('#calcAreaVal').val('');
    $('#dispCalcArea').text('--');
    $('#clearBoundaryBtn').addClass('d-none');
    $('#overlapAlert').hide();
    $('#validationMsg').hide();
    hasOverlap = false;
    $('#saveBtn').prop('disabled', false);

    // If editing, also clear from server
    if (editId) {
        $.post('barangay_boundary.php?ajax=delete_boundary', {barangay_id: editId}, function(res){
            if (res.ok) showToast('Boundary removed.', 'warning');
        }, 'json');
    }
}

// ── Draw events ──
map.on(L.Draw.Event.CREATED, function(e){
    drawnItems.clearLayers();
    drawnItems.addLayer(e.layer);
    validatePolygon(e.layer);
});

map.on(L.Draw.Event.EDITED, function(e){
    let layers = drawnItems.getLayers();
    if (layers.length > 0) validatePolygon(layers[0]);
});

map.on(L.Draw.Event.DELETED, function(e){
    $('#boundaryGeoJSON').val('');
    $('#calcAreaVal').val('');
    $('#dispCalcArea').text('--');
    $('#clearBoundaryBtn').addClass('d-none');
    $('#overlapAlert').hide();
    $('#validationMsg').hide();
    hasOverlap = false;
    $('#saveBtn').prop('disabled', false);
});

// ── Load existing boundaries from DATABASE as reference ──
function loadExistingBoundaries() {
    $.getJSON('barangay_boundary.php?ajax=boundaries', function(data){
        console.log('Loaded', data.length, 'existing boundaries from database');
        existingBoundaries = data;
        existingLayer.clearLayers();
        
        if (data.length === 0) {
            console.log('No existing boundaries in database');
            return;
        }
        
        let bounds = L.latLngBounds();
        
        data.forEach(b => {
            if (b.id === editId) return; // don't show self in existing layer
            
            try {
                let geo = JSON.parse(b.boundary_geojson);
                
                // More visible style for database boundaries
                let layer = L.geoJSON(geo, {
                    style: { 
                        color: '#e67e22',        // Orange border
                        weight: 3,                // Thicker border
                        fillOpacity: 0.15,         // Semi-transparent fill
                        fillColor: '#e67e22',      // Orange fill
                        dashArray: '5,5',          // Dashed line to distinguish from OSM boundaries
                        opacity: 0.9                // Border opacity
                    }
                });
                
                // Add tooltip with barangay name
                layer.bindTooltip(b.name, {
                    permanent: true, 
                    direction: 'center',
                    className: 'database-boundary-label'
                });
                
                existingLayer.addLayer(layer);
                
                // Extend bounds
                if (layer.getBounds().isValid()) {
                    bounds.extend(layer.getBounds());
                }
                
                console.log('Added database boundary:', b.name);
            } catch(e){
                console.error('Error parsing boundary for', b.name, e);
            }
        });
        
        // Don't auto-fit to database boundaries, let user see OSM boundaries too
    }).fail(function(xhr, status, error) {
        console.error('Failed to load database boundaries:', error);
    });
}

// ── Load staff dropdown ──
function loadStaffUsers() {
    $.getJSON('barangay_boundary.php?ajax=staff_users', function(users){
        let opts = '<option value="">-- None --</option>';
        users.forEach(u => opts += `<option value="${u.id}">${u.username}</option>`);
        $('#brgyStaff').html(opts);

        // If editing, load barangay data after staff dropdown is ready
        if (editId) loadBarangayData();
    });
}

// ── Load barangay data for editing ──
function loadBarangayData() {
    $.getJSON('barangay_boundary.php?ajax=get&id=' + editId, function(b){
        if (!b) return;
        $('#brgyName').val(b.name);
        $('#brgyArea').val(b.area_km2);
        $('#brgyCoords').val(b.coordinates);
        $('#brgyStaff').val(b.staff_user_id || '');

        // Manual area display
        if (b.area_km2) $('#dispManualArea').text(parseFloat(b.area_km2).toFixed(2));

        // Calculated area display
        if (b.calculated_area_km2) {
            $('#dispCalcArea').text(parseFloat(b.calculated_area_km2).toFixed(4));
            $('#calcAreaVal').val(b.calculated_area_km2);
        }

        // Load existing boundary into draw layer
        if (b.boundary_geojson) {
            try {
                let geo = JSON.parse(b.boundary_geojson);
                L.geoJSON(geo, {
                    style: { color: '#0d6efd', weight: 3, fillOpacity: 0.15 },
                    onEachFeature: function(feature, layer){ drawnItems.addLayer(layer); }
                });
                $('#boundaryGeoJSON').val(b.boundary_geojson);
                $('#clearBoundaryBtn').removeClass('d-none');

                // Zoom to boundary
                let bounds = drawnItems.getBounds();
                if (bounds.isValid()) map.fitBounds(bounds, {padding: [30, 30]});
            } catch(e){}
        } else if (b.coordinates) {
            // Zoom to center coords
            let parts = b.coordinates.split(',').map(Number);
            if (parts.length === 2) map.setView([parts[0], parts[1]], 14);
        }

        // Show computed stats
        let stats = [
            {label:'Households', val: b.household_count||0},
            {label:'Population', val: b.population||0},
            {label:'PWD', val: b.pwd_count||0},
            {label:'Seniors', val: b.senior_count||0},
            {label:'Children', val: b.children_count||0},
            {label:'Infants', val: b.infant_count||0},
            {label:'Pregnant', val: b.pregnant_count||0},
            {label:'IP', val: b.ip_count||0},
        ];
        let grid = '';
        stats.forEach(s => grid += `<div class="col-6 col-md-3"><div class="bg-light rounded p-1 text-center"><div style="font-size:.65rem" class="text-muted">${s.label}</div><div class="fw-bold" style="font-size:.85rem">${parseInt(s.val).toLocaleString()}</div></div></div>`);
        $('#statsGrid').html(grid);
        $('#computedStats').removeClass('d-none');
    });
}

// ── Update manual area display on input ──
$('#brgyArea').on('input', function(){
    let v = parseFloat($(this).val());
    $('#dispManualArea').text(isNaN(v) ? '--' : v.toFixed(2));
});

// ── Form submit ──
$('#brgyForm').on('submit', function(e){
    e.preventDefault();

    if (hasOverlap) {
        showToast('Cannot save: boundary overlaps with existing barangays.', 'danger');
        return;
    }

    let data = $(this).serialize();
    $.post('barangay_boundary.php?ajax=save', data, function(res){
        if (res.ok) {
            showToast(res.msg, 'success');
            // Redirect back to management after short delay
            setTimeout(function(){ window.location.href = 'barangay_management.php'; }, 1200);
        } else {
            showToast(res.msg, 'danger');
            // If server detected overlap
            if (res.msg && res.msg.includes('overlaps')) {
                hasOverlap = true;
                $('#overlapDetails').text(res.msg);
                $('#overlapAlert').slideDown();
                $('#saveBtn').prop('disabled', true);
            }
        }
    }, 'json');
});

function showToast(msg, type){
    let id = 'toast' + Date.now();
    $('body').append(`<div id="${id}" class="position-fixed bottom-0 end-0 p-3" style="z-index:9999">
        <div class="toast show align-items-center text-bg-${type} border-0" role="alert">
            <div class="d-flex"><div class="toast-body">${msg}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>
        </div></div>`);
    setTimeout(() => $('#'+id).remove(), 4000);
}

// ── Init ──
$(document).ready(function(){
    loadStaffUsers();
    loadExistingBoundaries();
    setTimeout(function(){ map.invalidateSize(); }, 300);
    
    // Add a message to show which layer has best boundaries
    console.log('Map initialized. Use Humanitarian layer for best boundary visibility.');
});
</script>