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

    if (!$id || !$geojson) {
        echo json_encode(['ok' => false, 'msg' => 'Missing data.']);
        exit;
    }

    $pdo->prepare("UPDATE barangays SET boundary_geojson = ? WHERE id = ?")->execute([$geojson, $id]);
    $pdo->prepare("INSERT INTO barangay_boundary_logs (barangay_id, action, drawn_by) VALUES (?, ?, ?)")
        ->execute([$id, 'updated', $_SESSION['user_id']]);

    echo json_encode(['ok' => true, 'msg' => 'Boundary saved.']);
    exit;
}

// ── AJAX: list barangays with boundary status ──
if (isset($_GET['ajax']) && $_GET['ajax'] === 'list') {
    header('Content-Type: application/json');
    $rows = $pdo->query("
        SELECT id, name, coordinates, boundary_geojson,
               CASE WHEN boundary_geojson IS NOT NULL AND boundary_geojson != '' THEN 1 ELSE 0 END AS has_boundary
        FROM barangays ORDER BY name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows);
    exit;
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
            position: fixed; top: 0; left: 0; width: 320px; height: 100vh;
            background: #fff; z-index: 1000; overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,.15);
        }
        .side-panel .header { background: #212529; color: #fff; padding: 1rem; }
        .brgy-item { padding: .6rem 1rem; border-bottom: 1px solid #eee; cursor: pointer; transition: background .15s; }
        .brgy-item:hover { background: #f0f2f5; }
        .brgy-item.active { background: #e3f2fd; border-left: 3px solid #0d6efd; }
        .status-icon { font-size: .85rem; }
        .map-container { margin-left: 320px; }
        @media(max-width:768px){ .side-panel { width: 100%; height: auto; max-height: 40vh; position: relative; } .map-container { margin-left: 0; } }
        .progress-badge { font-size: .8rem; padding: .3rem .6rem; }
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
    </div>
    <div id="brgyList"></div>
</div>

<div class="map-container">
    <div id="map"></div>
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

const map = L.map('map', { layers: [osm], center: [12.84, 120.87], zoom: 11 });
L.control.layers({'OpenStreetMap': osm, 'Satellite': satellite, 'Terrain': terrain, 'Hybrid': hybrid}).addTo(map);

// Drawing layer
const drawnItems = new L.FeatureGroup();
map.addLayer(drawnItems);

const drawControl = new L.Control.Draw({
    position: 'topright',
    draw: {
        polygon: { allowIntersection: false, shapeOptions: { color: '#0d6efd', weight: 2 } },
        polyline: false, rectangle: false, circle: false, circlemarker: false, marker: false
    },
    edit: { featureGroup: drawnItems }
});
map.addControl(drawControl);

// All boundary layers (non-editing)
const allBoundariesLayer = L.layerGroup().addTo(map);

let selectedBarangayId = null;
let barangayData = [];

// URL param pre-select
const urlId = new URLSearchParams(location.search).get('id');

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
            html += `<div class="brgy-item" data-id="${b.id}" onclick="selectBarangay(${b.id})">
                ${icon} <span class="ms-2">${b.name}</span>
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
        try {
            let geo = JSON.parse(b.boundary_geojson);
            let layer = L.geoJSON(geo, {
                style: { color: '#6c757d', weight: 1, fillOpacity: 0.05 }
            });
            layer.bindTooltip(b.name, {permanent: true, direction: 'center', className: 'bg-transparent border-0 text-dark fw-bold shadow-none'});
            allBoundariesLayer.addLayer(layer);
        } catch(e){}
    });
}

function selectBarangay(id){
    selectedBarangayId = id;
    $('.brgy-item').removeClass('active');
    $(`.brgy-item[data-id="${id}"]`).addClass('active');

    let b = barangayData.find(x => x.id === id);
    if (!b) return;

    // Zoom to center
    if (b.coordinates) {
        let parts = b.coordinates.split(',').map(Number);
        if (parts.length === 2) map.setView([parts[0], parts[1]], 14);
    }

    // Clear draw layer and load existing boundary
    drawnItems.clearLayers();
    if (b.boundary_geojson) {
        try {
            let geo = JSON.parse(b.boundary_geojson);
            L.geoJSON(geo, {
                onEachFeature: function(feature, layer){ drawnItems.addLayer(layer); }
            });
        } catch(e){}
    }
}

// Handle new polygon drawn
map.on(L.Draw.Event.CREATED, function(e){
    drawnItems.clearLayers();
    drawnItems.addLayer(e.layer);
    saveBoundary();
});

// Handle polygon edited
map.on(L.Draw.Event.EDITED, function(e){ saveBoundary(); });

// Handle polygon deleted
map.on(L.Draw.Event.DELETED, function(e){
    if (selectedBarangayId) {
        $.post('barangay_boundary.php?ajax=save_boundary', {
            barangay_id: selectedBarangayId,
            geojson: ''
        }, function(res){
            loadBarangays();
            showToast('Boundary removed.', 'warning');
        }, 'json');
    }
});

function saveBoundary(){
    if (!selectedBarangayId) {
        showToast('Select a barangay first.', 'warning');
        return;
    }
    let layers = drawnItems.getLayers();
    if (layers.length === 0) return;

    let geojson = JSON.stringify(layers[0].toGeoJSON().geometry);
    $.post('barangay_boundary.php?ajax=save_boundary', {
        barangay_id: selectedBarangayId,
        geojson: geojson
    }, function(res){
        if (res.ok) {
            loadBarangays();
            showToast('Boundary saved successfully!', 'success');
        } else {
            showToast(res.msg, 'danger');
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
