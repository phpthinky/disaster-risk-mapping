<?php
// population_archive.php — Admin & Division Chief
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if (!in_array($_SESSION['role'], ['admin', 'division_chief'])) { header('Location: dashboard.php'); exit; }

// ── AJAX ──
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    // Population history timeline
    if ($_GET['ajax'] === 'history') {
        $bid = $_GET['barangay_id'] ?? '';
        $sql = "SELECT pa.*, b.name AS barangay_name
                FROM population_data_archive pa
                JOIN barangays b ON pa.barangay_id = b.id";
        $params = [];
        if ($bid) { $sql .= " WHERE pa.barangay_id = ?"; $params[] = $bid; }
        $sql .= " ORDER BY pa.archived_at DESC LIMIT 200";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // Population comparison
    if ($_GET['ajax'] === 'comparison') {
        // Current computed from barangays
        $current = $pdo->query("
            SELECT b.id, b.name, b.population, b.household_count, b.pwd_count, b.senior_count,
                   b.children_count, b.infant_count, b.pregnant_count, b.ip_count,
                   GROUP_CONCAT(DISTINCT CASE WHEN hz.risk_level IN ('High Susceptible','Moderate Susceptible') THEN hz.risk_level END) AS high_risk_zones
            FROM barangays b
            LEFT JOIN hazard_zones hz ON b.id = hz.barangay_id
            GROUP BY b.id ORDER BY b.name
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Previous from archive (most recent per barangay)
        $previous = $pdo->query("
            SELECT pa.barangay_id, pa.total_population, pa.households, pa.archived_at
            FROM population_data_archive pa
            INNER JOIN (
                SELECT barangay_id, MAX(id) AS max_id
                FROM population_data_archive
                GROUP BY barangay_id
            ) latest ON pa.id = latest.max_id
        ")->fetchAll(PDO::FETCH_ASSOC);
        $prevMap = [];
        foreach ($previous as $p) $prevMap[$p['barangay_id']] = $p;

        $result = [];
        foreach ($current as $c) {
            $prev = $prevMap[$c['id']] ?? null;
            $prevPop = $prev ? (int)$prev['total_population'] : 0;
            $diff = (int)$c['population'] - $prevPop;
            $result[] = [
                'id' => $c['id'],
                'name' => $c['name'],
                'current_population' => (int)$c['population'],
                'previous_population' => $prevPop,
                'difference' => $diff,
                'current_households' => (int)$c['household_count'],
                'high_risk_zones' => $c['high_risk_zones'],
                'hazard_warning' => ($diff > 0 && $c['high_risk_zones']) ? true : false,
                'archived_at' => $prev['archived_at'] ?? null,
            ];
        }
        echo json_encode($result);
        exit;
    }

    // Export CSV
    if ($_GET['ajax'] === 'export_csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="population_comparison_'.date('Y-m-d').'.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Barangay','Current Population','Previous Population','Difference','Households','Hazard Warning']);

        $data = json_decode(file_get_contents('http://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']).'/population_archive.php?ajax=comparison'), true);
        foreach ($data as $row) {
            fputcsv($out, [$row['name'], $row['current_population'], $row['previous_population'], $row['difference'], $row['current_households'], $row['hazard_warning'] ? 'YES' : '']);
        }
        fclose($out);
        exit;
    }

    exit;
}

