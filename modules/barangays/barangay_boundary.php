<?php
// modules/barangays/barangay_boundary.php — Admin only
// Draw and save barangay polygon boundaries using Leaflet Draw
session_start();
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL . 'login.php'); exit;
}

header('Content-Type: text/html; charset=utf-8');

// AJAX: save boundary
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_boundary') {
    header('Content-Type: application/json');
    $barangay_id = (int)$_POST['barangay_id'];
    $geojson = $_POST['geojson'] ?? '';
    if (!json_decode($geojson)) { echo json_encode(['success'=>false,'message'=>'Invalid GeoJSON']); exit; }
    $pdo->prepare("UPDATE barangays SET boundary_geojson = ? WHERE id = ?")->execute([$geojson, $barangay_id]);
    echo json_encode(['success'=>true,'message'=>'Boundary saved']);
    exit;
}

// AJAX: clear boundary
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_boundary') {
    header('Content-Type: application/json');
    $barangay_id = (int)$_POST['barangay_id'];
    $pdo->prepare("UPDATE barangays SET boundary_geojson = NULL WHERE id = ?")->execute([$barangay_id]);
    echo json_encode(['success'=>true]);
    exit;
}

// Page data
$barangays = $pdo->query("
    SELECT id, name, coordinates, boundary_geojson,
           CASE WHEN boundary_geojson IS NOT NULL THEN 1 ELSE 0 END as has_boundary
    FROM barangays ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

$total  = count($barangays);
$mapped = array_sum(array_column($barangays, 'has_boundary'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Barangay Boundaries — DRMS</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css">
<style>
  body{margin:0;font-family:sans-serif;overflow:hidden;}
  .page-wrapper{display:flex;height:100vh;}
  .side-panel{width:300px;min-width:300px;background:#1a1f2e;color:#fff;display:flex;flex-direction:column;overflow:hidden;}
  .side-header{padding:16px;border-bottom:1px solid rgba(255,255,255,.15);background:#12182a;}
  .side-header h5{margin:0;font-size:1rem;}
  .progress-info{font-size:.8rem;opacity:.8;margin-top:4px;}
  .brgy-list{flex:1;overflow-y:auto;padding:4px 0;}
  .brgy-item{display:flex;align-items:center;padding:9px 14px;cursor:pointer;border-bottom:1px solid rgba(255,255,255,.06);transition:background .15s;}
  .brgy-item:hover{background:rgba(255,255,255,.08);}
  .brgy-item.active{background:rgba(13,110,253,.35);}
  .brgy-item .ico{margin-right:10px;font-size:.9rem;}
  .status-ok{color:#28a745;} .status-bad{color:#ffc107;}
  .map-area{flex:1;position:relative;}
  #map{height:100%;}
  .map-toolbar{position:absolute;top:10px;left:10px;z-index:1000;display:flex;gap:6px;}
  .tile-switcher{position:absolute;top:10px;right:50px;z-index:1000;display:flex;gap:4px;}
  .tile-btn{background:#fff;border:1px solid #ccc;border-radius:4px;padding:4px 8px;font-size:.75rem;cursor:pointer;}
  .tile-btn.active{background:#0d6efd;color:#fff;border-color:#0d6efd;}
  #statusBar{position:absolute;bottom:10px;left:10px;z-index:1000;background:rgba(0,0,0,.7);color:#fff;padding:6px 12px;border-radius:4px;font-size:.85rem;}
  #toastBox{position:fixed;bottom:20px;right:20px;z-index:9999;}
  .back-nav{padding:10px 14px;border-top:1px solid rgba(255,255,255,.1);}
</style>
</head>
<body>
<div class="page-wrapper">

  <!-- Side Panel -->
  <div class="side-panel">
    <div class="side-header">
      <h5><i class="fas fa-draw-polygon me-2"></i>Barangay Boundaries</h5>
      <div class="progress-info">
        <span class="text-success" id="mappedCount"><?= $mapped ?></span> of <?= $total ?> barangays drawn
        <div class="progress mt-1" style="height:4px;">
          <div class="progress-bar bg-success" id="progressBar" style="width:<?= $total ? round($mapped/$total*100) : 0 ?>%"></div>
        </div>
      </div>
    </div>

    <div class="brgy-list" id="brgyList">
      <?php foreach ($barangays as $b): ?>
      <div class="brgy-item"
           data-id="<?= $b['id'] ?>"
           data-name="<?= htmlspecialchars($b['name']) ?>"
           data-coords="<?= htmlspecialchars($b['coordinates'] ?? '') ?>"
           data-geojson="<?= htmlspecialchars($b['boundary_geojson'] ?? '') ?>">
        <span class="ico <?= $b['has_boundary'] ? 'status-ok' : 'status-bad' ?>">
          <i class="fas <?= $b['has_boundary'] ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
        </span>
        <span style="font-size:.88rem;"><?= htmlspecialchars($b['name']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="back-nav d-grid gap-1">
      <a href="<?= BASE_URL ?>modules/barangays/barangay_management.php" class="btn btn-sm btn-outline-light">
        <i class="fas fa-city me-1"></i> Barangay Management
      </a>
      <a href="<?= BASE_URL ?>dashboard.php" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Dashboard
      </a>
    </div>
  </div>

  <!-- Map Area -->
  <div class="map-area">
    <div class="map-toolbar">
      <button class="btn btn-sm btn-primary" id="btnSave" disabled>
        <i class="fas fa-save me-1"></i> Save Boundary
      </button>
      <button class="btn btn-sm btn-danger" id="btnClear" disabled>
        <i class="fas fa-trash me-1"></i> Clear
      </button>
    </div>

    <!-- Tile Switcher -->
    <div class="tile-switcher">
      <button class="tile-btn active" data-tile="street">Street</button>
      <button class="tile-btn" data-tile="satellite">Satellite</button>
      <button class="tile-btn" data-tile="terrain">Terrain</button>
      <button class="tile-btn" data-tile="hybrid">Hybrid</button>
    </div>

    <div id="map"></div>
    <div id="statusBar">Click a barangay in the list to start drawing.</div>
  </div>
</div>

<div id="toastBox"></div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>
<script>
const SABLAYAN = [12.8333, 120.7667];

// Tile layers
const tiles = {
  street:    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution:'© OSM', maxZoom:19}),
  satellite: L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {attribution:'© Esri', maxZoom:19}),
  terrain:   L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {attribution:'© OpenTopoMap', maxZoom:17}),
  hybrid:    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}', {attribution:'© Esri', maxZoom:19})
};

const map = L.map('map').setView(SABLAYAN, 12);
tiles.street.addTo(map);

// Tile switcher
document.querySelectorAll('.tile-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.tile-btn').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    Object.values(tiles).forEach(t => map.removeLayer(t));
    tiles[this.dataset.tile].addTo(map);
  });
});

