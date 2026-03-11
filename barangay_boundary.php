<?php
// barangay_boundary.php — Admin only — Draw barangay boundaries
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

// ── AJAX: save boundary ──
if (isset($_GET['ajax']) && $_GET['ajax'] === 'save_boundary' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $id      = (int)$_POST['barangay_id'];
    $geojson = $_POST['geojson'] ?? '';
    $calcArea = isset($_POST['calculated_area_km2']) ? (float)$_POST['calculated_area_km2'] : null;

    if (!$id) {
        echo json_encode(['ok' => false, 'msg' => 'No barangay selected.']);
        exit;
    }

    // Empty geojson = delete boundary
    if ($geojson === '' || $geojson === 'null') {
        $pdo->prepare("UPDATE barangays SET boundary_geojson = NULL, calculated_area_km2 = NULL WHERE id = ?")->execute([$id]);
        $pdo->prepare("INSERT INTO barangay_boundary_logs (barangay_id, action, drawn_by) VALUES (?, ?, ?)")
            ->execute([$id, 'updated', $_SESSION['user_id']]);
        echo json_encode(['ok' => true, 'msg' => 'Boundary removed.']);
        exit;
    }

    // Validate GeoJSON is parseable
    $decoded = json_decode($geojson, true);
    if (!$decoded || !isset($decoded['type'])) {
        echo json_encode(['ok' => false, 'msg' => 'Invalid GeoJSON data.']);
        exit;
    }

    // Validate minimum 3 points
    $coords = $decoded['coordinates'][0] ?? [];
    if (count($coords) < 4) { // GeoJSON polygons repeat first point, so 4 = 3 unique points
        echo json_encode(['ok' => false, 'msg' => 'Polygon must have at least 3 points.']);
        exit;
    }

    // Server-side overlap check
    $others = $pdo->prepare("SELECT id, name, boundary_geojson FROM barangays WHERE id != ? AND boundary_geojson IS NOT NULL AND boundary_geojson != ''");
    $others->execute([$id]);
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

    // Save boundary and calculated area
    $pdo->prepare("UPDATE barangays SET boundary_geojson = ?, calculated_area_km2 = ? WHERE id = ?")
        ->execute([$geojson, $calcArea, $id]);
    $pdo->prepare("INSERT INTO barangay_boundary_logs (barangay_id, action, drawn_by) VALUES (?, ?, ?)")
        ->execute([$id, 'updated', $_SESSION['user_id']]);

    echo json_encode(['ok' => true, 'msg' => 'Boundary saved successfully.', 'calculated_area_km2' => $calcArea]);
    exit;
}

// ── AJAX: list barangays with boundary status ──
if (isset($_GET['ajax']) && $_GET['ajax'] === 'list') {
    header('Content-Type: application/json');
    $rows = $pdo->query("
        SELECT id, name, coordinates, boundary_geojson, area_km2, calculated_area_km2,
               CASE WHEN boundary_geojson IS NOT NULL AND boundary_geojson != '' THEN 1 ELSE 0 END AS has_boundary
        FROM barangays ORDER BY name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows);
    exit;
}

/**
 * Check if two polygons overlap using point-in-polygon tests.
 * Tests if any vertex of polygon A is inside polygon B, or vice versa.
 */
function polygons_overlap(array $polyA, array $polyB): bool
{
    // Check if any vertex of A is inside B
    foreach ($polyA as $pt) {
        if (point_in_ring($pt[1], $pt[0], $polyB)) return true;
    }
    // Check if any vertex of B is inside A
    foreach ($polyB as $pt) {
        if (point_in_ring($pt[1], $pt[0], $polyA)) return true;
    }
    return false;
}

