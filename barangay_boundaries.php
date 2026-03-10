<?php
// barangay_boundaries.php — Admin only
// Phase 3: Barangay boundary drawing with Leaflet Draw

session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Add boundary_geojson column to barangays if it doesn't exist
try {
    $pdo->exec("ALTER TABLE barangays ADD COLUMN IF NOT EXISTS boundary_geojson LONGTEXT DEFAULT NULL");
} catch (PDOException $e) {
    // Column already exists
}

// Handle AJAX save boundary
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_boundary') {
    header('Content-Type: application/json');
    $barangay_id = (int)$_POST['barangay_id'];
    $geojson = $_POST['geojson'];

    // Basic validation — must be parseable JSON
    $parsed = json_decode($geojson, true);
    if (!$parsed) {
        echo json_encode(['success' => false, 'message' => 'Invalid GeoJSON']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE barangays SET boundary_geojson = ? WHERE id = ?");
    $stmt->execute([$geojson, $barangay_id]);

    echo json_encode(['success' => true, 'message' => 'Boundary saved']);
    exit;
}

// Handle AJAX clear boundary
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_boundary') {
    header('Content-Type: application/json');
    $barangay_id = (int)$_POST['barangay_id'];
    $stmt = $pdo->prepare("UPDATE barangays SET boundary_geojson = NULL WHERE id = ?");
    $stmt->execute([$barangay_id]);
    echo json_encode(['success' => true]);
    exit;
}

