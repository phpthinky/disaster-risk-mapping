<?php
// incident_reports.php — Admin & Barangay Staff
session_start();
require_once 'config.php';
require_once 'sync_functions.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$role = $_SESSION['role'];
if (!in_array($role, ['admin', 'barangay_staff'])) { header('Location: dashboard.php'); exit; }

// ── AJAX handlers ──────────────────────────────────────
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    // List incidents
    if ($_GET['ajax'] === 'list') {
        $stmt = $pdo->query("
            SELECT ir.*, ht.name AS hazard_name, ht.color AS hazard_color, ht.icon AS hazard_icon,
                   u.username AS reported_by_name,
                   (SELECT SUM(aa.affected_population) FROM affected_areas aa WHERE aa.incident_id = ir.id) AS total_affected
            FROM incident_reports ir
            LEFT JOIN hazard_types ht ON ir.hazard_type_id = ht.id
            LEFT JOIN users u ON ir.reported_by = u.id
            ORDER BY ir.created_at DESC
        ");
        echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // Get single incident with affected areas
    if ($_GET['ajax'] === 'get' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("
            SELECT ir.*, ht.name AS hazard_name, ht.color AS hazard_color
            FROM incident_reports ir
            LEFT JOIN hazard_types ht ON ir.hazard_type_id = ht.id
            WHERE ir.id = ?
        ");
        $stmt->execute([$_GET['id']]);
        $incident = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($incident) {
            $aa = $pdo->prepare("
                SELECT aa.*, b.name AS barangay_name
                FROM affected_areas aa
                JOIN barangays b ON aa.barangay_id = b.id
                WHERE aa.incident_id = ?
            ");
            $aa->execute([$incident['id']]);
            $incident['affected_areas'] = $aa->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode($incident);
        exit;
    }

    // Get hazard types
    if ($_GET['ajax'] === 'hazard_types') {
        echo json_encode($pdo->query("SELECT * FROM hazard_types ORDER BY name")->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // Get households for map display
    if ($_GET['ajax'] === 'households_for_map') {
        $stmt = $pdo->query("
            SELECT h.id, h.household_head, h.latitude, h.longitude, h.family_members,
                   h.pwd_count, h.senior_count, h.infant_count, h.pregnant_count, h.ip_non_ip,
                   b.name AS barangay_name
            FROM households h
            JOIN barangays b ON h.barangay_id = b.id
            WHERE h.latitude IS NOT NULL AND h.longitude IS NOT NULL
              AND h.latitude != 0 AND h.longitude != 0
              AND h.latitude BETWEEN 12.50 AND 13.20
              AND h.longitude BETWEEN 120.50 AND 121.20
        ");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // Get barangay boundaries for map
    if ($_GET['ajax'] === 'boundaries') {
        $stmt = $pdo->query("SELECT id, name, boundary_geojson, coordinates FROM barangays WHERE boundary_geojson IS NOT NULL AND boundary_geojson != ''");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // Save incident
    if ($_GET['ajax'] === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_POST['id'] ?? null;
        $title = trim($_POST['title'] ?? '');
        $hazard_type_id = $_POST['hazard_type_id'] ?? null;
        $incident_date = $_POST['incident_date'] ?? date('Y-m-d');
        $status = $_POST['status'] ?? 'ongoing';
        $description = trim($_POST['description'] ?? '');
        $polygon = $_POST['affected_polygon'] ?? '';

        if ($title === '' || !$hazard_type_id || !$incident_date) {
            echo json_encode(['ok' => false, 'msg' => 'Title, disaster type, and date are required.']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            if ($id) {
                $pdo->prepare("UPDATE incident_reports SET title=?, hazard_type_id=?, incident_date=?, status=?, affected_polygon=?, description=?, updated_at=NOW() WHERE id=?")
                    ->execute([$title, $hazard_type_id, $incident_date, $status, $polygon, $description, $id]);
                // Clear old affected areas
                $pdo->prepare("DELETE FROM affected_areas WHERE incident_id = ?")->execute([$id]);
            } else {
                $pdo->prepare("INSERT INTO incident_reports (title, hazard_type_id, incident_date, status, affected_polygon, description, reported_by) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$title, $hazard_type_id, $incident_date, $status, $polygon, $description, $_SESSION['user_id']]);
                $id = $pdo->lastInsertId();
            }

            // Compute affected areas from polygon
            $computation = null;
            if ($polygon && $polygon !== '') {
                $computation = compute_affected_areas($pdo, $polygon);

                foreach ($computation['by_barangay'] as $area) {
                    $pdo->prepare("INSERT INTO affected_areas (incident_id, barangay_id, affected_households, affected_population, affected_pwd, affected_seniors, affected_infants, affected_minors, affected_pregnant, ip_count)
                        VALUES (?,?,?,?,?,?,?,?,?,?)")
                        ->execute([$id, $area['barangay_id'], $area['affected_households'], $area['affected_population'],
                            $area['affected_pwd'], $area['affected_seniors'], $area['affected_infants'],
                            $area['affected_minors'], $area['affected_pregnant'], $area['ip_count']]);
                }
            }

            $pdo->commit();

            $response = ['ok' => true, 'msg' => 'Incident report saved.', 'incident_id' => $id];
            if ($computation) {
                $response['computation'] = $computation;
            }
            echo json_encode($response);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
        }
        exit;
    }

    // Delete incident
    if ($_GET['ajax'] === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM incident_reports WHERE id = ?")->execute([$id]);
        echo json_encode(['ok' => true, 'msg' => 'Incident deleted.']);
        exit;
    }

    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incident Reports - Sablayan Risk Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css" />
    <style>
        body { background: #f0f2f5; }
        .main-content { margin-left: 0; }
        @media(min-width:768px){ .main-content { margin-left: 16.666667%; } }
        #incidentMap { height: 450px; border-radius: .5rem; }
        .stat-card { border-left: 4px solid; border-radius: .5rem; }
        .stat-card.blue { border-color: #0d6efd; }
        .stat-card.red { border-color: #dc3545; }
        .stat-card.green { border-color: #198754; }
        .status-ongoing { color: #dc3545; font-weight: 600; }
        .status-monitoring { color: #ffc107; font-weight: 600; }
        .status-resolved { color: #198754; font-weight: 600; }
    </style>
</head>
<body>
<div class="container-fluid">
<div class="row">
<?php include 'sidebar.php'; ?>
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
    <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h4 class="fw-bold"><i class="fas fa-file-circle-exclamation me-2"></i>Incident Reports</h4>
        <button class="btn btn-primary" onclick="openAddModal()"><i class="fas fa-plus me-1"></i> New Incident</button>
    </div>

    <!-- Summary -->
    <div class="row g-3 mb-4" id="summaryCards"></div>

    <!-- Filter -->
    <div class="row mb-3">
        <div class="col-md-3">
            <select class="form-select form-select-sm" id="filterStatus" onchange="renderTable()">
                <option value="">All Statuses</option>
                <option value="ongoing">Ongoing</option>
                <option value="monitoring">Monitoring</option>
                <option value="resolved">Resolved</option>
            </select>
        </div>
        <div class="col-md-3">
            <input type="text" class="form-control form-control-sm" id="filterSearch" placeholder="Search..." oninput="renderTable()">
        </div>
    </div>

    <!-- Table -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr><th>Title</th><th>Disaster Type</th><th>Date</th><th>Status</th><th class="text-center">Affected Pop.</th><th class="text-center">Actions</th></tr>
                    </thead>
                    <tbody id="incidentBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</main>
</div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="incidentModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="incidentModalTitle">New Incident Report</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="incidentForm">
        <div class="modal-body">
          <input type="hidden" name="id" id="incId">
          <div class="row g-3 mb-3">
            <div class="col-md-6"><label class="form-label">Incident Title <span class="text-danger">*</span></label><input type="text" class="form-control" name="title" id="incTitle" required></div>
            <div class="col-md-3"><label class="form-label">Disaster Type <span class="text-danger">*</span></label><select class="form-select" name="hazard_type_id" id="incType" required></select></div>
            <div class="col-md-3"><label class="form-label">Date <span class="text-danger">*</span></label><input type="date" class="form-control" name="incident_date" id="incDate" required></div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status" id="incStatus"><option value="ongoing">Ongoing</option><option value="monitoring">Monitoring</option><option value="resolved">Resolved</option></select></div>
            <div class="col-md-9"><label class="form-label">Description</label><textarea class="form-control" name="description" id="incDesc" rows="2"></textarea></div>
          </div>

          <!-- Map Section -->
          <h6 class="text-primary border-bottom pb-2 mb-3"><i class="fas fa-draw-polygon me-1"></i> Draw Affected Area</h6>
          <div class="alert alert-info small py-2">
            <i class="fas fa-info-circle me-1"></i> Use the polygon draw tool (top-right of map) to draw the affected area. Household dots and barangay boundaries are shown for reference. Satellite view recommended.
          </div>
          <div id="incidentMap" class="mb-3"></div>
          <input type="hidden" name="affected_polygon" id="incPolygon">

          <!-- System capability note -->
          <div class="alert alert-secondary small py-2">
            <i class="fas fa-lightbulb me-1"></i> <strong>SYSTEM CAPABILITY NOTE:</strong> This system currently supports manual polygon drawing for affected area mapping after an incident occurs. This can be upgraded in a future version to support real-time GPS-based incident tracking where the affected polygon is automatically generated from live field reports submitted during an active disaster. The household GPS infrastructure is already in place to support this upgrade.
          </div>

          <!-- Results section (shown after save) -->
          <div id="computationResults" class="d-none">
            <h6 class="text-success border-bottom pb-2 mb-3"><i class="fas fa-calculator me-1"></i> Computation Results</h6>
            <div class="row g-3 mb-3" id="resultsSummary"></div>
            <div class="table-responsive" id="resultsTable"></div>
            <div id="resultsWarning"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save & Compute</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title" id="viewTitle">Incident Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="viewBody"></div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>
<script>
const incidentModal = new bootstrap.Modal('#incidentModal');
const viewModal = new bootstrap.Modal('#viewModal');
let allIncidents = [];
let incMap, drawnItems, householdDots = [], boundaryLayers = [];

$(document).ready(function(){
    loadIncidents();
    loadHazardTypes();

    $('#incidentForm').on('submit', function(e){
        e.preventDefault();
        // Get polygon from drawn layer
        let layers = drawnItems ? drawnItems.getLayers() : [];
        if (layers.length > 0) {
            let geo = layers[0].toGeoJSON().geometry;
            $('#incPolygon').val(JSON.stringify(geo));
        }
        $.post('incident_reports.php?ajax=save', $(this).serialize(), function(res){
            if (res.ok) {
                showToast(res.msg, 'success');
                loadIncidents();
                if (res.computation) showComputationResults(res.computation);
                else incidentModal.hide();
            } else {
                showToast(res.msg, 'danger');
            }
        }, 'json');
    });
});

function loadIncidents(){
    $.getJSON('incident_reports.php?ajax=list', function(res){
        allIncidents = res.data;
        renderSummary();
        renderTable();
    });
}

function loadHazardTypes(){
    $.getJSON('incident_reports.php?ajax=hazard_types', function(types){
        let opts = '<option value="">-- Select --</option>';
        types.forEach(t => opts += `<option value="${t.id}">${t.name}</option>`);
        $('#incType').html(opts);
    });
}

function renderSummary(){
    let total = allIncidents.length;
    let ongoing = allIncidents.filter(i => i.status === 'ongoing').length;
    let totalAffected = allIncidents.reduce((s,i) => s + (parseInt(i.total_affected)||0), 0);
    $('#summaryCards').html(`
        <div class="col-6 col-md-4"><div class="card stat-card blue p-3"><div class="text-muted small">Total Incidents</div><div class="fs-4 fw-bold">${total}</div></div></div>
        <div class="col-6 col-md-4"><div class="card stat-card red p-3"><div class="text-muted small">Active (Ongoing)</div><div class="fs-4 fw-bold">${ongoing}</div></div></div>
        <div class="col-6 col-md-4"><div class="card stat-card green p-3"><div class="text-muted small">Total Affected</div><div class="fs-4 fw-bold">${totalAffected.toLocaleString()}</div></div></div>
    `);
}

function renderTable(){
    let status = $('#filterStatus').val();
    let search = ($('#filterSearch').val()||'').toLowerCase();
    let filtered = allIncidents;
    if (status) filtered = filtered.filter(i => i.status === status);
    if (search) filtered = filtered.filter(i => (i.title||'').toLowerCase().includes(search) || (i.hazard_name||'').toLowerCase().includes(search));

    let html = '';
    if (filtered.length === 0) html = '<tr><td colspan="6" class="text-center text-muted py-3">No incidents found.</td></tr>';
    filtered.forEach(i => {
        let statusClass = 'status-' + i.status;
        html += `<tr>
            <td class="fw-semibold">${esc(i.title)}</td>
            <td><span style="color:${i.hazard_color||'#333'}"><i class="fas ${i.hazard_icon||'fa-exclamation-triangle'} me-1"></i>${esc(i.hazard_name||'')}</span></td>
            <td>${i.incident_date}</td>
            <td><span class="${statusClass}">${i.status}</span></td>
            <td class="text-center">${(parseInt(i.total_affected)||0).toLocaleString()}</td>
            <td class="text-center">
                <button class="btn btn-sm btn-outline-info" onclick="viewIncident(${i.id})" title="View"><i class="fas fa-eye"></i></button>
                <button class="btn btn-sm btn-outline-primary" onclick="editIncident(${i.id})" title="Edit"><i class="fas fa-edit"></i></button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteIncident(${i.id})" title="Delete"><i class="fas fa-trash"></i></button>
            </td>
        </tr>`;
    });
    $('#incidentBody').html(html);
}

function openAddModal(){
    $('#incidentModalTitle').text('New Incident Report');
    $('#incidentForm')[0].reset();
    $('#incId').val('');
    $('#incDate').val(new Date().toISOString().split('T')[0]);
    $('#incPolygon').val('');
    $('#computationResults').addClass('d-none');
    incidentModal.show();
    setTimeout(initIncidentMap, 300);
}

function editIncident(id){
    $.getJSON('incident_reports.php?ajax=get&id=' + id, function(inc){
        if (!inc) return;
        $('#incidentModalTitle').text('Edit Incident Report');
        $('#incId').val(inc.id);
        $('#incTitle').val(inc.title);
        $('#incType').val(inc.hazard_type_id);
        $('#incDate').val(inc.incident_date);
        $('#incStatus').val(inc.status);
        $('#incDesc').val(inc.description);
        $('#incPolygon').val(inc.affected_polygon || '');
        // Show existing results
        if (inc.affected_areas && inc.affected_areas.length > 0) {
            let totals = {affected_households:0, affected_population:0, affected_pwd:0, affected_seniors:0, affected_infants:0, affected_minors:0, affected_pregnant:0, ip_count:0};
            inc.affected_areas.forEach(a => {
                totals.affected_households += parseInt(a.affected_households)||0;
                totals.affected_population += parseInt(a.affected_population)||0;
                totals.affected_pwd += parseInt(a.affected_pwd)||0;
                totals.affected_seniors += parseInt(a.affected_seniors)||0;
                totals.affected_infants += parseInt(a.affected_infants)||0;
                totals.affected_minors += parseInt(a.affected_minors)||0;
                totals.affected_pregnant += parseInt(a.affected_pregnant)||0;
                totals.ip_count += parseInt(a.ip_count)||0;
            });
            showComputationResults({totals: totals, by_barangay: inc.affected_areas.map(a => ({...a, barangay_name: a.barangay_name})), excluded_count: 0});
        }
        incidentModal.show();
        setTimeout(function(){
            initIncidentMap();
            // Load existing polygon
            if (inc.affected_polygon) {
                try {
                    let geo = JSON.parse(inc.affected_polygon);
                    L.geoJSON(geo, {
                        onEachFeature: function(f, layer){ drawnItems.addLayer(layer); }
                    });
                    drawnItems.getLayers()[0] && incMap.fitBounds(drawnItems.getBounds());
                } catch(e){}
            }
        }, 300);
    });
}

function viewIncident(id){
    $.getJSON('incident_reports.php?ajax=get&id=' + id, function(inc){
        if (!inc) return;
        let html = `<h5>${esc(inc.title)}</h5>
            <p><strong>Type:</strong> ${esc(inc.hazard_name||'')} | <strong>Date:</strong> ${inc.incident_date} | <strong>Status:</strong> <span class="status-${inc.status}">${inc.status}</span></p>
            <p>${esc(inc.description||'No description.')}</p>`;
        if (inc.affected_areas && inc.affected_areas.length > 0) {
            html += '<h6>Affected Areas</h6><table class="table table-sm"><thead><tr><th>Barangay</th><th>Households</th><th>Population</th><th>PWD</th><th>Seniors</th><th>Infants</th><th>Pregnant</th><th>IP</th></tr></thead><tbody>';
            let t = {h:0,p:0,pwd:0,s:0,i:0,pr:0,ip:0};
            inc.affected_areas.forEach(a => {
                html += `<tr><td>${esc(a.barangay_name)}</td><td>${a.affected_households}</td><td>${parseInt(a.affected_population).toLocaleString()}</td><td>${a.affected_pwd}</td><td>${a.affected_seniors}</td><td>${a.affected_infants}</td><td>${a.affected_pregnant}</td><td>${a.ip_count||0}</td></tr>`;
                t.h+=parseInt(a.affected_households)||0; t.p+=parseInt(a.affected_population)||0; t.pwd+=parseInt(a.affected_pwd)||0;
                t.s+=parseInt(a.affected_seniors)||0; t.i+=parseInt(a.affected_infants)||0; t.pr+=parseInt(a.affected_pregnant)||0; t.ip+=parseInt(a.ip_count)||0;
            });
            html += `<tr class="table-dark"><td><strong>Total</strong></td><td><strong>${t.h}</strong></td><td><strong>${t.p.toLocaleString()}</strong></td><td><strong>${t.pwd}</strong></td><td><strong>${t.s}</strong></td><td><strong>${t.i}</strong></td><td><strong>${t.pr}</strong></td><td><strong>${t.ip}</strong></td></tr>`;
            html += '</tbody></table>';
        }
        $('#viewTitle').text(inc.title);
        $('#viewBody').html(html);
        viewModal.show();
    });
}

function deleteIncident(id){
    if (!confirm('Delete this incident report?')) return;
    $.post('incident_reports.php?ajax=delete', {id:id}, function(res){
        if (res.ok) { showToast(res.msg, 'success'); loadIncidents(); }
        else showToast(res.msg, 'danger');
    }, 'json');
}

function initIncidentMap(){
    if (incMap) { incMap.remove(); incMap = null; }

    let tiles = {
        'OpenStreetMap': L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom:19}),
        'Satellite': L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {maxZoom:19}),
        'Terrain': L.tileLayer('https://tile.opentopomap.org/{z}/{x}/{y}.png', {maxZoom:17}),
        'Hybrid': L.layerGroup([
            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {maxZoom:19}),
            L.tileLayer('https://stamen-tiles.a.ssl.fastly.net/toner-labels/{z}/{x}/{y}.png', {maxZoom:19, opacity:0.7})
        ])
    };

    incMap = L.map('incidentMap', { layers: [tiles['Satellite']], center: [12.84, 120.87], zoom: 11 });
    L.control.layers(tiles).addTo(incMap);

    drawnItems = new L.FeatureGroup();
    incMap.addLayer(drawnItems);

    let drawCtrl = new L.Control.Draw({
        position: 'topright',
        draw: { polygon: { allowIntersection: false, shapeOptions: { color: '#dc3545', weight: 2, dashArray: '5,5' } },
                polyline: false, rectangle: false, circle: false, circlemarker: false, marker: false },
        edit: { featureGroup: drawnItems }
    });
    incMap.addControl(drawCtrl);

    incMap.on(L.Draw.Event.CREATED, function(e){ drawnItems.clearLayers(); drawnItems.addLayer(e.layer); });

    // Load boundaries
    $.getJSON('incident_reports.php?ajax=boundaries', function(boundaries){
        boundaries.forEach(b => {
            try {
                let geo = JSON.parse(b.boundary_geojson);
                L.geoJSON(geo, { style: { color: '#6c757d', weight: 1, fillOpacity: 0.05 } })
                    .bindTooltip(b.name, {permanent:true, direction:'center', className:'bg-transparent border-0 text-dark fw-bold shadow-none'})
                    .addTo(incMap);
            } catch(e){}
        });
    });

    // Load household dots
    $.getJSON('incident_reports.php?ajax=households_for_map', function(hhs){
        hhs.forEach(h => {
            let hasVuln = parseInt(h.pwd_count)>0 || parseInt(h.senior_count)>0 || parseInt(h.infant_count)>0 || parseInt(h.pregnant_count)>0;
            L.circleMarker([parseFloat(h.latitude), parseFloat(h.longitude)], {
                radius: 3, fillColor: hasVuln ? '#dc3545' : '#3498db', color: '#fff', weight: 1, fillOpacity: 0.8
            }).bindPopup(`<strong>${h.household_head}</strong><br>${h.barangay_name}<br>Members: ${h.family_members}`).addTo(incMap);
        });
    });
}

function showComputationResults(comp){
    let t = comp.totals;
    $('#resultsSummary').html(`
        <div class="col-4 col-md-2"><div class="bg-light rounded p-2 text-center"><div class="small text-muted">Households</div><div class="fw-bold">${t.affected_households}</div></div></div>
        <div class="col-4 col-md-2"><div class="bg-light rounded p-2 text-center"><div class="small text-muted">Population</div><div class="fw-bold">${t.affected_population.toLocaleString()}</div></div></div>
        <div class="col-4 col-md-2"><div class="bg-light rounded p-2 text-center"><div class="small text-muted">PWD</div><div class="fw-bold">${t.affected_pwd}</div></div></div>
        <div class="col-4 col-md-2"><div class="bg-light rounded p-2 text-center"><div class="small text-muted">Seniors</div><div class="fw-bold">${t.affected_seniors}</div></div></div>
        <div class="col-4 col-md-2"><div class="bg-light rounded p-2 text-center"><div class="small text-muted">Infants</div><div class="fw-bold">${t.affected_infants}</div></div></div>
        <div class="col-4 col-md-2"><div class="bg-light rounded p-2 text-center"><div class="small text-muted">Pregnant</div><div class="fw-bold">${t.affected_pregnant}</div></div></div>
    `);
    let tbl = '<table class="table table-sm"><thead><tr><th>Barangay</th><th>Households</th><th>Population</th><th>PWD</th><th>Seniors</th><th>Infants</th><th>Pregnant</th><th>IP</th></tr></thead><tbody>';
    comp.by_barangay.forEach(a => {
        tbl += `<tr><td>${esc(a.barangay_name)}</td><td>${a.affected_households}</td><td>${parseInt(a.affected_population).toLocaleString()}</td><td>${a.affected_pwd}</td><td>${a.affected_seniors}</td><td>${a.affected_infants}</td><td>${a.affected_pregnant}</td><td>${a.ip_count||0}</td></tr>`;
    });
    tbl += '</tbody></table>';
    $('#resultsTable').html(tbl);

    if (comp.excluded_count > 0) {
        $('#resultsWarning').html(`<div class="alert alert-warning small py-2"><i class="fas fa-exclamation-triangle me-1"></i> ${comp.excluded_count} household(s) with invalid/missing GPS were excluded from computation.</div>`);
    } else {
        $('#resultsWarning').html('');
    }
    $('#computationResults').removeClass('d-none');
}

function showToast(msg, type){
    let id = 'toast'+Date.now();
    $('body').append(`<div id="${id}" class="position-fixed bottom-0 end-0 p-3" style="z-index:9999"><div class="toast show align-items-center text-bg-${type} border-0"><div class="d-flex"><div class="toast-body">${msg}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div></div>`);
    setTimeout(()=>$('#'+id).remove(), 4000);
}

function esc(s){ if(!s) return ''; let d=document.createElement('div'); d.textContent=s; return d.innerHTML; }
</script>
</body>
</html>
