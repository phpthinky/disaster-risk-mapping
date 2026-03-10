<?php
/**
 * modules/population/population_history.php
 * Admin and Division Chief only: Population History — timeline of archive changes per barangay.
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
// CSV Export — must come BEFORE any HTML output
// =========================================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $expBarangayId = isset($_GET['barangay_id']) && $_GET['barangay_id'] !== '' ? (int)$_GET['barangay_id'] : null;
    $expDateFrom   = isset($_GET['date_from'])   && $_GET['date_from']   !== '' ? $_GET['date_from']   : null;
    $expDateTo     = isset($_GET['date_to'])     && $_GET['date_to']     !== '' ? $_GET['date_to']     : null;

    $expWhere  = ['1=1'];
    $expParams = [];

    if ($expBarangayId !== null) {
        $expWhere[]  = 'a.barangay_id = :barangay_id';
        $expParams[':barangay_id'] = $expBarangayId;
    }
    if ($expDateFrom !== null) {
        $expWhere[]  = 'DATE(a.archived_at) >= :date_from';
        $expParams[':date_from'] = $expDateFrom;
    }
    if ($expDateTo !== null) {
        $expWhere[]  = 'DATE(a.archived_at) <= :date_to';
        $expParams[':date_to'] = $expDateTo;
    }

    $expSql = 'SELECT b.name AS barangay_name,
                      a.data_date,
                      a.total_population,
                      a.households,
                      a.elderly_count,
                      a.children_count,
                      a.pwd_count,
                      a.ips_count,
                      a.solo_parent_count,
                      a.widow_count,
                      a.change_type,
                      a.archived_by,
                      a.archived_at
                 FROM population_data_archive a
                 JOIN barangays b ON b.id = a.barangay_id
                WHERE ' . implode(' AND ', $expWhere) . '
                ORDER BY a.archived_at DESC';

    $expStmt = $pdo->prepare($expSql);
    $expStmt->execute($expParams);
    $expRows = $expStmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="population_history_' . date('Ymd_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // BOM for Excel UTF-8 compatibility
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, [
        'Barangay', 'Data Date', 'Total Population', 'Households',
        'Elderly', 'Children', 'PWD', 'IPs', 'Solo Parent', 'Widow',
        'Change Type', 'Archived By', 'Archived At'
    ]);
    foreach ($expRows as $row) {
        fputcsv($out, [
            $row['barangay_name'],
            $row['data_date'],
            $row['total_population'],
            $row['households'],
            $row['elderly_count'],
            $row['children_count'],
            $row['pwd_count'],
            $row['ips_count'],
            $row['solo_parent_count'],
            $row['widow_count'],
            $row['change_type'],
            $row['archived_by'],
            $row['archived_at'],
        ]);
    }
    fclose($out);
    exit;
}

// =========================================================
// Filter inputs
// =========================================================
$filterBarangayId = isset($_GET['barangay_id']) && $_GET['barangay_id'] !== '' ? (int)$_GET['barangay_id'] : null;
$filterDateFrom   = isset($_GET['date_from'])   && $_GET['date_from']   !== '' ? trim($_GET['date_from'])   : null;
$filterDateTo     = isset($_GET['date_to'])     && $_GET['date_to']     !== '' ? trim($_GET['date_to'])     : null;

// Validate date strings
if ($filterDateFrom !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDateFrom)) $filterDateFrom = null;
if ($filterDateTo   !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDateTo))   $filterDateTo   = null;

// =========================================================
// Fetch barangay list for the dropdown
// =========================================================
$barangaysStmt = $pdo->query('SELECT id, name FROM barangays ORDER BY name ASC');
$barangayList  = $barangaysStmt->fetchAll(PDO::FETCH_ASSOC);

// =========================================================
// Fetch archive records with filters
// =========================================================
$where  = ['1=1'];
$params = [];

if ($filterBarangayId !== null) {
    $where[]  = 'a.barangay_id = :barangay_id';
    $params[':barangay_id'] = $filterBarangayId;
}
if ($filterDateFrom !== null) {
    $where[]  = 'DATE(a.archived_at) >= :date_from';
    $params[':date_from'] = $filterDateFrom;
}
if ($filterDateTo !== null) {
    $where[]  = 'DATE(a.archived_at) <= :date_to';
    $params[':date_to'] = $filterDateTo;
}

$sql = 'SELECT a.id,
               a.barangay_id,
               b.name  AS barangay_name,
               a.data_date,
               a.total_population,
               a.households,
               a.elderly_count,
               a.children_count,
               a.pwd_count,
               a.ips_count,
               a.solo_parent_count,
               a.widow_count,
               a.change_type,
               a.archived_by,
               a.archived_at
          FROM population_data_archive a
          JOIN barangays b ON b.id = a.barangay_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY a.archived_at DESC';

$stmt    = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =========================================================
// Compute difference column: +/- vs previous record per barangay
// Records are DESC by archived_at; we need the next-older record per barangay.
// Build per-barangay map keyed by position so we can look ahead.
// =========================================================
// Group indices by barangay_id
$barangayIndices = []; // barangay_id => [index, ...]  (positions in $records, oldest last = highest index)
foreach ($records as $idx => $row) {
    $barangayIndices[$row['barangay_id']][] = $idx;
}
// For each record, the "previous" is the next index in the same barangay group (since sorted DESC)
$prevPopulation = []; // index => previous total_population or null
foreach ($barangayIndices as $bid => $indices) {
    foreach ($indices as $pos => $idx) {
        $prevIdx = $indices[$pos + 1] ?? null;
        $prevPopulation[$idx] = $prevIdx !== null ? (int)$records[$prevIdx]['total_population'] : null;
    }
}

// =========================================================
// HTML
// =========================================================
$pageTitle = 'Population History';
$role      = $_SESSION['role'] ?? '';

require_once BASE_PATH . '/includes/header.php';
require_once BASE_PATH . '/includes/sidebar.php';
?>

<!-- Main Content -->
<div class="main-content">

  <!-- Topbar -->
  <div class="topbar">
    <div class="d-flex align-items-center gap-2">
      <i class="fas fa-chart-line text-primary"></i>
      <strong>Population History</strong>
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
          <!-- Barangay Dropdown -->
          <div class="col-12 col-md-4">
            <label for="barangay_id" class="form-label fw-semibold">
              <i class="fas fa-map-marker-alt text-primary me-1"></i>Barangay
            </label>
            <select name="barangay_id" id="barangay_id" class="form-select">
              <option value="">All Barangays</option>
              <?php foreach ($barangayList as $b): ?>
                <option value="<?= (int)$b['id'] ?>"
                  <?= $filterBarangayId === (int)$b['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($b['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Date From -->
          <div class="col-12 col-md-3">
            <label for="date_from" class="form-label fw-semibold">
              <i class="fas fa-calendar-alt text-primary me-1"></i>Date From
            </label>
            <input type="date" name="date_from" id="date_from" class="form-control"
                   value="<?= htmlspecialchars($filterDateFrom ?? '') ?>">
          </div>

          <!-- Date To -->
          <div class="col-12 col-md-3">
            <label for="date_to" class="form-label fw-semibold">
              <i class="fas fa-calendar-alt text-primary me-1"></i>Date To
            </label>
            <input type="date" name="date_to" id="date_to" class="form-control"
                   value="<?= htmlspecialchars($filterDateTo ?? '') ?>">
          </div>

          <!-- Actions -->
          <div class="col-12 col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1">
              <i class="fas fa-filter me-1"></i>Filter
            </button>
            <a href="<?= BASE_URL ?>modules/population/population_history.php"
               class="btn btn-outline-secondary" title="Clear filters">
              <i class="fas fa-times"></i>
            </a>
          </div>
        </form>
      </div>
    </div>

    <!-- Toolbar: count + export -->
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
      <div class="text-muted small">
        Showing <strong><?= count($records) ?></strong> archive record(s)
        <?php if ($filterBarangayId !== null): ?>
          for <strong><?php
            foreach ($barangayList as $b) {
                if ((int)$b['id'] === $filterBarangayId) { echo htmlspecialchars($b['name']); break; }
            }
          ?></strong>
        <?php endif; ?>
      </div>
      <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>"
         class="btn btn-success btn-sm">
        <i class="fas fa-file-csv me-1"></i>Export CSV
      </a>
    </div>

    <!-- History Table -->
    <div class="card border-0 shadow-sm">
      <div class="card-body p-0">
        <?php if (empty($records)): ?>
          <div class="text-center py-5 text-muted">
            <i class="fas fa-history fa-3x mb-3 opacity-25"></i>
            <p class="mb-0 fw-semibold">No population history yet.</p>
            <p class="small">Population history is recorded automatically whenever household data changes.</p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0" id="historyTable">
              <thead class="table-dark">
                <tr>
                  <th class="ps-3">#</th>
                  <th>Barangay</th>
                  <th>Data Date</th>
                  <th class="text-end">Total Population</th>
                  <th class="text-end">Households</th>
                  <th class="text-end">Elderly</th>
                  <th class="text-end">Children</th>
                  <th class="text-end">PWD</th>
                  <th class="text-end">IPs</th>
                  <th class="text-center">Change Type</th>
                  <th class="text-end">Difference</th>
                  <th>Archived By</th>
                  <th>Archived At</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($records as $idx => $row):
                    $currentPop  = (int)$row['total_population'];
                    $previousPop = $prevPopulation[$idx];

                    $diff     = null;
                    $rowClass = '';
                    $diffBadge = '<span class="text-muted small">—</span>';

                    if ($previousPop !== null) {
                        $diff = $currentPop - $previousPop;
                        if ($diff > 0) {
                            $rowClass  = 'table-success';
                            $diffBadge = '<span class="badge bg-success">+' . number_format($diff) . '</span>';
                        } elseif ($diff < 0) {
                            $rowClass  = 'table-danger';
                            $diffBadge = '<span class="badge bg-danger">' . number_format($diff) . '</span>';
                        } else {
                            $diffBadge = '<span class="badge bg-secondary">No change</span>';
                        }
                    }

                    // Change type badge
                    $changeTypeLower = strtolower($row['change_type'] ?? '');
                    $ctClass = match(true) {
                        str_contains($changeTypeLower, 'insert') || str_contains($changeTypeLower, 'add') => 'bg-primary',
                        str_contains($changeTypeLower, 'update') || str_contains($changeTypeLower, 'edit') => 'bg-warning text-dark',
                        str_contains($changeTypeLower, 'delete') || str_contains($changeTypeLower, 'remove') => 'bg-danger',
                        default => 'bg-secondary',
                    };
                ?>
                <tr class="<?= $rowClass ?>">
                  <td class="ps-3 text-muted small"><?= $idx + 1 ?></td>
                  <td class="fw-semibold"><?= htmlspecialchars($row['barangay_name']) ?></td>
                  <td><?= htmlspecialchars($row['data_date'] ?? '—') ?></td>
                  <td class="text-end fw-bold"><?= number_format($currentPop) ?></td>
                  <td class="text-end"><?= number_format((int)$row['households']) ?></td>
                  <td class="text-end"><?= number_format((int)$row['elderly_count']) ?></td>
                  <td class="text-end"><?= number_format((int)$row['children_count']) ?></td>
                  <td class="text-end"><?= number_format((int)$row['pwd_count']) ?></td>
                  <td class="text-end"><?= number_format((int)$row['ips_count']) ?></td>
                  <td class="text-center">
                    <span class="badge <?= $ctClass ?>">
                      <?= htmlspecialchars(ucfirst($row['change_type'] ?? 'unknown')) ?>
                    </span>
                  </td>
                  <td class="text-end"><?= $diffBadge ?></td>
                  <td><?= htmlspecialchars($row['archived_by'] ?? '—') ?></td>
                  <td class="text-nowrap small text-muted">
                    <?= htmlspecialchars($row['archived_at'] ?? '—') ?>
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
    <?php if (!empty($records)): ?>
    <div class="d-flex gap-3 mt-3 flex-wrap">
      <span class="small">
        <span class="badge bg-success me-1">&nbsp;</span>Population increased vs. previous record
      </span>
      <span class="small">
        <span class="badge bg-danger me-1">&nbsp;</span>Population decreased vs. previous record
      </span>
      <span class="small text-muted">
        Rows without highlight: first recorded entry for that barangay or no change.
      </span>
    </div>
    <?php endif; ?>

  </div><!-- /.p-4 -->
</div><!-- /.main-content -->

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
