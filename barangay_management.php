<?php
// barangay_management.php — Admin only
session_start();
require_once 'config.php';
require_once 'sync_functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

// ── AJAX handlers ──────────────────────────────────────
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    // List barangays
    if ($_GET['ajax'] === 'list') {
        $rows = $pdo->query("
            SELECT b.*, u.username AS staff_username, u.id AS staff_user_id
            FROM barangays b
            LEFT JOIN users u ON u.barangay_id = b.id AND u.role = 'barangay_staff'
            ORDER BY b.name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['data' => $rows]);
        exit;
    }

    // Get single barangay
    if ($_GET['ajax'] === 'get' && isset($_GET['id'])) {
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

    // Get available staff users
    if ($_GET['ajax'] === 'staff_users') {
        $rows = $pdo->query("
            SELECT id, username FROM users
            WHERE role = 'barangay_staff'
            ORDER BY username ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows);
        exit;
    }

    // Save (add or edit)
    if ($_GET['ajax'] === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $name       = trim($_POST['name'] ?? '');
        $area_km2   = $_POST['area_km2'] ?: null;
        $coords     = trim($_POST['coordinates'] ?? '');
        $staff_id   = $_POST['staff_user_id'] ?: null;
        $id         = $_POST['id'] ?? null;

        if ($name === '') {
            echo json_encode(['ok' => false, 'msg' => 'Barangay name is required.']);
            exit;
        }

        try {
            if ($id) {
                // Update
                $stmt = $pdo->prepare("UPDATE barangays SET name = ?, area_km2 = ?, coordinates = ? WHERE id = ?");
                $stmt->execute([$name, $area_km2, $coords, $id]);

                // Unassign previous staff
                $pdo->prepare("UPDATE users SET barangay_id = NULL WHERE barangay_id = ? AND role = 'barangay_staff'")->execute([$id]);
            } else {
                // Insert
                $stmt = $pdo->prepare("INSERT INTO barangays (name, area_km2, coordinates) VALUES (?, ?, ?)");
                $stmt->execute([$name, $area_km2, $coords]);
                $id = $pdo->lastInsertId();
            }

            // Assign staff
            if ($staff_id) {
                $pdo->prepare("UPDATE users SET barangay_id = ? WHERE id = ? AND role = 'barangay_staff'")->execute([$id, $staff_id]);
            }

            echo json_encode(['ok' => true, 'msg' => 'Barangay saved successfully.']);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
        }
        exit;
    }

    // Delete
    if ($_GET['ajax'] === 'delete' && isset($_POST['id'])) {
        $id = $_POST['id'];
        // Check for linked households
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM households WHERE barangay_id = ?");
        $cnt->execute([$id]);
        if ($cnt->fetchColumn() > 0) {
            echo json_encode(['ok' => false, 'msg' => 'Cannot delete: households are linked to this barangay.']);
            exit;
        }
        // Unassign staff
        $pdo->prepare("UPDATE users SET barangay_id = NULL WHERE barangay_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM barangays WHERE id = ?")->execute([$id]);
        echo json_encode(['ok' => true, 'msg' => 'Barangay deleted.']);
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
    <title>Barangay Management - Sablayan Risk Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; }
        .main-content { margin-left: 0; }
        @media(min-width:768px){ .main-content { margin-left: 16.666667%; } }
        .stat-card { border-left: 4px solid; border-radius: .5rem; }
        .stat-card.blue  { border-color: #0d6efd; }
        .stat-card.green { border-color: #198754; }
        .stat-card.amber { border-color: #ffc107; }
        .stat-card.red   { border-color: #dc3545; }
        .boundary-yes { color: #198754; }
        .boundary-no  { color: #dc3545; }
        .table th { white-space: nowrap; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include 'sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h4 class="fw-bold"><i class="fas fa-map-marked-alt me-2"></i>Barangay Management</h4>
                <button class="btn btn-primary" onclick="openAddModal()">
                    <i class="fas fa-plus me-1"></i> Add Barangay
                </button>
            </div>

            <!-- Summary cards -->
            <div class="row g-3 mb-4" id="summaryCards"></div>

            <!-- Search -->
            <div class="mb-3">
                <input type="text" class="form-control" id="searchBox" placeholder="Search barangays...">
            </div>

            <!-- Table -->
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="brgyTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>Barangay Name</th>
                                    <th>Assigned Staff</th>
                                    <th class="text-center">Households</th>
                                    <th class="text-center">Population</th>
                                    <th class="text-center">Boundary</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="brgyBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="brgyModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="brgyModalTitle">Add Barangay</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="brgyForm">
        <div class="modal-body">
          <input type="hidden" name="id" id="brgyId">
          <div class="mb-3">
            <label class="form-label">Barangay Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="name" id="brgyName" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Area (km&sup2;)</label>
            <input type="number" step="0.01" class="form-control" name="area_km2" id="brgyArea">
          </div>
          <div class="mb-3">
            <label class="form-label">Center Coordinates (lat, lng)</label>
            <input type="text" class="form-control" name="coordinates" id="brgyCoords" placeholder="e.g. 12.8435, 120.8754">
          </div>
          <div class="mb-3">
            <label class="form-label">Assign Staff User</label>
            <select class="form-select" name="staff_user_id" id="brgyStaff">
              <option value="">-- None --</option>
            </select>
          </div>
          <!-- Computed stats (shown when editing) -->
          <div id="computedStats" class="d-none">
            <hr>
            <h6 class="text-muted">Computed Statistics (read-only)</h6>
            <div class="row g-2" id="statsGrid"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Delete confirm Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">Delete Barangay</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to delete <strong id="delName"></strong>?</p>
        <p class="text-muted small">Only allowed if no households are linked.</p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
const brgyModal = new bootstrap.Modal('#brgyModal');
const deleteModal = new bootstrap.Modal('#deleteModal');
let allData = [];
let deleteId = null;

$(document).ready(function(){
    loadData();
    loadStaffUsers();

    $('#brgyForm').on('submit', function(e){
        e.preventDefault();
        $.post('barangay_management.php?ajax=save', $(this).serialize(), function(res){
            if (res.ok) {
                brgyModal.hide();
                loadData();
                showToast(res.msg, 'success');
            } else {
                showToast(res.msg, 'danger');
            }
        }, 'json');
    });

    $('#confirmDeleteBtn').on('click', function(){
        if (!deleteId) return;
        $.post('barangay_management.php?ajax=delete', {id: deleteId}, function(res){
            deleteModal.hide();
            if (res.ok) {
                loadData();
                showToast(res.msg, 'success');
            } else {
                showToast(res.msg, 'danger');
            }
        }, 'json');
    });

    $('#searchBox').on('input', function(){
        renderTable($(this).val().toLowerCase());
    });
});

function loadData(){
    $.getJSON('barangay_management.php?ajax=list', function(res){
        allData = res.data;
        renderSummary();
        renderTable('');
    });
}

function loadStaffUsers(){
    $.getJSON('barangay_management.php?ajax=staff_users', function(users){
        let opts = '<option value="">-- None --</option>';
        users.forEach(u => opts += `<option value="${u.id}">${u.username}</option>`);
        $('#brgyStaff').html(opts);
    });
}

function renderSummary(){
    let total = allData.length;
    let withBoundary = allData.filter(b => b.boundary_geojson).length;
    let totalPop = allData.reduce((s,b) => s + (parseInt(b.population)||0), 0);
    let totalHH = allData.reduce((s,b) => s + (parseInt(b.household_count)||0), 0);

    $('#summaryCards').html(`
        <div class="col-6 col-md-3"><div class="card stat-card blue p-3"><div class="text-muted small">Total Barangays</div><div class="fs-4 fw-bold">${total}</div></div></div>
        <div class="col-6 col-md-3"><div class="card stat-card green p-3"><div class="text-muted small">Boundaries Drawn</div><div class="fs-4 fw-bold">${withBoundary} / ${total}</div></div></div>
        <div class="col-6 col-md-3"><div class="card stat-card amber p-3"><div class="text-muted small">Total Population</div><div class="fs-4 fw-bold">${totalPop.toLocaleString()}</div></div></div>
        <div class="col-6 col-md-3"><div class="card stat-card red p-3"><div class="text-muted small">Total Households</div><div class="fs-4 fw-bold">${totalHH.toLocaleString()}</div></div></div>
    `);
}

function renderTable(filter){
    let html = '';
    let filtered = filter ? allData.filter(b => b.name.toLowerCase().includes(filter)) : allData;
    if (filtered.length === 0) {
        html = '<tr><td colspan="6" class="text-center text-muted py-4">No barangays found.</td></tr>';
    }
    filtered.forEach(b => {
        let boundary = b.boundary_geojson
            ? '<span class="boundary-yes"><i class="fas fa-check-circle"></i> Drawn</span>'
            : '<span class="boundary-no"><i class="fas fa-exclamation-circle"></i> Not yet</span>';
        html += `<tr>
            <td class="fw-semibold">${esc(b.name)}</td>
            <td>${b.staff_username ? esc(b.staff_username) : '<span class="text-muted">Unassigned</span>'}</td>
            <td class="text-center">${parseInt(b.household_count)||0}</td>
            <td class="text-center">${(parseInt(b.population)||0).toLocaleString()}</td>
            <td class="text-center">${boundary}</td>
            <td class="text-center">
                <button class="btn btn-sm btn-outline-primary me-1" onclick="openEditModal(${b.id})" title="Edit"><i class="fas fa-edit"></i></button>
                <a href="barangay_boundary.php?id=${b.id}" class="btn btn-sm btn-outline-success me-1" title="Draw Boundary"><i class="fas fa-draw-polygon"></i></a>
                <button class="btn btn-sm btn-outline-danger" onclick="openDeleteModal(${b.id}, '${esc(b.name)}')" title="Delete"><i class="fas fa-trash"></i></button>
            </td>
        </tr>`;
    });
    $('#brgyBody').html(html);
}

function openAddModal(){
    $('#brgyModalTitle').text('Add Barangay');
    $('#brgyForm')[0].reset();
    $('#brgyId').val('');
    $('#computedStats').addClass('d-none');
    brgyModal.show();
}

function openEditModal(id){
    $.getJSON('barangay_management.php?ajax=get&id=' + id, function(b){
        if (!b) return;
        $('#brgyModalTitle').text('Edit Barangay');
        $('#brgyId').val(b.id);
        $('#brgyName').val(b.name);
        $('#brgyArea').val(b.area_km2);
        $('#brgyCoords').val(b.coordinates);
        $('#brgyStaff').val(b.staff_user_id || '');
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
        stats.forEach(s => grid += `<div class="col-6 col-md-3"><div class="bg-light rounded p-2 text-center"><div class="small text-muted">${s.label}</div><div class="fw-bold">${parseInt(s.val).toLocaleString()}</div></div></div>`);
        $('#statsGrid').html(grid);
        $('#computedStats').removeClass('d-none');
        brgyModal.show();
    });
}

function openDeleteModal(id, name){
    deleteId = id;
    $('#delName').text(name);
    deleteModal.show();
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

function esc(s){ let d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
</script>
</body>
</html>
