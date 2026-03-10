<?php
// population_comparison.php — Admin and Division Chief only
// Phase 6: Population comparison view

session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'division_chief'])) {
    header('Location: login.php');
    exit;
}

// Filters
$filter_barangay = isset($_GET['barangay_id']) ? (int)$_GET['barangay_id'] : 0;
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to   = $_GET['date_to']   ?? '';

$where_brgy = $filter_barangay ? "AND b.id = $filter_barangay" : '';

// Get all barangays for dropdown
$barangays_list = $pdo->query("SELECT id, name FROM barangays ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Main comparison query:
// current = latest population_data row per barangay
// previous = second-latest from population_data_archive
$comparison = $pdo->query("
    SELECT
        b.id AS barangay_id,
        b.name AS barangay_name,
        cur.total_population   AS current_pop,
        cur.data_date          AS current_date,
        arch.total_population  AS prev_pop,
        arch.archived_at       AS prev_date,
        (cur.total_population - COALESCE(arch.total_population, cur.total_population)) AS diff,
        MAX(CASE WHEN hz.risk_level IN ('High Susceptible','high') THEN 1 ELSE 0 END) AS has_high_risk,
        MAX(CASE WHEN hz.risk_level IN ('Moderate Susceptible','medium') THEN 1 ELSE 0 END) AS has_moderate_risk
    FROM barangays b
    LEFT JOIN (
        SELECT barangay_id, total_population, data_date
        FROM population_data
    ) cur ON cur.barangay_id = b.id
    LEFT JOIN (
        SELECT pa.barangay_id, pa.total_population, pa.archived_at
        FROM population_data_archive pa
        INNER JOIN (
            SELECT barangay_id, MAX(archived_at) AS max_at
            FROM population_data_archive
            GROUP BY barangay_id
        ) latest ON pa.barangay_id = latest.barangay_id AND pa.archived_at = latest.max_at
    ) arch ON arch.barangay_id = b.id
    LEFT JOIN hazard_zones hz ON hz.barangay_id = b.id
    WHERE 1=1 $where_brgy
    GROUP BY b.id, b.name, cur.total_population, cur.data_date, arch.total_population, arch.archived_at
    ORDER BY b.name
")->fetchAll(PDO::FETCH_ASSOC);

// Handle CSV export
if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="population_comparison_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Barangay', 'Previous Population', 'Current Population', 'Difference', 'High Risk Zone', 'Warning']);
    foreach ($comparison as $row) {
        $diff = ($row['current_pop'] !== null && $row['prev_pop'] !== null)
            ? $row['current_pop'] - $row['prev_pop'] : '';
        $warning = '';
        if ($diff > 0 && ($row['has_high_risk'] || $row['has_moderate_risk'])) {
            $warning = 'Population increased in risk zone';
        }
        fputcsv($out, [
            $row['barangay_name'],
            $row['prev_pop'] ?? 'N/A',
            $row['current_pop'] ?? 'N/A',
            ($diff !== '' ? ($diff > 0 ? '+' : '') . $diff : 'N/A'),
            ($row['has_high_risk'] ? 'High' : ($row['has_moderate_risk'] ? 'Moderate' : 'No')),
            $warning
        ]);
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Population Comparison — Sablayan Risk Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f8f9fa; }
        .diff-positive { color: #198754; font-weight: bold; }
        .diff-negative { color: #dc3545; font-weight: bold; }
        .diff-zero { color: #6c757d; }
        .warn-flag { color: #dc3545; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include 'sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <?php include 'navbar.php'; ?>

            <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
                <h4><i class="fas fa-chart-line me-2"></i>Population Comparison</h4>
                <div class="d-flex gap-2">
                    <a href="?export_csv=1&barangay_id=<?php echo $filter_barangay; ?>"
                       class="btn btn-success btn-sm">
                        <i class="fas fa-file-csv me-1"></i> Export CSV
                    </a>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-3">
                <div class="card-body">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">Barangay</label>
                            <select name="barangay_id" class="form-select">
                                <option value="">All Barangays</option>
                                <?php foreach ($barangays_list as $b): ?>
                                <option value="<?php echo $b['id']; ?>"
                                    <?php echo $filter_barangay == $b['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($b['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-auto">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-filter me-1"></i> Filter
                            </button>
                            <a href="population_comparison.php" class="btn btn-secondary btn-sm">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Comparison Table -->
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Barangay</th>
                                    <th>Previous Population</th>
                                    <th>Current Population</th>
                                    <th>Difference</th>
                                    <th>Hazard Zone</th>
                                    <th>Warning</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($comparison as $row): ?>
                                <?php
                                    $cur  = $row['current_pop'];
                                    $prev = $row['prev_pop'];
                                    $diff = ($cur !== null && $prev !== null) ? ($cur - $prev) : null;
                                    $isHighRisk = $row['has_high_risk'] || $row['has_moderate_risk'];
                                    $warn = ($diff !== null && $diff > 0 && $isHighRisk);
                                ?>
                                <tr class="<?php echo $warn ? 'table-danger' : ''; ?>">
                                    <td><strong><?php echo htmlspecialchars($row['barangay_name']); ?></strong></td>
                                    <td>
                                        <?php if ($prev !== null): ?>
                                            <?php echo number_format($prev); ?>
                                            <small class="text-muted d-block"><?php echo $row['prev_date'] ? date('M d, Y', strtotime($row['prev_date'])) : ''; ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">No archive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($cur !== null): ?>
                                            <?php echo number_format($cur); ?>
                                            <small class="text-muted d-block"><?php echo $row['current_date'] ? date('M d, Y', strtotime($row['current_date'])) : ''; ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">No data</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($diff !== null): ?>
                                            <span class="<?php echo $diff > 0 ? 'diff-positive' : ($diff < 0 ? 'diff-negative' : 'diff-zero'); ?>">
                                                <?php echo ($diff > 0 ? '+' : '') . number_format($diff); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['has_high_risk']): ?>
                                            <span class="badge bg-danger">High Risk</span>
                                        <?php elseif ($row['has_moderate_risk']): ?>
                                            <span class="badge bg-warning text-dark">Moderate Risk</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Low / None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($warn): ?>
                                            <span class="warn-flag"><i class="fas fa-triangle-exclamation me-1"></i>Population increased in risk zone</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
