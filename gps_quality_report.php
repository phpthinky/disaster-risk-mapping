<?php
// gps_quality_report.php — Admin only
// Phase 2: GPS data quality report — lists households with missing or invalid coordinates

session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Bounds for Sablayan
define('LAT_MIN', 12.50);
define('LAT_MAX', 13.20);
define('LNG_MIN', 120.50);
define('LNG_MAX', 121.20);

// Filter
$filter_barangay = isset($_GET['barangay_id']) ? (int)$_GET['barangay_id'] : 0;
$where = $filter_barangay ? "AND h.barangay_id = $filter_barangay" : '';

$barangays_list = $pdo->query("SELECT id, name FROM barangays ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Count invalid records per barangay
$brgy_counts = $pdo->query("
    SELECT b.id, b.name,
           COUNT(h.id) AS total_hh,
           SUM(CASE WHEN h.latitude IS NULL OR h.longitude IS NULL THEN 1 ELSE 0 END) AS missing_gps,
           SUM(CASE WHEN h.latitude IS NOT NULL AND h.longitude IS NOT NULL
               AND (h.latitude < 12.50 OR h.latitude > 13.20
                    OR h.longitude < 120.50 OR h.longitude > 121.20) THEN 1 ELSE 0 END) AS invalid_gps
    FROM barangays b
    LEFT JOIN households h ON h.barangay_id = b.id
    GROUP BY b.id, b.name
    ORDER BY (missing_gps + invalid_gps) DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Flagged households
$flagged_stmt = $pdo->prepare("
    SELECT h.id, h.household_head, h.barangay_id, h.latitude, h.longitude, h.family_members,
           b.name AS barangay_name,
           CASE
               WHEN h.latitude IS NULL OR h.longitude IS NULL THEN 'Missing'
               ELSE 'Invalid Range'
           END AS gps_issue
    FROM households h
    JOIN barangays b ON b.id = h.barangay_id
    WHERE (
        h.latitude IS NULL OR h.longitude IS NULL
        OR h.latitude < 12.50 OR h.latitude > 13.20
        OR h.longitude < 120.50 OR h.longitude > 121.20
    ) $where
    ORDER BY b.name, h.household_head
");
$flagged_stmt->execute();
$flagged = $flagged_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_flagged = count($flagged);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GPS Quality Report — Sablayan Risk Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f8f9fa; }
        .main-content { padding: 20px; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include 'sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <?php include 'navbar.php'; ?>

            <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
                <h4><i class="fas fa-map-marker-alt me-2 text-danger"></i>GPS Data Quality Report</h4>
                <span class="badge bg-danger fs-6"><?php echo $total_flagged; ?> records need attention</span>
            </div>

            <div class="alert alert-info">
                <i class="fas fa-info-circle me-1"></i>
                This report lists all households with <strong>missing</strong> or <strong>out-of-range</strong> GPS coordinates.
                Valid range: Latitude 12.50–13.20, Longitude 120.50–121.20. Fix these records before relying on polygon auto-computation.
            </div>

            <!-- Per-barangay summary -->
            <div class="card mb-4">
                <div class="card-header"><strong>Invalid GPS Count per Barangay</strong></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Barangay</th>
                                    <th>Total Households</th>
                                    <th>Missing GPS</th>
                                    <th>Invalid Range</th>
                                    <th>Total Flagged</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($brgy_counts as $r): ?>
                                <?php $flagged_count = $r['missing_gps'] + $r['invalid_gps']; ?>
                                <tr class="<?php echo $flagged_count > 0 ? 'table-warning' : ''; ?>">
                                    <td><?php echo htmlspecialchars($r['name']); ?></td>
                                    <td><?php echo $r['total_hh']; ?></td>
                                    <td><?php echo $r['missing_gps'] ?: '—'; ?></td>
                                    <td><?php echo $r['invalid_gps'] ?: '—'; ?></td>
                                    <td>
                                        <?php if ($flagged_count > 0): ?>
                                            <span class="badge bg-danger"><?php echo $flagged_count; ?></span>
                                        <?php else: ?>
                                            <span class="text-success"><i class="fas fa-check-circle"></i></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($flagged_count > 0): ?>
                                            <a href="?barangay_id=<?php echo $r['id']; ?>" class="btn btn-xs btn-sm btn-outline-danger py-0">
                                                Filter
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Filter -->
            <div class="card mb-3">
                <div class="card-body">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">Filter by Barangay</label>
                            <select name="barangay_id" class="form-select">
                                <option value="">All Barangays</option>
                                <?php foreach ($barangays_list as $b): ?>
                                <option value="<?php echo $b['id']; ?>" <?php echo $filter_barangay == $b['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($b['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-auto">
                            <button class="btn btn-primary btn-sm" type="submit">
                                <i class="fas fa-filter me-1"></i> Filter
                            </button>
                            <a href="gps_quality_report.php" class="btn btn-secondary btn-sm">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Flagged records table -->
            <?php if (empty($flagged)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-1"></i>
                    All households have valid GPS coordinates. Auto-computation can be fully trusted.
                </div>
            <?php else: ?>
            <div class="card">
                <div class="card-header"><strong>Flagged Household Records (<?php echo $total_flagged; ?>)</strong></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>#</th>
                                    <th>Household Head</th>
                                    <th>Barangay</th>
                                    <th>Latitude</th>
                                    <th>Longitude</th>
                                    <th>Issue</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($flagged as $i => $hh): ?>
                                <tr>
                                    <td><?php echo $i + 1; ?></td>
                                    <td><?php echo htmlspecialchars($hh['household_head']); ?></td>
                                    <td><?php echo htmlspecialchars($hh['barangay_name']); ?></td>
                                    <td><?php echo $hh['latitude'] ?? '<span class="text-danger">NULL</span>'; ?></td>
                                    <td><?php echo $hh['longitude'] ?? '<span class="text-danger">NULL</span>'; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $hh['gps_issue'] === 'Missing' ? 'secondary' : 'danger'; ?>">
                                            <?php echo $hh['gps_issue']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="households.php?edit_id=<?php echo $hh['id']; ?>" class="btn btn-sm btn-warning py-0">
                                            <i class="fas fa-edit"></i> Fix GPS
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