/**
 * Ray-casting point-in-polygon for a single ring.
 * $ring is array of [lng, lat] pairs (GeoJSON order).
 */
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Boundary Drawing - Sablayan Risk Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css" />
    <style>
        html, body { height: 100%; margin: 0; overflow: hidden; }
        #map { height: 100vh; width: 100%; }
        .side-panel {
            position: fixed; top: 0; left: 0; width: 360px; height: 100vh;
            background: #fff; z-index: 1000; overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,.15);
        }
        .side-panel .header { background: #212529; color: #fff; padding: 1rem; }
        .brgy-item { padding: .6rem 1rem; border-bottom: 1px solid #eee; cursor: pointer; transition: background .15s; }
        .brgy-item:hover { background: #f0f2f5; }
        .brgy-item.active { background: #e3f2fd; border-left: 3px solid #0d6efd; }
        .status-icon { font-size: .85rem; }
        .map-container { margin-left: 360px; }
        @media(max-width:768px){ .side-panel { width: 100%; height: auto; max-height: 40vh; position: relative; } .map-container { margin-left: 0; } }
        .progress-badge { font-size: .8rem; padding: .3rem .6rem; }
        .area-panel { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: .5rem; padding: .75rem; margin: .75rem 1rem; }
        .area-value { font-size: 1.1rem; font-weight: 700; }
        .overlap-warning { background: #fff3cd; border: 1px solid #ffc107; border-radius: .5rem; padding: .5rem .75rem; margin: .5rem 1rem; display: none; }
        .save-section { padding: .75rem 1rem; border-top: 1px solid #dee2e6; }
        .legend-existing { display: inline-block; width: 16px; height: 3px; background: #e67e22; margin-right: 4px; vertical-align: middle; }
        .legend-active { display: inline-block; width: 16px; height: 3px; background: #0d6efd; margin-right: 4px; vertical-align: middle; }
    </style>
</head>
<body>

<div class="side-panel">
    <div class="header">
        <a href="barangay_management.php" class="text-white text-decoration-none"><i class="fas fa-arrow-left me-2"></i></a>
        <span class="fw-bold">Barangay Boundaries</span>
        <div class="mt-2">
            <span class="badge progress-badge bg-success" id="progressBadge">0 / 0 mapped</span>
        </div>
        <div class="mt-1" style="font-size:.75rem">
            <span class="legend-existing"></span> Existing boundaries
            <span class="legend-active ms-2"></span> Active / drawing
        </div>
    </div>

    <!-- Area display panel -->
    <div class="area-panel" id="areaPanel" style="display:none">
        <h6 class="mb-2"><i class="fas fa-ruler-combined me-1 text-primary"></i> Area Comparison</h6>
        <div class="row g-2">
            <div class="col-6">
                <div class="text-muted" style="font-size:.75rem">Manual Area (Official)</div>
                <div class="area-value text-dark" id="manualArea">--</div>
                <div class="text-muted" style="font-size:.7rem">km&sup2;</div>
            </div>
            <div class="col-6">
                <div class="text-muted" style="font-size:.75rem">Calculated Map Area</div>
                <div class="area-value text-primary" id="calcArea">--</div>
                <div class="text-muted" style="font-size:.7rem">km&sup2;</div>
            </div>
        </div>
    </div>

    <!-- Overlap warning -->
    <div class="overlap-warning" id="overlapWarning">
        <i class="fas fa-exclamation-triangle text-warning me-1"></i>
        <strong>Overlap Detected!</strong>
        <div class="small" id="overlapDetails">This polygon overlaps existing boundaries. Please redraw.</div>
    </div>

    <!-- Validation info -->
    <div id="validationInfo" class="px-3 py-1" style="display:none">
        <div class="small" id="validationMsg"></div>
    </div>

    <!-- Save section -->
    <div class="save-section" id="saveSection" style="display:none">
        <button class="btn btn-primary w-100" id="saveBoundaryBtn" onclick="confirmSave()">
            <i class="fas fa-save me-1"></i> Save Boundary
        </button>
    </div>

    <div id="brgyList"></div>
</div>

<div class="map-container">
    <div id="map"></div>
</div>

<!-- Save Confirm Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" style="z-index:2000">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white py-2">
        <h6 class="modal-title mb-0">Confirm Save</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-1">Save this boundary for <strong id="confirmName"></strong>?</p>
        <p class="small text-muted mb-0">Calculated area: <span id="confirmArea"></span> km&sup2;</p>
      </div>
      <div class="modal-footer py-1">
        <button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-sm btn-primary" onclick="doSave()">Confirm Save</button>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>
<script>
// Tile layers
const osm      = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom:19, attribution:'OpenStreetMap'});
const satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {maxZoom:19, attribution:'Esri Satellite'});
const terrain   = L.tileLayer('https://tile.opentopomap.org/{z}/{x}/{y}.png', {maxZoom:17, attribution:'OpenTopoMap'});
const hybrid    = L.layerGroup([
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {maxZoom:19}),
    L.tileLayer('https://stamen-tiles.a.ssl.fastly.net/toner-labels/{z}/{x}/{y}.png', {maxZoom:19, opacity:0.7})
]);

const map = L.map('map', { layers: [satellite], center: [12.84, 120.87], zoom: 11 });
L.control.layers({'OpenStreetMap': osm, 'Satellite': satellite, 'Terrain': terrain, 'Hybrid': hybrid}).addTo(map);

// Drawing layer (active barangay polygon — blue)
const drawnItems = new L.FeatureGroup();
map.addLayer(drawnItems);

const drawControl = new L.Control.Draw({
    position: 'topright',
    draw: {
        polygon: {
            allowIntersection: false,
            shapeOptions: { color: '#0d6efd', weight: 3, fillOpacity: 0.15 }
        },
        polyline: false, rectangle: false, circle: false, circlemarker: false, marker: false
    },
    edit: { featureGroup: drawnItems }
});
map.addControl(drawControl);

// All existing boundary layers (non-editing) — orange/brown
const allBoundariesLayer = L.layerGroup().addTo(map);

let selectedBarangayId = null;
let barangayData = [];
let pendingGeoJSON = null;
let pendingCalcArea = null;
let hasOverlap = false;

const confirmModal = new bootstrap.Modal('#confirmModal');
const urlId = new URLSearchParams(location.search).get('id');

// ── Area Calculation (Spherical Excess / Geodesic) ──
function calcPolygonAreaKm2(latlngs) {
    // Shoelfield formula on WGS84 sphere (Earth radius 6371 km)
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
    let area = Math.abs(total * R * R / 2);
    return area;
}

// ── Overlap Detection (client-side) ──
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
    barangayData.forEach(b => {
        if (b.id === selectedBarangayId) return;
        if (!b.boundary_geojson) return;
        try {
            let other = JSON.parse(b.boundary_geojson);
            let otherRing = other.coordinates[0];
            // Check if any vertex of new is inside other
            for (let pt of newGeoCoords) {
                if (pointInPolygon(pt[1], pt[0], otherRing)) {
                    overlaps.push(b.name);
                    return;
                }
            }
            // Check if any vertex of other is inside new
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
function validateAndShowArea(layer) {
    let latlngs = layer.getLatLngs()[0];
    if (!latlngs || latlngs.length < 3) {
        showValidation('Polygon must have at least 3 points.', 'danger');
        hasOverlap = true; // block save
        return;
    }

    // Calculate area
    let areaKm2 = calcPolygonAreaKm2(latlngs);
    pendingCalcArea = Math.round(areaKm2 * 10000) / 10000;
    $('#calcArea').text(pendingCalcArea.toFixed(4));

    // Get GeoJSON coords for overlap check
    let geojson = layer.toGeoJSON().geometry;
    pendingGeoJSON = JSON.stringify(geojson);
    let geoCoords = geojson.coordinates[0];

    // Overlap check
    let overlaps = checkOverlap(geoCoords);
    if (overlaps.length > 0) {
        hasOverlap = true;
        $('#overlapDetails').text('Overlaps with: ' + overlaps.join(', ') + '. Please redraw.');
        $('#overlapWarning').slideDown();
        showValidation('Cannot save: boundary overlaps with existing barangays.', 'danger');
        $('#saveBoundaryBtn').prop('disabled', true).removeClass('btn-primary').addClass('btn-secondary');
    } else {
        hasOverlap = false;
        $('#overlapWarning').slideUp();
        showValidation('Polygon valid. ' + latlngs.length + ' points. Ready to save.', 'success');
        $('#saveBoundaryBtn').prop('disabled', false).removeClass('btn-secondary').addClass('btn-primary');
    }

    $('#saveSection').show();
    $('#areaPanel').show();
}

function showValidation(msg, type) {
    let color = type === 'danger' ? '#dc3545' : type === 'success' ? '#198754' : '#6c757d';
    let icon = type === 'danger' ? 'times-circle' : 'check-circle';
    $('#validationMsg').html(`<i class="fas fa-${icon} me-1" style="color:${color}"></i> <span style="color:${color}">${msg}</span>`);
    $('#validationInfo').show();
}

// ── Load barangays ──
function loadBarangays(){
    $.getJSON('barangay_boundary.php?ajax=list', function(data){
        barangayData = data;
        let mapped = data.filter(b => b.has_boundary).length;
        $('#progressBadge').text(mapped + ' / ' + data.length + ' mapped');

        let html = '';
        data.forEach(b => {
            let icon = b.has_boundary
                ? '<i class="fas fa-check-circle text-success status-icon"></i>'
                : '<i class="fas fa-exclamation-circle text-danger status-icon"></i>';
            let areaInfo = '';
            if (b.has_boundary && b.calculated_area_km2) {
                areaInfo = `<span class="text-muted" style="font-size:.7rem; float:right">${parseFloat(b.calculated_area_km2).toFixed(2)} km&sup2;</span>`;
            }
            html += `<div class="brgy-item" data-id="${b.id}" onclick="selectBarangay(${b.id})">
                ${icon} <span class="ms-2">${b.name}</span>${areaInfo}
            </div>`;
        });
        $('#brgyList').html(html);

        // Show all existing boundaries
        showAllBoundaries();

        // Pre-select from URL
        if (urlId) selectBarangay(parseInt(urlId));
    });
}

function showAllBoundaries(){
    allBoundariesLayer.clearLayers();
    barangayData.forEach(b => {
        if (!b.boundary_geojson) return;
        // Skip the currently selected barangay (its boundary is in drawnItems)
        if (b.id === selectedBarangayId) return;
        try {
            let geo = JSON.parse(b.boundary_geojson);
            let layer = L.geoJSON(geo, {
                style: { color: '#e67e22', weight: 2, fillOpacity: 0.08, dashArray: '5,5' }
            });
            layer.bindTooltip(b.name, {permanent: true, direction: 'center', className: 'bg-transparent border-0 text-dark fw-bold shadow-none'});
            allBoundariesLayer.addLayer(layer);
        } catch(e){}
    });
}

function selectBarangay(id){
    selectedBarangayId = id;
    pendingGeoJSON = null;
    pendingCalcArea = null;
    hasOverlap = false;

    $('.brgy-item').removeClass('active');
    $(`.brgy-item[data-id="${id}"]`).addClass('active');

    let b = barangayData.find(x => x.id === id);
    if (!b) return;

    // Show area panel
    $('#manualArea').text(b.area_km2 ? parseFloat(b.area_km2).toFixed(2) : '--');
    $('#calcArea').text(b.calculated_area_km2 ? parseFloat(b.calculated_area_km2).toFixed(4) : '--');
    $('#areaPanel').show();

    // Reset overlap/validation
    $('#overlapWarning').hide();
    $('#validationInfo').hide();
    $('#saveSection').hide();

    // Zoom to center
    if (b.coordinates) {
        let parts = b.coordinates.split(',').map(Number);
        if (parts.length === 2) map.setView([parts[0], parts[1]], 14);
    }

    // Refresh existing boundaries (excluding selected)
    showAllBoundaries();

    // Clear draw layer and load existing boundary for editing
    drawnItems.clearLayers();
    if (b.boundary_geojson) {
        try {
            let geo = JSON.parse(b.boundary_geojson);
            L.geoJSON(geo, {
                style: { color: '#0d6efd', weight: 3, fillOpacity: 0.15 },
                onEachFeature: function(feature, layer){ drawnItems.addLayer(layer); }
            });
            // Show current area
            let layers = drawnItems.getLayers();
            if (layers.length > 0) {
                let latlngs = layers[0].getLatLngs()[0];
                if (latlngs) {
                    let areaKm2 = calcPolygonAreaKm2(latlngs);
                    pendingCalcArea = Math.round(areaKm2 * 10000) / 10000;
                    $('#calcArea').text(pendingCalcArea.toFixed(4));
                }
            }
        } catch(e){}
    }
}

// ── Handle new polygon drawn ──
map.on(L.Draw.Event.CREATED, function(e){
    if (!selectedBarangayId) {
        showToast('Select a barangay first from the side panel.', 'warning');
        return;
    }
    drawnItems.clearLayers();
    drawnItems.addLayer(e.layer);
    validateAndShowArea(e.layer);
});

// ── Handle polygon edited ──
map.on(L.Draw.Event.EDITED, function(e){
    let layers = drawnItems.getLayers();
    if (layers.length > 0) {
        validateAndShowArea(layers[0]);
    }
});

// ── Handle polygon deleted ──
map.on(L.Draw.Event.DELETED, function(e){
    if (selectedBarangayId) {
        if (!confirm('Remove the boundary for this barangay?')) {
            loadBarangays();
            return;
        }
        $.post('barangay_boundary.php?ajax=save_boundary', {
            barangay_id: selectedBarangayId,
            geojson: ''
        }, function(res){
            if (res.ok) {
                loadBarangays();
                showToast('Boundary removed.', 'warning');
                $('#calcArea').text('--');
                $('#saveSection').hide();
                $('#validationInfo').hide();
                $('#overlapWarning').hide();
            }
        }, 'json');
    }
});

// ── Save with confirmation ──
function confirmSave(){
    if (!selectedBarangayId) {
        showToast('Select a barangay first.', 'warning');
        return;
    }
    if (hasOverlap) {
        showToast('Cannot save: boundary overlaps with existing barangays. Please redraw.', 'danger');
        return;
    }

    let layers = drawnItems.getLayers();
    if (layers.length === 0) {
        showToast('Draw a polygon first.', 'warning');
        return;
    }

    // Prepare pending data
    let layer = layers[0];
    let latlngs = layer.getLatLngs()[0];
    if (!latlngs || latlngs.length < 3) {
        showToast('Polygon must have at least 3 points.', 'danger');
        return;
    }

    pendingGeoJSON = JSON.stringify(layer.toGeoJSON().geometry);
    pendingCalcArea = Math.round(calcPolygonAreaKm2(latlngs) * 10000) / 10000;

    let b = barangayData.find(x => x.id === selectedBarangayId);
    $('#confirmName').text(b ? b.name : 'Selected Barangay');
    $('#confirmArea').text(pendingCalcArea.toFixed(4));
    confirmModal.show();
}

function doSave(){
    confirmModal.hide();

    if (!selectedBarangayId || !pendingGeoJSON) {
        showToast('No boundary data to save.', 'danger');
        return;
    }

    $.post('barangay_boundary.php?ajax=save_boundary', {
        barangay_id: selectedBarangayId,
        geojson: pendingGeoJSON,
        calculated_area_km2: pendingCalcArea
    }, function(res){
        if (res.ok) {
            loadBarangays();
            showToast(res.msg, 'success');
            $('#saveSection').hide();
            showValidation('Boundary saved successfully.', 'success');
        } else {
            showToast(res.msg, 'danger');
            // If server-side overlap detected
            if (res.msg && res.msg.includes('overlaps')) {
                hasOverlap = true;
                $('#overlapDetails').text(res.msg);
                $('#overlapWarning').slideDown();
                $('#saveBoundaryBtn').prop('disabled', true).removeClass('btn-primary').addClass('btn-secondary');
            }
        }
    }, 'json');
}

function showToast(msg, type){
    let id = 'toast' + Date.now();
    $('body').append(`<div id="${id}" class="position-fixed bottom-0 end-0 p-3" style="z-index:9999">
        <div class="toast show align-items-center text-bg-${type} border-0" role="alert">
            <div class="d-flex"><div class="toast-body">${msg}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>
        </div></div>`);
    setTimeout(() => $('#'+id).remove(), 4000);
}

$(document).ready(loadBarangays);
</script>
</body>
</html>
