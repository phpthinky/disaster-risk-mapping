<?php
// barangay_management.php — Admin only — Barangay list + delete
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
                <a href="barangay_boundary.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> Add Barangay
                </a>
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
                                    <th class="text-center">Area (km&sup2;)</th>
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
const deleteModal = new bootstrap.Modal('#deleteModal');
let allData = [];
let deleteId = null;

$(document).ready(function(){
    loadData();

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
        html = '<tr><td colspan="7" class="text-center text-muted py-4">No barangays found.</td></tr>';
    }
    filtered.forEach(b => {
        let boundary = b.boundary_geojson
            ? '<span class="boundary-yes"><i class="fas fa-check-circle"></i> Drawn</span>'
            : '<span class="boundary-no"><i class="fas fa-exclamation-circle"></i> Not yet</span>';
        let manualArea = b.area_km2 ? parseFloat(b.area_km2).toFixed(2) : '--';
        let calcArea = b.calculated_area_km2 ? parseFloat(b.calculated_area_km2).toFixed(2) : '--';
        let areaDisplay = `<span title="Manual / Official">${manualArea}</span> <span class="text-muted">/</span> <span class="text-primary" title="Calculated from map">${calcArea}</span>`;
        html += `<tr>
            <td class="fw-semibold">${esc(b.name)}</td>
            <td>${b.staff_username ? esc(b.staff_username) : '<span class="text-muted">Unassigned</span>'}</td>
            <td class="text-center">${parseInt(b.household_count)||0}</td>
            <td class="text-center">${(parseInt(b.population)||0).toLocaleString()}</td>
            <td class="text-center" style="font-size:.85rem">${areaDisplay}</td>
            <td class="text-center">${boundary}</td>
            <td class="text-center">
                <a href="barangay_boundary.php?id=${b.id}" class="btn btn-sm btn-outline-primary me-1" title="Edit"><i class="fas fa-edit"></i></a>
                <button class="btn btn-sm btn-outline-danger" onclick="openDeleteModal(${b.id}, '${esc(b.name)}')" title="Delete"><i class="fas fa-trash"></i></button>
            </td>
        </tr>`;
    });
    $('#brgyBody').html(html);
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
