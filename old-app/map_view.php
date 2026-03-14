<?php
// map_view.php — Complete polygon & GPS-based map
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

// ── AJAX data endpoints ──────────────────────────────────
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    if ($_GET['ajax'] === 'barangays') {
        $rows = $pdo->query("
            SELECT b.*,
                   COUNT(DISTINCT hz.id) AS hazard_count,
                   COALESCE(SUM(hz.affected_population), 0) AS total_affected
            FROM barangays b
            LEFT JOIN hazard_zones hz ON b.id = hz.barangay_id
            GROUP BY b.id
            ORDER BY b.name
        ")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows);
        exit;
    }

    if ($_GET['ajax'] === 'hazard_zones') {
        $rows = $pdo->query("
            SELECT hz.*, ht.name AS hazard_name, ht.color, ht.icon,
                   b.name AS barangay_name, b.boundary_geojson
            FROM hazard_zones hz
            JOIN hazard_types ht ON hz.hazard_type_id = ht.id
            JOIN barangays b ON hz.barangay_id = b.id
        ")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows);
        exit;
    }

    if ($_GET['ajax'] === 'hazard_types') {
        echo json_encode($pdo->query("SELECT * FROM hazard_types")->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($_GET['ajax'] === 'households') {
        $rows = $pdo->query("
            SELECT h.id, h.household_head, h.latitude, h.longitude, h.family_members,
                   h.pwd_count, h.senior_count, h.infant_count, h.pregnant_count,
                   h.ip_non_ip, h.sitio_purok_zone, h.zone,
                   b.name AS barangay_name
            FROM households h
            JOIN barangays b ON h.barangay_id = b.id
            WHERE h.latitude IS NOT NULL AND h.longitude IS NOT NULL
              AND h.latitude != 0 AND h.longitude != 0
              AND h.latitude BETWEEN 12.50 AND 13.20
              AND h.longitude BETWEEN 120.50 AND 121.20
        ")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows);
        exit;
    }

    if ($_GET['ajax'] === 'incidents') {
        $rows = $pdo->query("
            SELECT ir.*, ht.name AS hazard_name, ht.color AS hazard_color,
                   (SELECT SUM(aa.affected_population) FROM affected_areas aa WHERE aa.incident_id = ir.id) AS total_affected,
                   (SELECT GROUP_CONCAT(b.name SEPARATOR ', ') FROM affected_areas aa2 JOIN barangays b ON aa2.barangay_id = b.id WHERE aa2.incident_id = ir.id) AS affected_barangays
            FROM incident_reports ir
            LEFT JOIN hazard_types ht ON ir.hazard_type_id = ht.id
            WHERE ir.status IN ('ongoing','monitoring')
        ")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows);
        exit;
    }

    if ($_GET['ajax'] === 'evacuation_centers') {
        $rows = $pdo->query("
            SELECT ec.*, b.name AS barangay_name
            FROM evacuation_centers ec
            JOIN barangays b ON ec.barangay_id = b.id
        ")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows);
        exit;
    }

    exit;
}

// Server-side data for stats sidebar
$barangayCount = $pdo->query("SELECT COUNT(*) FROM barangays")->fetchColumn();
$hazardZoneCount = $pdo->query("SELECT COUNT(*) FROM hazard_zones")->fetchColumn();
$totalAffected = $pdo->query("SELECT COALESCE(SUM(affected_population),0) FROM hazard_zones")->fetchColumn();
$hazardTypes = $pdo->query("SELECT * FROM hazard_types")->fetchAll(PDO::FETCH_ASSOC);

// Risk level colors
$riskColors = [
    'High Susceptible' => '#e74c3c',
    'Moderate Susceptible' => '#e67e22',
    'Low Susceptible' => '#f1c40f',
    'Prone' => '#9b59b6',
    'Generally Susceptible' => '#3498db',
    'PEIS VIII - Very destructive to devastating ground shaking' => '#922b21',
    'PEIS VII - Destructive ground shaking' => '#cb4335',
    'General Inundation' => '#1abc9c',
    'Not Susceptible' => '#27ae60',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interactive Map - Sablayan Risk Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body { margin: 0; overflow: hidden; height: 100vh; }
        .map-wrapper { display: flex; height: 100vh; }
        .map-sidebar {
            width: 280px; min-width: 280px; background: #fff; overflow-y: auto;
            box-shadow: 2px 0 8px rgba(0,0,0,.1); z-index: 500;
            display: flex; flex-direction: column;
        }
        .map-sidebar .header { background: #212529; color: #fff; padding: .8rem 1rem; }
        #map { flex: 1; height: 100vh; }
        .stat-item { padding: .5rem 1rem; border-bottom: 1px solid #eee; }
        .stat-item .val { font-weight: 700; font-size: 1.1rem; }
        .stat-item .label { font-size: .75rem; color: #6c757d; }
        .legend-section { padding: .5rem 1rem; }
        .legend-section h6 { font-size: .8rem; text-transform: uppercase; color: #6c757d; margin-bottom: .5rem; }
        .legend-item { display: flex; align-items: center; margin-bottom: 4px; font-size: .8rem; }
        .legend-color { width: 16px; height: 16px; border-radius: 3px; margin-right: 8px; flex-shrink: 0; }
        .legend-icon { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 8px; color: #fff; font-size: .6rem; flex-shrink: 0; }
        .hazard-legend { display: flex; flex-wrap: wrap; gap: 6px; }
        .hazard-legend-item { display: flex; align-items: center; font-size: .75rem; padding: 3px 8px; background: #f8f9fa; border-radius: 4px; }
        .hazard-icon-display { width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: .55rem; margin-right: 6px; }
        @media(max-width:768px){ .map-sidebar { width: 100%; height: auto; max-height: 35vh; } .map-wrapper { flex-direction: column; } }
    </style>
</head>
<body>
<div class="map-wrapper">
    <!-- Sidebar -->
    <div class="map-sidebar">
        <div class="header">
            <div class="d-flex align-items-center justify-content-between">
                <span class="fw-bold"><i class="fas fa-map me-2"></i>Risk Assessment Map</span>
                <a href="dashboard.php" class="text-white"><i class="fas fa-arrow-left"></i></a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stat-item">
            <div class="d-flex justify-content-between">
                <div><div class="label">Barangays</div><div class="val"><?= $barangayCount ?></div></div>
                <div><div class="label">Hazard Zones</div><div class="val"><?= $hazardZoneCount ?></div></div>
                <div><div class="label">Total Affected</div><div class="val"><?= number_format($totalAffected) ?></div></div>
            </div>
        </div>

        <!-- Risk level legend -->
        <div class="legend-section">
            <h6><i class="fas fa-layer-group me-1"></i> Risk Levels</h6>
            <?php foreach ($riskColors as $level => $color): ?>
            <div class="legend-item">
                <div class="legend-color" style="background-color: <?= $color ?>;"></div>
                <span><?= htmlspecialchars($level) ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Hazard types legend -->
        <div class="legend-section">
            <h6><i class="fas fa-exclamation-triangle me-1"></i> Hazard Types</h6>
            <?php foreach ($hazardTypes as $type): ?>
            <div class="legend-item">
                <div class="legend-icon" style="background-color: <?= $type['color'] ?>;">
                    <i class="fas <?= $type['icon'] ?>"></i>
                </div>
                <span><?= htmlspecialchars($type['name']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Household legend -->
        <div class="legend-section">
            <h6><i class="fas fa-home me-1"></i> Households</h6>
            <div class="legend-item"><div class="legend-color" style="background-color: #dc3545; border-radius:50%;"></div><span>Vulnerable (PWD/Senior/Infant/Pregnant)</span></div>
            <div class="legend-item"><div class="legend-color" style="background-color: #3498db; border-radius:50%;"></div><span>Regular Household</span></div>
        </div>

        <!-- Data sources -->
        <div class="legend-section">
            <h6><i class="fas fa-info-circle me-1"></i> Data Sources</h6>
            <div class="legend-item"><div class="legend-color" style="background-color: #3498db;"></div><span>Population (Computed)</span></div>
            <div class="legend-item"><div class="legend-color" style="background-color: #2980b9;"></div><span>Barangay Boundaries</span></div>
            <small class="text-muted" style="font-size:.7rem">All population data auto-computed from household records.</small>
        </div>

        <!-- Evacuation centers legend -->
        <div class="legend-section">
            <h6><i class="fas fa-building-shield me-1"></i> Evacuation Centers</h6>
            <div class="legend-item"><div class="legend-icon" style="background-color:#198754;font-size:.5rem"><i class="fas fa-building-shield"></i></div><span>Operational</span></div>
            <div class="legend-item"><div class="legend-icon" style="background-color:#ffc107;font-size:.5rem"><i class="fas fa-building-shield"></i></div><span>Maintenance</span></div>
            <div class="legend-item"><div class="legend-icon" style="background-color:#dc3545;font-size:.5rem"><i class="fas fa-building-shield"></i></div><span>Closed</span></div>
        </div>

        <!-- Quick actions -->
        <div class="legend-section mt-auto pb-3">
            <a href="hazard_data.php" class="btn btn-sm btn-outline-primary w-100 mb-1"><i class="fas fa-plus me-1"></i> Add Hazard Zone</a>
            <a href="incident_reports.php" class="btn btn-sm btn-outline-danger w-100 mb-1"><i class="fas fa-file-circle-exclamation me-1"></i> Incident Reports</a>
            <button class="btn btn-sm btn-outline-secondary w-100" onclick="location.reload()"><i class="fas fa-sync-alt me-1"></i> Refresh Map</button>
        </div>
    </div>

    <!-- Map -->
    <div id="map"></div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
<script>
// Risk level colors
const riskColors = <?= json_encode($riskColors) ?>;

// Tile layers
const osm = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom:19, attribution:'&copy; OpenStreetMap'});
const satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {maxZoom:19, attribution:'Esri Satellite'});
const terrain = L.tileLayer('https://tile.opentopomap.org/{z}/{x}/{y}.png', {maxZoom:17, attribution:'OpenTopoMap'});
const hybridSat = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {maxZoom:19});
const hybridLabels = L.tileLayer('https://stamen-tiles.a.ssl.fastly.net/toner-labels/{z}/{x}/{y}.png', {maxZoom:19, opacity:0.7});
const hybrid = L.layerGroup([hybridSat, hybridLabels]);

// Map init
const map = L.map('map', { layers: [osm], center: [12.8333, 120.7667], zoom: 11, zoomControl: true });

// Tile layer control
L.control.layers({
    'OpenStreetMap': osm,
    'Satellite': satellite,
    'Terrain': terrain,
    'Hybrid': hybrid
}, null, {position: 'topright'}).addTo(map);

// Overlay layer groups
const boundaryLayer = L.layerGroup().addTo(map);       // Layer 1 — always on
const hazardLayer = L.layerGroup().addTo(map);          // Layer 2 — on by default
const householdLayer = L.layerGroup();                  // Layer 3 — off by default
const heatmapLayer = L.layerGroup();                    // Layer 4 — off by default
const incidentLayer = L.layerGroup().addTo(map);        // Layer 5 — on by default
const evacLayer = L.layerGroup().addTo(map);            // Layer 6 — on by default

// Overlay control (without barangay boundaries since it's always on)
const overlayControl = L.control.layers(null, {
    '<i class="fas fa-exclamation-triangle"></i> Hazard Zones': hazardLayer,
    '<i class="fas fa-home"></i> Household Locations': householdLayer,
    '<i class="fas fa-fire"></i> Population Heatmap': heatmapLayer,
    '<i class="fas fa-bolt"></i> Active Incidents': incidentLayer,
    '<i class="fas fa-building-shield"></i> Evacuation Centers': evacLayer
}, {position: 'topright', collapsed: false}).addTo(map);

// ── Load all data ──────────────────────────────────────
$(document).ready(function(){
    loadBoundaries();
    loadHazardZones();
    loadHouseholds();
    loadIncidents();
    loadEvacuationCenters();
});

// Layer 1 — Barangay Boundaries (always visible)
function loadBoundaries(){
    $.getJSON('map_view.php?ajax=barangays', function(barangays){
        barangays.forEach(b => {
            if (!b.boundary_geojson) return;
            try {
                let geo = JSON.parse(b.boundary_geojson);
                let layer = L.geoJSON(geo, {
                    style: { color: '#495057', weight: 2, fillColor: '#dee2e6', fillOpacity: 0.08 }
                });
                layer.bindTooltip(b.name, {permanent: true, direction: 'center', className: 'bg-transparent border-0 text-dark fw-bold shadow-none', offset: [0, 0]});
                layer.bindPopup(`
                    <div style="min-width:200px">
                        <h6 class="mb-1">${esc(b.name)}</h6>
                        <table class="table table-sm table-borderless mb-0" style="font-size:.8rem">
                            <tr><td>Population</td><td class="fw-bold">${parseInt(b.population||0).toLocaleString()}</td></tr>
                            <tr><td>Households</td><td class="fw-bold">${b.household_count||0}</td></tr>
                            <tr><td>PWD</td><td>${b.pwd_count||0}</td></tr>
                            <tr><td>Seniors</td><td>${b.senior_count||0}</td></tr>
                            <tr><td>IP</td><td>${b.ip_count||0}</td></tr>
                            <tr><td>Hazard Zones</td><td>${b.hazard_count||0}</td></tr>
                        </table>
                    </div>
                `);
                boundaryLayer.addLayer(layer);
            } catch(e){ console.error('Boundary parse error for ' + b.name, e); }
        });
    });
}

// Layer 2 — Hazard Zones (colored polygons on barangay boundaries)
function loadHazardZones(){
    $.getJSON('map_view.php?ajax=hazard_zones', function(zones){
        zones.forEach(hz => {
            let color = riskColors[hz.risk_level] || '#6c757d';

            // If barangay has boundary, render as colored overlay
            if (hz.boundary_geojson) {
                try {
                    let geo = JSON.parse(hz.boundary_geojson);
                    let layer = L.geoJSON(geo, {
                        style: { color: color, weight: 2, fillColor: color, fillOpacity: 0.25 }
                    });
                    layer.bindPopup(`
                        <div style="min-width:200px">
                            <h6 style="color:${hz.color}"><i class="fas ${hz.icon} me-1"></i>${esc(hz.hazard_name)}</h6>
                            <table class="table table-sm table-borderless mb-0" style="font-size:.8rem">
                                <tr><td>Barangay</td><td class="fw-bold">${esc(hz.barangay_name)}</td></tr>
                                <tr><td>Risk Level</td><td><span style="color:${color};font-weight:600">${esc(hz.risk_level)}</span></td></tr>
                                <tr><td>Affected Pop.</td><td class="fw-bold">${parseInt(hz.affected_population||0).toLocaleString()}</td></tr>
                                <tr><td>Area</td><td>${hz.area_km2||'N/A'} km&sup2;</td></tr>
                            </table>
                            ${hz.description ? '<p class="mt-1 mb-0" style="font-size:.75rem">'+esc(hz.description)+'</p>' : ''}
                        </div>
                    `);
                    hazardLayer.addLayer(layer);
                } catch(e){}
            }

            // Add hazard type icon marker at barangay coordinates
            if (hz.coordinates || hz.boundary_geojson) {
                let lat, lng;
                if (hz.coordinates) {
                    let parts = hz.coordinates.split(',').map(Number);
                    if (parts.length === 2) { lat = parts[0]; lng = parts[1]; }
                }
                if (lat && lng) {
                    let iconHtml = `<div style="background:${hz.color};width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.3)"><i class="fas ${hz.icon}"></i></div>`;
                    let marker = L.marker([lat, lng], {
                        icon: L.divIcon({ html: iconHtml, className: '', iconSize: [28, 28], iconAnchor: [14, 14] })
                    });
                    marker.bindPopup(`<strong>${esc(hz.hazard_name)}</strong><br>${esc(hz.barangay_name)}<br>Risk: ${esc(hz.risk_level)}`);
                    hazardLayer.addLayer(marker);
                }
            }
        });
    });
}

// Layer 3 — Household Locations & Layer 4 — Heatmap
function loadHouseholds(){
    $.getJSON('map_view.php?ajax=households', function(households){
        let heatData = [];
        households.forEach(h => {
            let lat = parseFloat(h.latitude), lng = parseFloat(h.longitude);
            let hasVuln = parseInt(h.pwd_count)>0 || parseInt(h.senior_count)>0 || parseInt(h.infant_count)>0 || parseInt(h.pregnant_count)>0;
            let dotColor = hasVuln ? '#dc3545' : '#3498db';

            // Household dot
            let marker = L.circleMarker([lat, lng], {
                radius: 4, fillColor: dotColor, color: '#fff', weight: 1, fillOpacity: 0.8
            });
            let vulnFlags = [];
            if (parseInt(h.pwd_count)>0) vulnFlags.push('PWD');
            if (parseInt(h.senior_count)>0) vulnFlags.push('Senior');
            if (parseInt(h.infant_count)>0) vulnFlags.push('Infant');
            if (parseInt(h.pregnant_count)>0) vulnFlags.push('Pregnant');
            if (h.ip_non_ip==='IP') vulnFlags.push('IP');

            marker.bindPopup(`
                <div style="min-width:180px">
                    <h6 class="mb-1">${esc(h.household_head)}</h6>
                    <div style="font-size:.8rem">
                        <div>Members: <strong>${h.family_members}</strong></div>
                        <div>Zone: ${esc(h.sitio_purok_zone || h.zone || '-')}</div>
                        <div>Barangay: ${esc(h.barangay_name)}</div>
                        ${vulnFlags.length ? '<div class="mt-1">Vulnerability: <strong>'+vulnFlags.join(', ')+'</strong></div>' : ''}
                    </div>
                </div>
            `);
            householdLayer.addLayer(marker);

            // Heatmap data
            heatData.push([lat, lng, parseInt(h.family_members) || 1]);
        });

        // Heatmap
        if (heatData.length > 0) {
            let heat = L.heatLayer(heatData, { radius: 25, blur: 15, maxZoom: 16, max: 20 });
            heatmapLayer.addLayer(heat);
        }
    });
}

// Layer 5 — Active Incidents
function loadIncidents(){
    $.getJSON('map_view.php?ajax=incidents', function(incidents){
        incidents.forEach(inc => {
            if (!inc.affected_polygon) return;
            try {
                let geo = JSON.parse(inc.affected_polygon);
                let color = inc.hazard_color || '#dc3545';
                let layer = L.geoJSON(geo, {
                    style: { color: color, weight: 2, dashArray: '8,4', fillColor: color, fillOpacity: 0.15 }
                });
                layer.bindPopup(`
                    <div style="min-width:220px">
                        <h6 style="color:${color}">${esc(inc.title)}</h6>
                        <table class="table table-sm table-borderless mb-0" style="font-size:.8rem">
                            <tr><td>Type</td><td>${esc(inc.hazard_name||'')}</td></tr>
                            <tr><td>Date</td><td>${inc.incident_date}</td></tr>
                            <tr><td>Status</td><td><strong>${inc.status}</strong></td></tr>
                            <tr><td>Affected Pop.</td><td class="fw-bold">${parseInt(inc.total_affected||0).toLocaleString()}</td></tr>
                            <tr><td>Barangays</td><td>${esc(inc.affected_barangays||'-')}</td></tr>
                        </table>
                    </div>
                `);
                incidentLayer.addLayer(layer);
            } catch(e){}
        });
    });
}

// Layer 6 — Evacuation Centers
function loadEvacuationCenters(){
    $.getJSON('map_view.php?ajax=evacuation_centers', function(centers){
        centers.forEach(ec => {
            if (!ec.latitude || !ec.longitude) return;
            let lat = parseFloat(ec.latitude), lng = parseFloat(ec.longitude);
            if (isNaN(lat) || isNaN(lng)) return;

            let statusColors = {operational:'#198754', maintenance:'#ffc107', closed:'#dc3545'};
            let color = statusColors[ec.status] || '#6c757d';

            let iconHtml = `<div style="background:${color};width:30px;height:30px;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:14px;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.3)"><i class="fas fa-building-shield"></i></div>`;
            let marker = L.marker([lat, lng], {
                icon: L.divIcon({ html: iconHtml, className: '', iconSize: [30, 30], iconAnchor: [15, 15] })
            });

            let occupancyPct = ec.capacity > 0 ? Math.round((ec.current_occupancy / ec.capacity) * 100) : 0;
            let barColor = occupancyPct >= 80 ? 'danger' : occupancyPct >= 50 ? 'warning' : 'success';

            marker.bindPopup(`
                <div style="min-width:220px">
                    <h6><i class="fas fa-building-shield me-1"></i>${esc(ec.name)}</h6>
                    <table class="table table-sm table-borderless mb-0" style="font-size:.8rem">
                        <tr><td>Status</td><td><span style="color:${color};font-weight:600">${ec.status}</span></td></tr>
                        <tr><td>Capacity</td><td>${ec.current_occupancy} / ${ec.capacity}</td></tr>
                        <tr><td colspan="2">
                            <div class="progress" style="height:6px"><div class="progress-bar bg-${barColor}" style="width:${occupancyPct}%"></div></div>
                        </td></tr>
                        ${ec.facilities ? '<tr><td>Facilities</td><td>'+esc(ec.facilities)+'</td></tr>' : ''}
                        ${ec.contact_person ? '<tr><td>Contact</td><td>'+esc(ec.contact_person)+'</td></tr>' : ''}
                        ${ec.contact_number ? '<tr><td>Phone</td><td>'+esc(ec.contact_number)+'</td></tr>' : ''}
                    </table>
                </div>
            `);
            evacLayer.addLayer(marker);
        });
    });
}

function esc(s){ if(!s) return ''; let d=document.createElement('div'); d.textContent=s; return d.innerHTML; }
</script>
</body>
</html>