$barangayList = $pdo->query("SELECT id, name FROM barangays ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Population Archive - Sablayan Risk Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; }
        .main-content { margin-left: 0; }
        @media(min-width:768px){ .main-content { margin-left: 16.666667%; } }
        .diff-positive { color: #198754; font-weight: 600; }
        .diff-negative { color: #dc3545; font-weight: 600; }
        .diff-zero { color: #6c757d; }
        .warning-flag { color: #dc3545; }
    </style>
</head>
<body>
<div class="container-fluid">
<div class="row">
<?php include 'sidebar.php'; ?>
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
    <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h4 class="fw-bold"><i class="fas fa-clock-rotate-left me-2"></i>Population Archive & Comparison</h4>
        <a href="population_archive.php?ajax=export_csv" class="btn btn-sm btn-success"><i class="fas fa-download me-1"></i> Export CSV</a>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabComparison"><i class="fas fa-chart-bar me-1"></i> Comparison</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabHistory"><i class="fas fa-history me-1"></i> History Timeline</a></li>
    </ul>

    <div class="tab-content">
        <!-- Comparison Tab -->
        <div class="tab-pane fade show active" id="tabComparison">
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Barangay</th>
                                    <th class="text-center">Previous Pop.</th>
                                    <th class="text-center">Current Pop.</th>
                                    <th class="text-center">Difference</th>
                                    <th class="text-center">Households</th>
                                    <th class="text-center">Hazard Warning</th>
                                </tr>
                            </thead>
                            <tbody id="compBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- History Tab -->
        <div class="tab-pane fade" id="tabHistory">
            <div class="row mb-3">
                <div class="col-md-4">
                    <select class="form-select form-select-sm" id="historyBarangay" onchange="loadHistory()">
                        <option value="">All Barangays</option>
                        <?php foreach ($barangayList as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-dark">
                                <tr><th>Barangay</th><th>Population</th><th>Households</th><th>Elderly</th><th>Children</th><th>PWD</th><th>Change Type</th><th>Archived At</th><th>By</th></tr>
                            </thead>
                            <tbody id="historyBody"></tbody>
                        </table>
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
<script>
$(document).ready(function(){
    loadComparison();
    $('a[href="#tabHistory"]').on('shown.bs.tab', loadHistory);
});

function loadComparison(){
    $.getJSON('population_archive.php?ajax=comparison', function(data){
        let html = '';
        data.forEach(r => {
            let diffClass = r.difference > 0 ? 'diff-positive' : r.difference < 0 ? 'diff-negative' : 'diff-zero';
            let diffText = r.difference > 0 ? '+'+r.difference.toLocaleString() : r.difference.toLocaleString();
            let warning = r.hazard_warning ? '<span class="warning-flag"><i class="fas fa-exclamation-triangle"></i> Pop. increased in hazard zone</span>' : '-';
            html += `<tr>
                <td class="fw-semibold">${esc(r.name)}</td>
                <td class="text-center">${r.previous_population.toLocaleString()}</td>
                <td class="text-center fw-bold">${r.current_population.toLocaleString()}</td>
                <td class="text-center ${diffClass}">${diffText}</td>
                <td class="text-center">${r.current_households}</td>
                <td class="text-center">${warning}</td>
            </tr>`;
        });
        $('#compBody').html(html || '<tr><td colspan="6" class="text-center text-muted py-3">No data.</td></tr>');
    });
}

function loadHistory(){
    let bid = $('#historyBarangay').val() || '';
    $.getJSON('population_archive.php?ajax=history&barangay_id=' + bid, function(data){
        let html = '';
        data.forEach(r => {
            let changeBadge = r.change_type === 'UPDATE' ? '<span class="badge bg-primary">UPDATE</span>' : '<span class="badge bg-danger">DELETE</span>';
            html += `<tr>
                <td>${esc(r.barangay_name)}</td>
                <td>${parseInt(r.total_population).toLocaleString()}</td>
                <td>${r.households}</td>
                <td>${r.elderly_count}</td>
                <td>${r.children_count}</td>
                <td>${r.pwd_count}</td>
                <td>${changeBadge}</td>
                <td>${r.archived_at}</td>
                <td>${esc(r.archived_by||'-')}</td>
            </tr>`;
        });
        $('#historyBody').html(html || '<tr><td colspan="9" class="text-center text-muted py-3">No archive data yet.</td></tr>');
    });
}

function esc(s){ if(!s) return ''; let d=document.createElement('div'); d.textContent=s; return d.innerHTML; }
</script>
</body>
</html>