// Draw control
const drawnItems = new L.FeatureGroup();
map.addLayer(drawnItems);

const drawControl = new L.Control.Draw({
  draw: {
    polygon: { allowIntersection: false, shapeOptions: { color:'#3388ff', weight:2, fillOpacity:0.2 } },
    polyline:false, rectangle:false, circle:false, marker:false, circlemarker:false
  },
  edit: { featureGroup: drawnItems }
});
map.addControl(drawControl);

map.on(L.Draw.Event.CREATED, e => {
  drawnItems.clearLayers();
  drawnItems.addLayer(e.layer);
  if (currentBrgy) document.getElementById('btnSave').disabled = false;
});
map.on(L.Draw.Event.EDITED, () => {
  if (currentBrgy) document.getElementById('btnSave').disabled = false;
});
map.on(L.Draw.Event.DELETED, () => {
  document.getElementById('btnSave').disabled = true;
});

let currentBrgy = null;

// Barangay list click
document.querySelectorAll('.brgy-item').forEach(item => {
  item.addEventListener('click', function() {
    document.querySelectorAll('.brgy-item').forEach(i => i.classList.remove('active'));
    this.classList.add('active');
    currentBrgy = { id: this.dataset.id, name: this.dataset.name, coords: this.dataset.coords, geojson: this.dataset.geojson };
    document.getElementById('statusBar').textContent = 'Drawing boundary for: ' + currentBrgy.name;
    document.getElementById('btnSave').disabled = false;
    document.getElementById('btnClear').disabled = !currentBrgy.geojson;
    drawnItems.clearLayers();

    if (currentBrgy.geojson && currentBrgy.geojson.trim()) {
      try {
        const layer = L.geoJSON(JSON.parse(currentBrgy.geojson), { style: { color:'#3388ff', weight:2, fillOpacity:0.2 } });
        layer.eachLayer(l => drawnItems.addLayer(l));
        map.fitBounds(drawnItems.getBounds(), { padding: [30,30] });
      } catch(e) { console.warn('Bad GeoJSON', e); }
    } else if (currentBrgy.coords) {
      const [lat, lng] = currentBrgy.coords.split(',').map(Number);
      if (!isNaN(lat) && !isNaN(lng)) map.setView([lat, lng], 14);
    }
  });
});

