<?php
/**
 * modules/population/population_comparison.php
 * Admin and Division Chief only: Population Comparison —
 * current barangay population vs. latest archived population.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/sync.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// --- Access guard: admin and division_chief only ---
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'division_chief'], true)) {
    http_response_code(403);
    die('403 Forbidden: Access restricted to administrators and division chiefs.');
}

// =========================================================
// Filter inputs
// =========================================================
$filterDateFrom   = isset($_GET['date_from'])    && $_GET['date_from']    !== '' ? trim($_GET['date_from'])    : null;
$filterDateTo     = isset($_GET['date_to'])      && $_GET['date_to']      !== '' ? trim($_GET['date_to'])      : null;
$filterHazardType = isset($_GET['hazard_type'])  && $_GET['hazard_type']  !== '' ? trim($_GET['hazard_type'])  : null;

// Validate dates
if ($filterDateFrom !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDateFrom)) $filterDateFrom = null;
if ($filterDateTo   !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDateTo))   $filterDateTo   = null;

// Allowed hazard type values
$allowedHazardTypes = ['High Susceptible', 'Moderate Susceptible', 'Low Susceptible'];
if ($filterHazardType !== null && !in_array($filterHazardType, $allowedHazardTypes, true)) {
    $filterHazardType = null;
}

// =========================================================
// Build comparison query
//
// For each barangay:
//   current_population  = barangays.population
//   previous_population = latest total_population from population_data_archive
//                         (optionally filtered by archived_at date range)
//   hazard_risk         = max risk_level from hazard_zones
// =========================================================

// Sub-query: latest archive per barangay within optional date range
$archiveDateWhere = '1=1';
$archiveParams    = [];
if ($filterDateFrom !== null) {
    $archiveDateWhere .= ' AND DATE(pda.archived_at) >= :date_from';
    $archiveParams[':date_from'] = $filterDateFrom;
}
if ($filterDateTo !== null) {
    $archiveDateWhere .= ' AND DATE(pda.archived_at) <= :date_to';
    $archiveParams[':date_to'] = $filterDateTo;
}

// Hazard join/filter
$hazardJoin  = "LEFT JOIN (
                    SELECT barangay_id,
                           MAX(CASE risk_level
                               WHEN 'High Susceptible'     THEN 3
                               WHEN 'Moderate Susceptible' THEN 2
                               WHEN 'Low Susceptible'      THEN 1
                               ELSE 0 END) AS risk_score,
                           MAX(risk_level) AS worst_risk_level,
                           GROUP_CONCAT(DISTINCT risk_level ORDER BY risk_level SEPARATOR ', ') AS all_risk_levels
                    FROM hazard_zones
                    GROUP BY barangay_id
                ) hz ON hz.barangay_id = b.id";

$hazardWhere  = '1=1';
$hazardParams = [];
if ($filterHazardType !== null) {
    $hazardWhere          = "hz.all_risk_levels LIKE :hazard_type";
    $hazardParams[':hazard_type'] = '%' . $filterHazardType . '%';
}

$sql = "SELECT b.id                                    AS barangay_id,
               b.name                                  AS barangay_name,
               COALESCE(b.population, 0)               AS current_population,
               COALESCE(b.household_count, 0)          AS current_households,
               prev.prev_population,
               prev.prev_archived_at,
               hz.risk_score,
               hz.worst_risk_level,
               hz.all_risk_levels
          FROM barangays b
          {$hazardJoin}
          LEFT JOIN (
              SELECT pda.barangay_id,
                     pda.total_population  AS prev_population,
                     pda.archived_at       AS prev_archived_at
                FROM population_data_archive pda
               INNER JOIN (
                   SELECT barangay_id, MAX(archived_at) AS max_archived_at
                     FROM population_data_archive
                    WHERE {$archiveDateWhere}
                    GROUP BY barangay_id
               ) latest ON latest.barangay_id = pda.barangay_id
                        AND pda.archived_at   = latest.max_archived_at
                        AND {$archiveDateWhere}
          ) prev ON prev.barangay_id = b.id
         WHERE {$hazardWhere}
         ORDER BY b.name ASC";

$allParams = array_merge($archiveParams, $archiveParams, $hazardParams);
// Note: archiveParams is bound twice (inner + outer WHERE of the sub-query)
// Use named params carefully — PDO allows duplicate named params in different clauses.
// To avoid collision we rebuild with suffixed keys.
$innerParams = [];
$outerParams = [];
foreach ($archiveParams as $key => $val) {
    $innerKey           = $key . '_inner';
    $outerKey           = $key . '_outer';
    $innerParams[$innerKey] = $val;
    $outerParams[$outerKey] = $val;
    // Replace in SQL
    $sql = str_replace(
        $key . '_inner',
        $key . '_inner',
        $sql
    );
}
// Rebuild the query with distinct param names for inner vs. outer archive date filter
$sqlFinal = "SELECT b.id                                    AS barangay_id,
               b.name                                  AS barangay_name,
               COALESCE(b.population, 0)               AS current_population,
               COALESCE(b.household_count, 0)          AS current_households,
               prev.prev_population,
               prev.prev_archived_at,
               hz.risk_score,
               hz.worst_risk_level,
               hz.all_risk_levels
          FROM barangays b
          {$hazardJoin}
          LEFT JOIN (
              SELECT pda.barangay_id,
                     pda.total_population  AS prev_population,
                     pda.archived_at       AS prev_archived_at
                FROM population_data_archive pda
               INNER JOIN (
                   SELECT barangay_id, MAX(archived_at) AS max_archived_at
                     FROM population_data_archive
                    WHERE 1=1";

$finalParams = $hazardParams;

if ($filterDateFrom !== null) {
    $sqlFinal .= ' AND DATE(archived_at) >= :df1';
    $finalParams[':df1'] = $filterDateFrom;
}
if ($filterDateTo !== null) {
    $sqlFinal .= ' AND DATE(archived_at) <= :dt1';
    $finalParams[':dt1'] = $filterDateTo;
}

$sqlFinal .= "        GROUP BY barangay_id
               ) latest ON latest.barangay_id = pda.barangay_id
                        AND pda.archived_at   = latest.max_archived_at
              WHERE 1=1";

if ($filterDateFrom !== null) {
    $sqlFinal .= ' AND DATE(pda.archived_at) >= :df2';
    $finalParams[':df2'] = $filterDateFrom;
}
if ($filterDateTo !== null) {
    $sqlFinal .= ' AND DATE(pda.archived_at) <= :dt2';
    $finalParams[':dt2'] = $filterDateTo;
}

$sqlFinal .= "  ) prev ON prev.barangay_id = b.id
         WHERE {$hazardWhere}
         ORDER BY b.name ASC";

$stmt = $pdo->prepare($sqlFinal);
$stmt->execute($finalParams);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =========================================================
// Fetch sparkline data: last 3 archive records per barangay
// =========================================================
$sparkSql = "SELECT barangay_id, total_population, archived_at
               FROM population_data_archive
              ORDER BY barangay_id ASC, archived_at DESC";
$sparkStmt = $pdo->query($sparkSql);
$sparkRaw  = $sparkStmt->fetchAll(PDO::FETCH_ASSOC);

// Group by barangay, keep latest 3
$sparkData = []; // barangay_id => [pop (newest first), ...]
foreach ($sparkRaw as $sr) {
    $bid = (int)$sr['barangay_id'];
    if (!isset($sparkData[$bid])) $sparkData[$bid] = [];
    if (count($sparkData[$bid]) < 3) {
        $sparkData[$bid][] = (int)$sr['total_population'];
    }
}

// Build arrow string: compare consecutive pairs (newest at index 0)
function buildSparkline(array $pops): string {
    // $pops[0] = newest, $pops[1] = older, $pops[2] = oldest
    $arrows = [];
    for ($i = 0; $i < count($pops) - 1; $i++) {
        if ($pops[$i] > $pops[$i + 1]) {
            $arrows[] = '<span class="text-success fw-bold">&#8593;</span>'; // up arrow
        } elseif ($pops[$i] < $pops[$i + 1]) {
            $arrows[] = '<span class="text-danger fw-bold">&#8595;</span>';  // down arrow
        } else {
            $arrows[] = '<span class="text-secondary">&#8594;</span>';       // right arrow (flat)
        }
    }
    // Reverse so oldest change is displayed first (left to right = past to present)
    return implode(' ', array_reverse($arrows));
}

// =========================================================
// Compute summary counters
// =========================================================
$countIncrease     = 0;
$countDecrease     = 0;
$countNoChange     = 0;
$countRiskIncrease = 0;

foreach ($rows as $row) {
    $current  = (int)$row['current_population'];
    $previous = $row['prev_population'] !== null ? (int)$row['prev_population'] : null;

    if ($previous === null) {
        $countNoChange++;
        continue;
    }

    $diff         = $current - $previous;
    $riskScore    = (int)($row['risk_score'] ?? 0);
    $isHighRisk   = $riskScore >= 2; // High Susceptible=3, Moderate=2

    if ($diff > 0) {
        $countIncrease++;
        if ($isHighRisk) $countRiskIncrease++;
    } elseif ($diff < 0) {
        $countDecrease++;
    } else {
        $countNoChange++;
    }
}

// =========================================================
// CSV Export — after data is fetched
// =========================================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="population_comparison_' . date('Ymd_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, [
        'Barangay',
        'Previous Population',
        'Current Population',
        'Difference',
        'Current Households',
        'Hazard Levels',
        'Risk Warning',
        'Trend (last 3 archives)',
    ]);
    foreach ($rows as $row) {
        $current  = (int)$row['current_population'];
        $previous = $row['prev_population'] !== null ? (int)$row['prev_population'] : null;
        $diff     = $previous !== null ? $current - $previous : null;

        $diffStr     = $diff === null ? 'N/A' : ($diff > 0 ? '+' . $diff : ($diff < 0 ? (string)$diff : 'No change'));
        $riskScore   = (int)($row['risk_score'] ?? 0);
        $isHighRisk  = $riskScore >= 2;
        $riskWarning = ($diff !== null && $diff > 0 && $isHighRisk) ? 'HIGH RISK ZONE' : '';

        $bid      = (int)$row['barangay_id'];
        $pops     = $sparkData[$bid] ?? [];
        $trendStr = '';
        if (count($pops) >= 2) {
            $arrows = [];
            for ($i = 0; $i < count($pops) - 1; $i++) {
                if ($pops[$i] > $pops[$i + 1])      $arrows[] = '↑';
                elseif ($pops[$i] < $pops[$i + 1])  $arrows[] = '↓';
                else                                  $arrows[] = '→';
            }
            $trendStr = implode(' ', array_reverse($arrows));
        }

        fputcsv($out, [
            $row['barangay_name'],
            $previous !== null ? $previous : 'No archive',
            $current,
            $diffStr,
            $row['current_households'],
            $row['all_risk_levels'] ?? 'None',
            $riskWarning,
            $trendStr,
        ]);
    }
    fclose($out);
    exit;
}

// =========================================================
// HTML
// =========================================================
$pageTitle = 'Population Comparison';
$role      = $_SESSION['role'] ?? '';

require_once BASE_PATH . '/includes/header.php';
require_once BASE_PATH . '/includes/sidebar.php';
?>

<!-- Main Content -->
<div class="main-content">

  <!-- Topbar -->
  <div class="topbar">
    <div class="d-flex align-items-center gap-2">
      <i class="fas fa-balance-scale text-primary"></i>
      <strong>Population Comparison</strong>
    </div>
    <div class="text-muted" style="font-size:.8rem;">
      <?= htmlspecialchars($_SESSION['username'] ?? '') ?> &mdash;
      <?= htmlspecialchars(ucwords(str_replace('_', ' ', $role))) ?>
    </div>
  </div>

  <!-- Page Body -->
  <div class="p-4">

    <!-- Filter Bar -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <form method="GET" action="" class="row g-3 align-items-end" id="filterForm">
          <div class="col-12 col-md-3">
            <label for="date_from" class="form-label fw-semibold">
              <i class="fas fa-calendar-alt text-primary me-1"></i>Archive From
            </label>
            <input type="date" name="date_from" id="date_from" class="form-control"
                   value="<?= htmlspecialchars($filterDateFrom ?? '') ?>">
          </div>

          <div class="col-12 col-md-3">
            <label for="date_to" class="form-label fw-semibold">
              <i class="fas fa-calendar-alt text-primary me-1"></i>Archive To
            </label>
            <input type="date" name="date_to" id="date_to" class="form-control"
                   value="<?= htmlspecialchars($filterDateTo ?? '') ?>">
          </div>

          <div class="col-12 col-md-3">
            <label for="hazard_type" class="form-label fw-semibold">
              <i class="fas fa-exclamation-triangle text-warning me-1"></i>Hazard Level
            </label>
            <select name="hazard_type" id="hazard_type" class="form-select">
              <option value="">All Hazard Levels</option>
              <option value="High Susceptible"
                <?= $filterHazardType === 'High Susceptible' ? 'selected' : '' ?>>High Susceptible</option>
              <option value="Moderate Susceptible"
                <?= $filterHazardType === 'Moderate Susceptible' ? 'selected' : '' ?>>Moderate Susceptible</option>
              <option value="Low Susceptible"
                <?= $filterHazardType === 'Low Susceptible' ? 'selected' : '' ?>>Low Susceptible</option>
            </select>
          </div>

          <div class="col-12 col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1">
              <i class="fas fa-filter me-1"></i>Apply
            </button>
            <a href="<?= BASE_URL ?>modules/population/population_comparison.php"
               class="btn btn-outline-secondary" title="Clear filters">
              <i class="fas fa-times"></i>
            </a>
          </div>
        </form>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100 border-start border-success border-4">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-circle bg-success bg-opacity-10 p-3">
              <i class="fas fa-arrow-trend-up text-success fa-lg"></i>
            </div>
            <div>
              <div class="fs-4 fw-bold text-success"><?= $countIncrease ?></div>
              <div class="text-muted small">Barangays with Increase</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100 border-start border-danger border-4">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-circle bg-danger bg-opacity-10 p-3">
              <i class="fas fa-arrow-trend-down text-danger fa-lg"></i>
            </div>
            <div>
              <div class="fs-4 fw-bold text-danger"><?= $countDecrease ?></div>
              <div class="text-muted small">Barangays with Decrease</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100 border-start border-secondary border-4">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-circle bg-secondary bg-opacity-10 p-3">
              <i class="fas fa-equals text-secondary fa-lg"></i>
            </div>
            <div>
              <div class="fs-4 fw-bold text-secondary"><?= $countNoChange ?></div>
              <div class="text-muted small">No Change / No Archive</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100 border-start border-warning border-4">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-circle bg-warning bg-opacity-10 p-3">
              <i class="fas fa-triangle-exclamation text-warning fa-lg"></i>
            </div>
            <div>
              <div class="fs-4 fw-bold text-warning"><?= $countRiskIncrease ?></div>
              <div class="text-muted small">Risk Zones with Increase</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Hazard Warning Info Box -->
    <?php if ($countRiskIncrease > 0): ?>
    <div class="alert alert-warning d-flex align-items-start gap-3 mb-4" role="alert">
      <i class="fas fa-triangle-exclamation fa-lg mt-1 flex-shrink-0"></i>
      <div>
        <strong>Priority Attention Required</strong><br>
        Barangays marked with <span class="badge bg-danger"><i class="fas fa-triangle-exclamation me-1"></i>High Risk Zone</span>
        have <strong>increased population</strong> AND are located in
        <strong>High or Moderate Susceptible hazard zones</strong>.
        These require priority attention for disaster preparedness and resource allocation.
      </div>
    </div>
    <?php else: ?>
    <div class="alert alert-info d-flex align-items-start gap-3 mb-4" role="alert">
      <i class="fas fa-circle-info fa-lg mt-1 flex-shrink-0"></i>
      <div>
        <strong>Hazard Warning Explanation</strong><br>
        Barangays marked with <span class="badge bg-danger"><i class="fas fa-triangle-exclamation me-1"></i>High Risk Zone</span>
        have increased population AND are located in High or Moderate Susceptible hazard zones.
        These require priority attention.
      </div>
    </div>
    <?php endif; ?>

    <!-- Toolbar -->
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
      <div class="text-muted small">
        Showing <strong><?= count($rows) ?></strong> barangay(s)
        <?php if ($filterDateFrom || $filterDateTo): ?>
          &mdash; archive range:
          <?php if ($filterDateFrom): ?>from <strong><?= htmlspecialchars($filterDateFrom) ?></strong><?php endif; ?>
          <?php if ($filterDateTo): ?>to <strong><?= htmlspecialchars($filterDateTo) ?></strong><?php endif; ?>
        <?php endif; ?>
      </div>
      <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>"
         class="btn btn-success btn-sm">
        <i class="fas fa-file-csv me-1"></i>Export CSV
      </a>
    </div>

    <!-- Comparison Table -->
    <div class="card border-0 shadow-sm">
      <div class="card-body p-0">
        <?php if (empty($rows)): ?>
          <div class="text-center py-5 text-muted">
            <i class="fas fa-balance-scale fa-3x mb-3 opacity-25"></i>
            <p class="mb-0 fw-semibold">No barangay data found.</p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0" id="comparisonTable">
              <thead class="table-dark">
                <tr>
                  <th class="ps-3">#</th>
                  <th>Barangay</th>
                  <th class="text-end">Previous Population</th>
                  <th class="text-end">Current Population</th>
                  <th class="text-end">Difference</th>
                  <th class="text-end">Households</th>
                  <th class="text-center">Hazard Warning</th>
                  <th class="text-center">Trend</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rows as $idx => $row):
                    $current   = (int)$row['current_population'];
                    $previous  = $row['prev_population'] !== null ? (int)$row['prev_population'] : null;
                    $diff      = $previous !== null ? $current - $previous : null;
                    $riskScore = (int)($row['risk_score'] ?? 0);
                    $isHighRisk = $riskScore >= 2; // Moderate (2) or High (3)

                    // Row highlight
                    $rowClass = '';
                    if ($diff !== null) {
                        if ($diff > 0)      $rowClass = 'table-success';
                        elseif ($diff < 0)  $rowClass = 'table-danger';
                    }

                    // Difference display
                    if ($diff === null) {
                        $diffHtml = '<span class="text-muted small">No archive</span>';
                    } elseif ($diff > 0) {
                        $diffHtml = '<span class="text-success fw-bold">+' . number_format($diff) . '</span>';
                    } elseif ($diff < 0) {
                        $diffHtml = '<span class="text-danger fw-bold">' . number_format($diff) . '</span>';
                    } else {
                        $diffHtml = '<span class="text-secondary">No change</span>';
                    }

                    // Hazard warning badge
                    $hazardBadge = '';
                    if ($diff !== null && $diff > 0 && $isHighRisk) {
                        $hazardBadge = '<span class="badge bg-danger">
                            <i class="fas fa-triangle-exclamation me-1"></i>High Risk Zone
                        </span>';
                    } elseif (!empty($row['all_risk_levels'])) {
                        $hazardBadge = '<span class="badge bg-secondary text-white" style="font-size:.7rem;">'
                            . htmlspecialchars($row['all_risk_levels'])
                            . '</span>';
                    } else {
                        $hazardBadge = '<span class="text-muted small">—</span>';
                    }

                    // Sparkline
                    $bid      = (int)$row['barangay_id'];
                    $pops     = $sparkData[$bid] ?? [];
                    $sparkHtml = '<span class="text-muted small">—</span>';
                    if (count($pops) >= 2) {
                        $sparkHtml = buildSparkline($pops);
                    }
                ?>
                <tr class="<?= $rowClass ?>">
                  <td class="ps-3 text-muted small"><?= $idx + 1 ?></td>
                  <td class="fw-semibold"><?= htmlspecialchars($row['barangay_name']) ?></td>
                  <td class="text-end">
                    <?php if ($previous !== null): ?>
                      <?= number_format($previous) ?>
                      <?php if ($row['prev_archived_at']): ?>
                        <div class="text-muted" style="font-size:.7rem;">
                          as of <?= htmlspecialchars(date('M d, Y', strtotime($row['prev_archived_at']))) ?>
                        </div>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="text-muted small">No archive</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end fw-bold"><?= number_format($current) ?></td>
                  <td class="text-end"><?= $diffHtml ?></td>
                  <td class="text-end"><?= number_format((int)$row['current_households']) ?></td>
                  <td class="text-center"><?= $hazardBadge ?></td>
                  <td class="text-center" style="font-size:1.1rem; letter-spacing:2px;">
                    <?= $sparkHtml ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Legend -->
    <?php if (!empty($rows)): ?>
    <div class="d-flex gap-4 mt-3 flex-wrap align-items-center">
      <span class="small"><span class="d-inline-block bg-success bg-opacity-25 border border-success rounded px-2">&nbsp;&nbsp;&nbsp;</span> Population increased</span>
      <span class="small"><span class="d-inline-block bg-danger bg-opacity-25 border border-danger rounded px-2">&nbsp;&nbsp;&nbsp;</span> Population decreased</span>
      <span class="small text-muted">Trend arrows show direction of last 2–3 archived changes (oldest → newest).</span>
    </div>
    <?php endif; ?>

  </div><!-- /.p-4 -->
</div><!-- /.main-content -->

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