// Get all barangays with boundary status
$barangays = $pdo->query("
    SELECT id, name, coordinates, boundary_geojson,
           CASE WHEN boundary_geojson IS NOT NULL THEN 1 ELSE 0 END as has_boundary
    FROM barangays
    ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

$total = count($barangays);
$mapped = array_sum(array_column($barangays, 'has_boundary'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Boundaries — Sablayan Risk Assessment</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css">
    <style>
        body { margin: 0; font-family: sans-serif; }
        .page-wrapper { display: flex; height: 100vh; overflow: hidden; }
        .side-panel {
            width: 300px;
            min-width: 300px;
            background: #1f4061;
            color: #fff;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .side-header {
            padding: 16px;
            border-bottom: 1px solid rgba(255,255,255,0.15);
            background: #162e45;
        }
        .side-header h5 { margin: 0; font-size: 1rem; }
        .progress-info { font-size: 0.8rem; opacity: 0.8; margin-top: 4px; }
        .barangay-list { flex: 1; overflow-y: auto; padding: 8px 0; }
        .barangay-item {
            display: flex; align-items: center;
            padding: 10px 16px; cursor: pointer;
            border-bottom: 1px solid rgba(255,255,255,0.07);
            transition: background 0.15s;
        }
        .barangay-item:hover { background: rgba(255,255,255,0.08); }
        .barangay-item.active { background: rgba(255,255,255,0.15); }
        .barangay-item .status-icon { margin-right: 10px; font-size: 0.9rem; }
        .barangay-item .brgy-name { flex: 1; font-size: 0.9rem; }
        .status-ok { color: #28a745; }
        .status-missing { color: #ffc107; }
        #map { flex: 1; }
        .map-toolbar {
            position: absolute; top: 10px; left: 310px; z-index: 1000;
            display: flex; gap: 6px;
        }
        .map-toolbar .btn { box-shadow: 0 2px 6px rgba(0,0,0,0.3); }
        #selectedBarangayLabel {
            position: absolute; bottom: 10px; left: 310px; z-index: 1000;
            background: rgba(0,0,0,0.7); color: #fff;
            padding: 6px 12px; border-radius: 4px; font-size: 0.85rem;
        }
        .back-nav {
            padding: 10px 16px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
    </style>
</head>
<body>
<div class="page-wrapper">
    <!-- Side panel -->
    <div class="side-panel">
        <div class="side-header">
            <h5><i class="fas fa-draw-polygon me-2"></i>Barangay Boundaries</h5>
            <div class="progress-info">
                <span class="text-success"><?php echo $mapped; ?> mapped</span> /
                <?php echo $total; ?> barangays
                <div class="progress mt-1" style="height:4px;">
                    <div class="progress-bar bg-success" style="width:<?php echo $total ? round($mapped/$total*100) : 0; ?>%"></div>
                </div>
            </div>
        </div>

        <div class="barangay-list" id="barangayList">
            <?php foreach ($barangays as $b): ?>
            <div class="barangay-item" data-id="<?php echo $b['id']; ?>"
                 data-name="<?php echo htmlspecialchars($b['name']); ?>"
                 data-coords="<?php echo htmlspecialchars($b['coordinates']); ?>"
                 data-geojson="<?php echo htmlspecialchars($b['boundary_geojson'] ?? ''); ?>">
                <span class="status-icon <?php echo $b['has_boundary'] ? 'status-ok' : 'status-missing'; ?>">
                    <i class="fas <?php echo $b['has_boundary'] ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                </span>
                <span class="brgy-name"><?php echo htmlspecialchars($b['name']); ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="back-nav">
            <a href="dashboard.php" class="btn btn-outline-light btn-sm w-100">
                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Map -->
    <div style="flex:1; position:relative;">
        <div class="map-toolbar">
            <button class="btn btn-sm btn-primary" id="btnSaveBoundary" disabled>
                <i class="fas fa-save me-1"></i> Save Boundary
            </button>
            <button class="btn btn-sm btn-danger" id="btnClearBoundary" disabled>
                <i class="fas fa-trash me-1"></i> Clear Boundary
            </button>
        </div>
        <div id="map" style="height:100vh;"></div>
        <div id="selectedBarangayLabel">Click a barangay in the list to start drawing its boundary.</div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
<script>
const SABLAYAN = [12.8333, 120.7667];
const map = L.map('map').setView(SABLAYAN, 12);
const streetTile = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors', maxZoom: 19
});
const satelliteTile = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
    attribution: 'Tiles &copy; Esri', maxZoom: 19
});
satelliteTile.addTo(map);
L.control.layers({ 'Satellite': satelliteTile, 'Street': streetTile }, {}, { position: 'topright' }).addTo(map);

// Leaflet Draw setup
const drawnItems = new L.FeatureGroup();
map.addLayer(drawnItems);

const drawControl = new L.Control.Draw({
    draw: {
        polygon: {
            allowIntersection: false,
            shapeOptions: { color: '#3388ff', weight: 2, fillOpacity: 0.2 }
        },
        polyline: false, rectangle: false, circle: false,
        marker: false, circlemarker: false
    },
    edit: { featureGroup: drawnItems }
});
map.addControl(drawControl);

map.on(L.Draw.Event.CREATED, function(e) {
    drawnItems.clearLayers();
    drawnItems.addLayer(e.layer);
    document.getElementById('btnSaveBoundary').disabled = !currentBarangay;
});

map.on(L.Draw.Event.EDITED, function() {
    document.getElementById('btnSaveBoundary').disabled = !currentBarangay;
});

map.on(L.Draw.Event.DELETED, function() {
    document.getElementById('btnSaveBoundary').disabled = true;
});

let currentBarangay = null;
let centerMarker = null;

// Select barangay from list
document.querySelectorAll('.barangay-item').forEach(function(item) {
    item.addEventListener('click', function() {
        // Remove active class from all
        document.querySelectorAll('.barangay-item').forEach(i => i.classList.remove('active'));
        this.classList.add('active');

        currentBarangay = {
            id: this.dataset.id,
            name: this.dataset.name,
            coords: this.dataset.coords,
            geojson: this.dataset.geojson
        };

        document.getElementById('selectedBarangayLabel').textContent =
            'Drawing boundary for: ' + currentBarangay.name;
        document.getElementById('btnSaveBoundary').disabled = false;
        document.getElementById('btnClearBoundary').disabled = !currentBarangay.geojson;

        // Clear existing drawn layers
        drawnItems.clearLayers();

        // Load existing boundary if present
        if (currentBarangay.geojson && currentBarangay.geojson.trim()) {
            try {
                const gj = JSON.parse(currentBarangay.geojson);
                const layer = L.geoJSON(gj, {
                    style: { color: '#3388ff', weight: 2, fillOpacity: 0.2 }
                });
                layer.eachLayer(function(l) { drawnItems.addLayer(l); });
                map.fitBounds(drawnItems.getBounds());
            } catch(e) {
                console.warn('Could not parse boundary GeoJSON');
            }
        } else if (currentBarangay.coords) {
            // Zoom to barangay center point
            const parts = currentBarangay.coords.split(',');
            if (parts.length >= 2) {
                const lat = parseFloat(parts[0]);
                const lng = parseFloat(parts[1]);
                if (!isNaN(lat) && !isNaN(lng)) {
                    map.setView([lat, lng], 14);
                }
            }
        }
    });
});

// Save boundary
document.getElementById('btnSaveBoundary').addEventListener('click', function() {
    if (!currentBarangay) return;
    if (drawnItems.getLayers().length === 0) {
        alert('Please draw a polygon boundary first.');
        return;
    }

    // Collect GeoJSON from drawn layers
    const geojsonData = drawnItems.toGeoJSON();
    const geojsonStr = JSON.stringify(geojsonData);

    const formData = new FormData();
    formData.append('action', 'save_boundary');
    formData.append('barangay_id', currentBarangay.id);
    formData.append('geojson', geojsonStr);

    fetch('barangay_boundaries.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(function(res) {
            if (res.success) {
                // Update side panel status icon
                const item = document.querySelector('.barangay-item[data-id="' + currentBarangay.id + '"]');
                if (item) {
                    item.dataset.geojson = geojsonStr;
                    item.querySelector('.status-icon').className = 'status-icon status-ok';
                    item.querySelector('.status-icon i').className = 'fas fa-check-circle';
                    currentBarangay.geojson = geojsonStr;
                }
                document.getElementById('btnClearBoundary').disabled = false;
                showToast('Boundary saved for ' + currentBarangay.name, 'success');
                updateProgressBar();
            } else {
                showToast('Error: ' + res.message, 'danger');
            }
        });
});

