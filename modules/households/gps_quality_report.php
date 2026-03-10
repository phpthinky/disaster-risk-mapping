<?php
// modules/households/gps_quality_report.php — Admin only
session_start();
require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL . 'login.php'); exit;
}

// Filter
$filter_barangay = isset($_GET['barangay_id']) ? (int)$_GET['barangay_id'] : 0;

$barangays_list = $pdo->query("SELECT id, name FROM barangays ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Per-barangay GPS summary
$brgy_counts = $pdo->query("
    SELECT b.id, b.name,
           COUNT(h.id) AS total_hh,
           SUM(CASE WHEN h.latitude IS NULL OR h.longitude IS NULL THEN 1 ELSE 0 END) AS missing_gps,
           SUM(CASE WHEN h.latitude IS NOT NULL AND h.longitude IS NOT NULL
               AND (h.latitude < 12.50 OR h.latitude > 13.20
                    OR h.longitude < 120.50 OR h.longitude > 121.20) THEN 1 ELSE 0 END) AS invalid_gps,
           SUM(CASE WHEN h.latitude BETWEEN 12.50 AND 13.20
               AND h.longitude BETWEEN 120.50 AND 121.20 THEN 1 ELSE 0 END) AS valid_gps
    FROM barangays b
    LEFT JOIN households h ON h.barangay_id = b.id
    GROUP BY b.id, b.name
    ORDER BY (missing_gps + invalid_gps) DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Flagged households
$where_clause = $filter_barangay ? "AND h.barangay_id = :bid" : "";
$flagged_sql = "
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
    )
    $where_clause
    ORDER BY b.name, h.household_head
";
$flagged_stmt = $pdo->prepare($flagged_sql);
if ($filter_barangay) $flagged_stmt->bindValue(':bid', $filter_barangay);
$flagged_stmt->execute();
$flagged = $flagged_stmt->fetchAll(PDO::FETCH_ASSOC);

// Summary stats
$total_hh     = array_sum(array_column($brgy_counts, 'total_hh'));
$total_missing = array_sum(array_column($brgy_counts, 'missing_gps'));
$total_invalid = array_sum(array_column($brgy_counts, 'invalid_gps'));
$total_valid   = array_sum(array_column($brgy_counts, 'valid_gps'));
$pct_valid     = $total_hh > 0 ? round($total_valid / $total_hh * 100, 1) : 0;

$pageTitle = 'GPS Quality Report';
require_once BASE_PATH . '/includes/header.php';
require_once BASE_PATH . '/includes/sidebar.php';
?>

<div class="main-content">
  <!-- Topbar -->
  <div class="topbar">
    <div class="d-flex align-items-center gap-2">
      <i class="fas fa-map-marker-alt text-primary"></i>
      <h5 class="mb-0">GPS Data Quality Report</h5>
    </div>
    <a href="<?= BASE_URL ?>modules/households/household_management.php" class="btn btn-sm btn-outline-primary">
      <i class="fas fa-arrow-left me-1"></i> Back to Households
    </a>
  </div>

  <div class="p-4">

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
      <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center p-3">
          <div class="fs-2 fw-bold text-primary"><?= number_format($total_hh) ?></div>
          <div class="text-muted small">Total Households</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center p-3">
          <div class="fs-2 fw-bold text-success"><?= number_format($total_valid) ?></div>
          <div class="text-muted small">Valid GPS <span class="badge bg-success"><?= $pct_valid ?>%</span></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center p-3">
          <div class="fs-2 fw-bold text-warning"><?= number_format($total_missing) ?></div>
          <div class="text-muted small">Missing GPS</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center p-3">
          <div class="fs-2 fw-bold text-danger"><?= number_format($total_invalid) ?></div>
          <div class="text-muted small">Invalid GPS</div>
        </div>
      </div>
    </div>

    <div class="row g-4">

      <!-- Per-Barangay Summary -->
      <div class="col-md-5">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white fw-semibold">Per-Barangay GPS Status</div>
          <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th>Barangay</th>
                  <th class="text-center">Total HH</th>
                  <th class="text-center">Missing</th>
                  <th class="text-center">Invalid</th>
                  <th class="text-center">% Valid</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($brgy_counts as $bc):
                $issues = (int)$bc['missing_gps'] + (int)$bc['invalid_gps'];
                $pct = $bc['total_hh'] > 0 ? round($bc['valid_gps'] / $bc['total_hh'] * 100) : 0;
                $row_class = $issues > 0 ? 'table-warning' : '';
              ?>
                <tr class="<?= $row_class ?>">
                  <td><a href="?barangay_id=<?= $bc['id'] ?>"><?= htmlspecialchars($bc['name']) ?></a></td>
                  <td class="text-center"><?= $bc['total_hh'] ?></td>
                  <td class="text-center <?= $bc['missing_gps'] > 0 ? 'text-danger fw-bold' : '' ?>"><?= $bc['missing_gps'] ?></td>
                  <td class="text-center <?= $bc['invalid_gps'] > 0 ? 'text-warning fw-bold' : '' ?>"><?= $bc['invalid_gps'] ?></td>
                  <td class="text-center">
                    <div class="progress" style="height:6px;">
                      <div class="progress-bar bg-<?= $pct >= 90 ? 'success' : ($pct >= 50 ? 'warning' : 'danger') ?>"
                           style="width:<?= $pct ?>%"></div>
                    </div>
                    <small><?= $pct ?>%</small>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Flagged Households -->
      <div class="col-md-7">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white d-flex align-items-center justify-content-between">
            <span class="fw-semibold">
              Flagged Households
              <span class="badge bg-danger ms-1"><?= count($flagged) ?></span>
            </span>
            <form class="d-flex gap-2" method="get">
              <select name="barangay_id" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                <option value="">All Barangays</option>
                <?php foreach ($barangays_list as $b): ?>
                  <option value="<?= $b['id'] ?>" <?= $filter_barangay == $b['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($b['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </form>
          </div>
          <div class="card-body p-0" style="max-height:500px;overflow-y:auto;">
            <?php if (empty($flagged)): ?>
              <div class="p-4 text-center text-success">
                <i class="fas fa-check-circle fa-2x mb-2"></i>
                <p class="mb-0">All households have valid GPS coordinates<?= $filter_barangay ? ' in this barangay' : '' ?>.</p>
              </div>
            <?php else: ?>
            <table class="table table-sm table-hover mb-0">
              <thead class="table-light sticky-top">
                <tr>
                  <th>Household Head</th>
                  <th>Barangay</th>
                  <th>Issue</th>
                  <th>Coordinates</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($flagged as $hh): ?>
                <tr>
                  <td><?= htmlspecialchars($hh['household_head']) ?></td>
                  <td><?= htmlspecialchars($hh['barangay_name']) ?></td>
                  <td>
                    <?php if ($hh['gps_issue'] === 'Missing'): ?>
                      <span class="badge bg-danger">Missing</span>
                    <?php else: ?>
                      <span class="badge bg-warning text-dark">Out of Range</span>
                    <?php endif; ?>
                  </td>
                  <td style="font-size:.8rem;">
                    <?php if ($hh['latitude'] && $hh['longitude']): ?>
                      <code><?= $hh['latitude'] ?>, <?= $hh['longitude'] ?></code>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <a href="<?= BASE_URL ?>modules/households/household_management.php?edit=<?= $hh['id'] ?>"
                       class="btn btn-xs btn-warning" style="font-size:.75rem;padding:2px 8px;">
                      <i class="fas fa-edit"></i> Fix GPS
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div><!-- row -->
  </div><!-- p-4 -->
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