// Save
document.getElementById('btnSave').addEventListener('click', function() {
  if (!currentBrgy || drawnItems.getLayers().length === 0) {
    toast('Draw a polygon first.', 'warning'); return;
  }
  const geojsonStr = JSON.stringify(drawnItems.toGeoJSON());
  $.post('<?= BASE_URL ?>modules/barangays/barangay_boundary.php',
    { action:'save_boundary', barangay_id: currentBrgy.id, geojson: geojsonStr },
    function(res) {
      if (res.success) {
        updateItemStatus(currentBrgy.id, true, geojsonStr);
        currentBrgy.geojson = geojsonStr;
        document.getElementById('btnClear').disabled = false;
        toast('Boundary saved for ' + currentBrgy.name, 'success');
        recalcProgress();
      } else { toast(res.message, 'danger'); }
    }, 'json');
});

// Clear
document.getElementById('btnClear').addEventListener('click', function() {
  if (!currentBrgy || !confirm('Remove boundary for ' + currentBrgy.name + '?')) return;
  $.post('<?= BASE_URL ?>modules/barangays/barangay_boundary.php',
    { action:'clear_boundary', barangay_id: currentBrgy.id },
    function(res) {
      if (res.success) {
        drawnItems.clearLayers();
        updateItemStatus(currentBrgy.id, false, '');
        currentBrgy.geojson = '';
        document.getElementById('btnClear').disabled = true;
        document.getElementById('btnSave').disabled = true;
        toast('Boundary cleared', 'warning');
        recalcProgress();
      }
    }, 'json');
});

function updateItemStatus(id, hasBoundary, geojson) {
  const el = document.querySelector('.brgy-item[data-id="' + id + '"]');
  if (!el) return;
  el.dataset.geojson = geojson;
  const ico = el.querySelector('.ico');
  ico.className = 'ico ' + (hasBoundary ? 'status-ok' : 'status-bad');
  ico.querySelector('i').className = 'fas ' + (hasBoundary ? 'fa-check-circle' : 'fa-exclamation-circle');
}

function recalcProgress() {
  const total = document.querySelectorAll('.brgy-item').length;
  const mapped = document.querySelectorAll('.status-ok').length;
  document.getElementById('mappedCount').textContent = mapped;
  document.getElementById('progressBar').style.width = (total ? Math.round(mapped/total*100) : 0) + '%';
}

function toast(msg, type) {
  const id = 'toast_' + Date.now();
  const colors = { success:'#198754', danger:'#dc3545', warning:'#ffc107', info:'#0dcaf0' };
  const el = document.createElement('div');
  el.id = id;
  el.style.cssText = 'background:' + (colors[type]||colors.info) + ';color:#fff;padding:10px 16px;border-radius:6px;margin-top:8px;box-shadow:0 2px 8px rgba(0,0,0,.3);max-width:280px;';
  el.textContent = msg;
  document.getElementById('toastBox').appendChild(el);
  setTimeout(() => el.remove(), 3500);
}
</script>
</body>
</html>