// Clear boundary
document.getElementById('btnClearBoundary').addEventListener('click', function() {
    if (!currentBarangay || !confirm('Remove boundary for ' + currentBarangay.name + '?')) return;

    const formData = new FormData();
    formData.append('action', 'clear_boundary');
    formData.append('barangay_id', currentBarangay.id);

    fetch('barangay_boundaries.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(function(res) {
            if (res.success) {
                drawnItems.clearLayers();
                const item = document.querySelector('.barangay-item[data-id="' + currentBarangay.id + '"]');
                if (item) {
                    item.dataset.geojson = '';
                    item.querySelector('.status-icon').className = 'status-icon status-missing';
                    item.querySelector('.status-icon i').className = 'fas fa-exclamation-circle';
                    currentBarangay.geojson = '';
                }
                this.disabled = true;
                document.getElementById('btnSaveBoundary').disabled = true;
                showToast('Boundary cleared', 'warning');
                updateProgressBar();
            }
        }.bind(this));
});

function updateProgressBar() {
    const total = document.querySelectorAll('.barangay-item').length;
    const mapped = document.querySelectorAll('.status-ok').length;
    document.querySelector('.progress-bar').style.width = (total ? Math.round(mapped/total*100) : 0) + '%';
    document.querySelector('.progress-info .text-success').textContent = mapped + ' mapped';
}

function showToast(msg, type) {
    const t = document.createElement('div');
    t.className = 'alert alert-' + type + ' position-fixed';
    t.style.cssText = 'bottom:20px;right:20px;z-index:9999;max-width:300px;';
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
}
</script>
</body>
</html>
