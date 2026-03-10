<?php
/**
 * modules/incidents/incident_management.php
 * Incident Reports — admin & barangay_staff access
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/sync.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// ── Auth ─────────────────────────────────────────────────────────────────────
$role    = $_SESSION['role']    ?? '';
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id || !in_array($role, ['admin', 'barangay_staff'])) {
    if (isset($_GET['action']) || isset($_POST['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

// ── AJAX handlers ─────────────────────────────────────────────────────────────
$action = $_REQUEST['action'] ?? '';

if ($action) {
    header('Content-Type: application/json');

    // ── 1. GET get_incidents ──────────────────────────────────────────────────
    if ($action === 'get_incidents' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $where  = [];
        $params = [];

        $status_filter = trim($_GET['status'] ?? '');
        if ($status_filter && in_array($status_filter, ['ongoing', 'resolved', 'monitoring'])) {
            $where[]  = 'ir.status = ?';
            $params[] = $status_filter;
        }

        $hazard_filter = (int)($_GET['hazard_type_id'] ?? 0);
        if ($hazard_filter > 0) {
            $where[]  = 'ir.hazard_type_id = ?';
            $params[] = $hazard_filter;
        }

        $sql = "
            SELECT ir.id, ir.title, ir.incident_date, ir.status,
                   ir.total_affected_population,
                   ht.name AS hazard_type_name,
                   ht.color AS hazard_type_color
            FROM incident_reports ir
            LEFT JOIN hazard_types ht ON ht.id = ir.hazard_type_id
        ";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY ir.incident_date DESC, ir.created_at DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $incidents]);
        exit;
    }

    // ── 2. GET get_incident ───────────────────────────────────────────────────
    if ($action === 'get_incident' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'error' => 'Invalid ID']); exit; }

        $stmt = $pdo->prepare("
            SELECT ir.*, ht.name AS hazard_type_name, ht.color AS hazard_type_color
            FROM incident_reports ir
            LEFT JOIN hazard_types ht ON ht.id = ir.hazard_type_id
            WHERE ir.id = ?
        ");
        $stmt->execute([$id]);
        $incident = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$incident) { echo json_encode(['success' => false, 'error' => 'Not found']); exit; }

        $aa = $pdo->prepare("
            SELECT aa.*, b.name AS barangay_name
            FROM affected_areas aa
            LEFT JOIN barangays b ON b.id = aa.barangay_id
            WHERE aa.incident_id = ?
            ORDER BY aa.affected_population DESC
        ");
        $aa->execute([$id]);
        $areas = $aa->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'incident' => $incident, 'affected_areas' => $areas]);
        exit;
    }

    // ── 3. POST save_incident ─────────────────────────────────────────────────
    if ($action === 'save_incident' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $title          = trim($_POST['title'] ?? '');
        $hazard_type_id = (int)($_POST['hazard_type_id'] ?? 0);
        $incident_date  = trim($_POST['incident_date'] ?? '');
        $status         = trim($_POST['status'] ?? 'ongoing');
        $description    = trim($_POST['description'] ?? '');
        $polygon_raw    = trim($_POST['polygon_geojson'] ?? '');

        if (!$title || !$hazard_type_id || !$incident_date || !$polygon_raw) {
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            exit;
        }
        if (!in_array($status, ['ongoing', 'resolved', 'monitoring'])) {
            $status = 'ongoing';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $incident_date)) {
            echo json_encode(['success' => false, 'error' => 'Invalid date format']);
            exit;
        }

        // Parse polygon GeoJSON
        $geojson = json_decode($polygon_raw, true);
        if (!$geojson) {
            echo json_encode(['success' => false, 'error' => 'Invalid polygon GeoJSON']);
            exit;
        }

        // Normalise to geometry coordinates[0]
        $coords = null;
        if (isset($geojson['type'])) {
            if ($geojson['type'] === 'FeatureCollection') {
                $feat = $geojson['features'][0] ?? null;
                if ($feat) {
                    $coords = $feat['geometry']['coordinates'][0] ?? null;
                }
            } elseif ($geojson['type'] === 'Feature') {
                $coords = $geojson['geometry']['coordinates'][0] ?? null;
            } elseif ($geojson['type'] === 'Polygon') {
                $coords = $geojson['coordinates'][0] ?? null;
            }
        }

        if (!$coords || count($coords) < 3) {
            echo json_encode(['success' => false, 'error' => 'Polygon has insufficient coordinates']);
            exit;
        }

        // Query households with valid GPS
        $hh_stmt = $pdo->prepare("
            SELECT id, barangay_id, family_members, pwd_count, senior_count,
                   infant_count, minor_count, pregnant_count, ip_non_ip,
                   latitude, longitude
            FROM households
            WHERE latitude IS NOT NULL
              AND longitude IS NOT NULL
              AND latitude  BETWEEN ? AND ?
              AND longitude BETWEEN ? AND ?
        ");
        $hh_stmt->execute([
            GPS_LAT_MIN, GPS_LAT_MAX,
            GPS_LNG_MIN, GPS_LNG_MAX
        ]);
        $households = $hh_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Excluded count
        $excl_stmt = $pdo->prepare("
            SELECT COUNT(*) FROM households
            WHERE latitude IS NULL OR longitude IS NULL
               OR latitude  NOT BETWEEN ? AND ?
               OR longitude NOT BETWEEN ? AND ?
        ");
        $excl_stmt->execute([
            GPS_LAT_MIN, GPS_LAT_MAX,
            GPS_LNG_MIN, GPS_LNG_MAX
        ]);
        $excluded_count = (int)$excl_stmt->fetchColumn();

        // Point-in-polygon check
        $barangay_data = [];
        foreach ($households as $hh) {
            $lat = (float)$hh['latitude'];
            $lng = (float)$hh['longitude'];
            if (!point_in_polygon($lat, $lng, $coords)) continue;

            $bid = (int)$hh['barangay_id'];
            if (!isset($barangay_data[$bid])) {
                $barangay_data[$bid] = [
                    'affected_households' => 0,
                    'affected_population' => 0,
                    'affected_pwd'        => 0,
                    'affected_seniors'    => 0,
                    'affected_infants'    => 0,
                    'affected_minors'     => 0,
                    'affected_pregnant'   => 0,
                    'ip_count'            => 0,
                ];
            }
            $bd = &$barangay_data[$bid];
            $bd['affected_households']++;
            $bd['affected_population'] += (int)$hh['family_members'];
            $bd['affected_pwd']        += (int)$hh['pwd_count'];
            $bd['affected_seniors']    += (int)$hh['senior_count'];
            $bd['affected_infants']    += (int)$hh['infant_count'];
            $bd['affected_minors']     += (int)$hh['minor_count'];
            $bd['affected_pregnant']   += (int)$hh['pregnant_count'];
            if (strtoupper(trim($hh['ip_non_ip'])) === 'IP') {
                $bd['ip_count']++;
            }
            unset($bd);
        }

        // Totals
        $total_hh = 0; $total_pop = 0; $total_pwd = 0; $total_sen = 0;
        $total_inf = 0; $total_min = 0; $total_preg = 0; $total_ip = 0;
        foreach ($barangay_data as $bd) {
            $total_hh   += $bd['affected_households'];
            $total_pop  += $bd['affected_population'];
            $total_pwd  += $bd['affected_pwd'];
            $total_sen  += $bd['affected_seniors'];
            $total_inf  += $bd['affected_infants'];
            $total_min  += $bd['affected_minors'];
            $total_preg += $bd['affected_pregnant'];
            $total_ip   += $bd['ip_count'];
        }

        // Insert incident_reports
        $ins = $pdo->prepare("
            INSERT INTO incident_reports
                (title, hazard_type_id, incident_date, status, description,
                 polygon_geojson, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $ins->execute([$title, $hazard_type_id, $incident_date, $status,
                        $description, $polygon_raw, $user_id]);
        $incident_id = (int)$pdo->lastInsertId();

        // Insert affected_areas per barangay
        $ins_aa = $pdo->prepare("
            INSERT INTO affected_areas
                (incident_id, barangay_id, affected_households, affected_population,
                 affected_pwd, affected_seniors, affected_infants, affected_minors,
                 affected_pregnant, ip_count, computed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        // Fetch barangay names for response
        $b_names = [];
        if ($barangay_data) {
            $bids = implode(',', array_map('intval', array_keys($barangay_data)));
            $bn   = $pdo->query("SELECT id, name FROM barangays WHERE id IN ($bids)");
            foreach ($bn->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $b_names[$row['id']] = $row['name'];
            }
        }

        $breakdown = [];
        foreach ($barangay_data as $bid => $bd) {
            $ins_aa->execute([
                $incident_id, $bid,
                $bd['affected_households'], $bd['affected_population'],
                $bd['affected_pwd'],        $bd['affected_seniors'],
                $bd['affected_infants'],    $bd['affected_minors'],
                $bd['affected_pregnant'],   $bd['ip_count'],
            ]);
            $breakdown[] = [
                'barangay_id'   => $bid,
                'barangay_name' => $b_names[$bid] ?? "Barangay $bid",
            ] + $bd;
        }

        // Update incident totals
        $upd = $pdo->prepare("
            UPDATE incident_reports SET
                total_affected_households  = ?,
                total_affected_population  = ?,
                total_affected_pwd         = ?,
                total_affected_seniors     = ?,
                total_affected_infants     = ?,
                total_affected_minors      = ?,
                total_affected_pregnant    = ?,
                total_ip_count             = ?,
                updated_at                 = NOW()
            WHERE id = ?
        ");
        $upd->execute([
            $total_hh, $total_pop, $total_pwd, $total_sen,
            $total_inf, $total_min, $total_preg, $total_ip,
            $incident_id
        ]);

        // Sort breakdown by population desc
        usort($breakdown, fn($a, $b) => $b['affected_population'] - $a['affected_population']);

        echo json_encode([
            'success'       => true,
            'incident_id'   => $incident_id,
            'total_households' => $total_hh,
            'total_population' => $total_pop,
            'total_pwd'        => $total_pwd,
            'total_seniors'    => $total_sen,
            'total_infants'    => $total_inf,
            'total_minors'     => $total_min,
            'total_pregnant'   => $total_preg,
            'total_ip'         => $total_ip,
            'breakdown'        => $breakdown,
            'excluded_count'   => $excluded_count,
        ]);
        exit;
    }

    // ── 4. POST update_status ─────────────────────────────────────────────────
    if ($action === 'update_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id     = (int)($_POST['id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        if (!$id || !in_array($status, ['ongoing', 'resolved', 'monitoring'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE incident_reports SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // ── 5. POST delete_incident ───────────────────────────────────────────────
    if ($action === 'delete_incident' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'error' => 'Invalid ID']); exit; }
        // Cascade delete affected_areas first (in case no FK cascade)
        $pdo->prepare("DELETE FROM affected_areas WHERE incident_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM incident_reports WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // ── 6. GET get_households_for_map ─────────────────────────────────────────
    if ($action === 'get_households_for_map' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->prepare("
            SELECT id, latitude, longitude, pwd_count, senior_count, infant_count, pregnant_count
            FROM households
            WHERE latitude  IS NOT NULL
              AND longitude IS NOT NULL
              AND latitude  BETWEEN ? AND ?
              AND longitude BETWEEN ? AND ?
        ");
        $stmt->execute([GPS_LAT_MIN, GPS_LAT_MAX, GPS_LNG_MIN, GPS_LNG_MAX]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    // ── 7. GET get_barangay_boundaries ────────────────────────────────────────
    if ($action === 'get_barangay_boundaries' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->query("SELECT id, name, boundary_geojson FROM barangays WHERE boundary_geojson IS NOT NULL AND boundary_geojson != ''");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

// ── Page data ─────────────────────────────────────────────────────────────────
$hazard_types = $pdo->query("SELECT id, name, color FROM hazard_types ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$summary = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(status IN ('ongoing','monitoring')) AS active,
        SUM(status = 'resolved') AS resolved,
        COALESCE(SUM(total_affected_population), 0) AS total_pop
    FROM incident_reports
")->fetch(PDO::FETCH_ASSOC);

// ── HTML ──────────────────────────────────────────────────────────────────────
$pageTitle = 'Incident Reports';
$extraHead = <<<HTML
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css">
<style>
  /* ── Incident page styles ── */
  .page-body { padding: 20px; }
  .stat-card  { border-radius: 10px; padding: 16px 20px; color: #fff; display: flex; align-items: center; gap: 14px; }
  .stat-card .stat-icon { font-size: 2rem; opacity: .85; }
  .stat-card .stat-val  { font-size: 1.8rem; font-weight: 700; line-height: 1; }
  .stat-card .stat-lbl  { font-size: .78rem; opacity: .9; margin-top: 2px; }

  /* Map container */
  #incidentMap {
    height: calc(100vh - 200px);
    min-height: 420px;
    width: 100%;
    border-radius: 8px;
    border: 1px solid #dee2e6;
  }

  /* View modal map */
  #viewIncidentMap {
    height: 340px;
    width: 100%;
    border-radius: 6px;
    border: 1px solid #dee2e6;
  }

  .map-tip {
    background: #e8f4fd;
    border-left: 4px solid #0d6efd;
    padding: 8px 12px;
    font-size: .82rem;
    border-radius: 0 4px 4px 0;
    margin-bottom: 10px;
  }

  /* Tile switcher */
  .tile-switcher { position: absolute; top: 10px; right: 10px; z-index: 1000; }
  .tile-switcher .btn { font-size: .75rem; padding: 3px 8px; }

  /* polygon info banner */
  #polygonInfo {
    display: none;
    background: #d1ecf1;
    border: 1px solid #bee5eb;
    border-radius: 6px;
    padding: 8px 12px;
    font-size: .83rem;
    margin-top: 8px;
  }

  /* Status badges */
  .badge-ongoing    { background: #dc3545; }
  .badge-monitoring { background: #fd7e14; }
  .badge-resolved   { background: #198754; }

  /* Table */
  .incident-table th { background: #f8f9fa; font-size: .82rem; }
  .incident-table td { font-size: .83rem; vertical-align: middle; }

  /* Form panel */
  .form-panel { background: #fff; border-radius: 8px; border: 1px solid #dee2e6; padding: 20px; height: 100%; }

  /* Results modal breakdown table */
  .breakdown-table th { font-size: .78rem; }
  .breakdown-table td { font-size: .8rem; }

  /* Large metric */
  .big-metric { font-size: 2.5rem; font-weight: 700; color: #0d6efd; }
</style>
HTML;

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<!-- ── Main Content ─────────────────────────────────────────────────────── -->
<div class="main-content">

  <!-- Topbar -->
  <div class="topbar">
    <div class="d-flex align-items-center gap-3">
      <i class="fas fa-file-medical-alt text-danger fs-5"></i>
      <h5 class="mb-0 fw-semibold">Incident Reports</h5>
    </div>
    <button class="btn btn-danger btn-sm" id="btnNewIncident">
      <i class="fas fa-plus me-1"></i>New Incident Report
    </button>
  </div>

  <!-- Page Body -->
  <div class="page-body">

    <!-- ── Summary Cards ── -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="stat-card" style="background: linear-gradient(135deg,#1e3c72,#2a5298);">
          <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
          <div>
            <div class="stat-val" id="cardTotal"><?= (int)$summary['total'] ?></div>
            <div class="stat-lbl">Total Incidents</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card" style="background: linear-gradient(135deg,#b02a37,#dc3545);">
          <div class="stat-icon"><i class="fas fa-fire"></i></div>
          <div>
            <div class="stat-val" id="cardActive"><?= (int)$summary['active'] ?></div>
            <div class="stat-lbl">Active (Ongoing + Monitoring)</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card" style="background: linear-gradient(135deg,#5a2d82,#7952b3);">
          <div class="stat-icon"><i class="fas fa-users"></i></div>
          <div>
            <div class="stat-val" id="cardPop"><?= number_format((int)$summary['total_pop']) ?></div>
            <div class="stat-lbl">Total Affected Population</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card" style="background: linear-gradient(135deg,#155724,#198754);">
          <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
          <div>
            <div class="stat-val" id="cardResolved"><?= (int)$summary['resolved'] ?></div>
            <div class="stat-lbl">Resolved</div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Tabs ── -->
    <ul class="nav nav-tabs mb-3" id="incidentTabs" role="tablist">
      <li class="nav-item">
        <button class="nav-link active" id="tab-list-btn" data-bs-toggle="tab" data-bs-target="#tab-list" type="button">
          <i class="fas fa-list me-1"></i>Incident List
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link" id="tab-new-btn" data-bs-toggle="tab" data-bs-target="#tab-new" type="button">
          <i class="fas fa-plus-circle me-1"></i>New Incident Report
        </button>
      </li>
    </ul>

    <div class="tab-content" id="incidentTabContent">

      <!-- ════════════════════════════════════════════════════
           TAB 1 — Incident List
      ════════════════════════════════════════════════════ -->
      <div class="tab-pane fade show active" id="tab-list" role="tabpanel">

        <!-- Filter bar -->
        <div class="card border-0 shadow-sm mb-3">
          <div class="card-body py-2">
            <div class="row g-2 align-items-end">
              <div class="col-auto">
                <label class="form-label mb-1 small fw-semibold">Status</label>
                <select class="form-select form-select-sm" id="filterStatus" style="min-width:140px;">
                  <option value="">All Statuses</option>
                  <option value="ongoing">Ongoing</option>
                  <option value="monitoring">Monitoring</option>
                  <option value="resolved">Resolved</option>
                </select>
              </div>
              <div class="col-auto">
                <label class="form-label mb-1 small fw-semibold">Hazard Type</label>
                <select class="form-select form-select-sm" id="filterHazard" style="min-width:160px;">
                  <option value="">All Types</option>
                  <?php foreach ($hazard_types as $ht): ?>
                  <option value="<?= $ht['id'] ?>"><?= htmlspecialchars($ht['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-auto">
                <button class="btn btn-sm btn-outline-secondary" id="btnResetFilters">
                  <i class="fas fa-redo me-1"></i>Reset
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Table -->
        <div class="card border-0 shadow-sm">
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover incident-table mb-0">
                <thead>
                  <tr>
                    <th class="ps-3">#</th>
                    <th>Title</th>
                    <th>Disaster Type</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Affected Population</th>
                    <th class="text-center">Actions</th>
                  </tr>
                </thead>
                <tbody id="incidentTableBody">
                  <tr><td colspan="7" class="text-center py-4 text-muted">
                    <i class="fas fa-spinner fa-spin me-2"></i>Loading incidents...
                  </td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div><!-- /tab-list -->

      <!-- ════════════════════════════════════════════════════
           TAB 2 — New Incident Report
      ════════════════════════════════════════════════════ -->
      <div class="tab-pane fade" id="tab-new" role="tabpanel">
        <div class="row g-3" style="align-items: stretch;">

          <!-- Left: Form -->
          <div class="col-md-4">
            <div class="form-panel">
              <h6 class="fw-bold mb-3 text-danger">
                <i class="fas fa-file-medical-alt me-1"></i>Incident Details
              </h6>

              <div class="mb-3">
                <label class="form-label fw-semibold small">Incident Title <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm" id="incTitle" placeholder="e.g. Typhoon Carina Flooding" required>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold small">Disaster Type <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm" id="incHazardType">
                  <option value="">— Select Type —</option>
                  <?php foreach ($hazard_types as $ht): ?>
                  <option value="<?= $ht['id'] ?>" data-color="<?= htmlspecialchars($ht['color'] ?? '#6c757d') ?>">
                    <?= htmlspecialchars($ht['name']) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold small">Incident Date <span class="text-danger">*</span></label>
                <input type="date" class="form-control form-control-sm" id="incDate" value="<?= date('Y-m-d') ?>" required>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold small">Status</label>
                <select class="form-select form-select-sm" id="incStatus">
                  <option value="ongoing">Ongoing</option>
                  <option value="monitoring">Monitoring</option>
                  <option value="resolved">Resolved</option>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label fw-semibold small">Description</label>
                <textarea class="form-control form-control-sm" id="incDescription" rows="4"
                          placeholder="Describe the incident, causes, initial observations..."></textarea>
              </div>

              <input type="hidden" id="incPolygonGeojson">

              <div id="polygonInfo">
                <i class="fas fa-check-circle text-success me-1"></i>
                Polygon captured — <strong id="polygonHHCount">0</strong> households in area will be computed on save.
              </div>

              <div class="d-grid mt-3">
                <button class="btn btn-danger" id="btnSaveIncident" disabled>
                  <i class="fas fa-save me-1"></i>Save Incident Report
                </button>
              </div>
              <div class="text-muted small mt-2 text-center" id="saveHint">
                <i class="fas fa-info-circle me-1"></i>Draw a polygon on the map first to enable saving.
              </div>
            </div>
          </div>

          <!-- Right: Map -->
          <div class="col-md-8">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-body p-2 d-flex flex-column">
                <div class="map-tip">
                  <i class="fas fa-draw-polygon me-1 text-primary"></i>
                  Draw a polygon over the affected area. The system will auto-count all households inside.
                </div>
                <div style="position:relative; flex:1;">
                  <div id="incidentMap"></div>
                  <!-- Tile switcher -->
                  <div class="tile-switcher btn-group" role="group">
                    <button class="btn btn-light btn-sm active" id="tileOSM" title="Street Map">Street</button>
                    <button class="btn btn-light btn-sm" id="tileSat" title="Satellite">Satellite</button>
                    <button class="btn btn-light btn-sm" id="tileTerrain" title="Terrain">Terrain</button>
                    <button class="btn btn-light btn-sm" id="tileHybrid" title="Hybrid">Hybrid</button>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div><!-- /row -->
      </div><!-- /tab-new -->

    </div><!-- /tab-content -->
  </div><!-- /page-body -->
</div><!-- /main-content -->

<!-- ══════════════════════════════════════════════════════════
     Modal: Results after save
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="resultsModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title"><i class="fas fa-chart-bar me-2"></i>Incident Saved — Affected Area Results</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="resultsModalBody">
        <!-- Filled by JS -->
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-outline-danger" id="btnViewNewIncident">
          <i class="fas fa-eye me-1"></i>View Incident
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     Modal: View Incident
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="viewModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header" style="background:#1e3c72;color:#fff;">
        <h5 class="modal-title"><i class="fas fa-file-medical-alt me-2"></i><span id="viewModalTitle">Incident Details</span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="viewModalBody">
        <div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     Modal: Edit Status
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="editStatusModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title"><i class="fas fa-edit me-1"></i>Update Status</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="editStatusId">
        <label class="form-label fw-semibold small">New Status</label>
        <select class="form-select" id="editStatusVal">
          <option value="ongoing">Ongoing</option>
          <option value="monitoring">Monitoring</option>
          <option value="resolved">Resolved</option>
        </select>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm" id="btnConfirmStatus">
          <i class="fas fa-save me-1"></i>Update
        </button>
      </div>
    </div>
  </div>
</div>

<?php
$isAdmin    = ($role === 'admin');
$isAdminJS  = $isAdmin ? 'true' : 'false';
$extraFooter = '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>' . "\n"
             . '<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>' . "\n"
             . '<script>const _PHP_IS_ADMIN = ' . $isAdminJS . ';</script>' . "\n";
$extraFooter .= <<<'SCRIPTS'
<script>
(function () {
  'use strict';

  const BASE_URL  = '/';
  const SELF      = BASE_URL + 'modules/incidents/incident_management.php';
  const IS_ADMIN  = _PHP_IS_ADMIN;
  let lastSavedId = null;
  let viewMapInst = null;

  // ── Map state ────────────────────────────────────────────────────────────
  let incMap       = null;
  let drawnItems   = null;
  let drawControl  = null;
  let currentTile  = null;
  let hhLayer      = null;
  let brgyLayer    = null;
  let hhData       = [];

  const tileLayers = {
    osm:     L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
               { attribution: '© OpenStreetMap contributors', maxZoom: 19 }),
    sat:     L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
               { attribution: '© Esri', maxZoom: 19 }),
    terrain: L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
               { attribution: '© OpenTopoMap', maxZoom: 17 }),
    hybrid:  L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}',
               { attribution: '© Esri', maxZoom: 19 }),
  };

  function initMap() {
    if (incMap) return;
    incMap = L.map('incidentMap', { center: [12.84, 120.77], zoom: 12 });
    currentTile = tileLayers.osm.addTo(incMap);

    drawnItems  = new L.FeatureGroup().addTo(incMap);
    drawControl = new L.Control.Draw({
      draw: {
        polygon:   { shapeOptions: { color: '#dc3545', fillOpacity: 0.2 } },
        polyline:  false,
        rectangle: false,
        circle:    false,
        circlemarker: false,
        marker:    false,
      },
      edit: { featureGroup: drawnItems },
    });
    incMap.addControl(drawControl);

    incMap.on(L.Draw.Event.CREATED, function (e) {
      drawnItems.clearLayers();
      drawnItems.addLayer(e.layer);
      const gj = e.layer.toGeoJSON();
      $('#incPolygonGeojson').val(JSON.stringify(gj));
      updatePolygonInfo(gj);
      $('#btnSaveIncident').prop('disabled', false);
      $('#saveHint').hide();
      $('#polygonInfo').show();
    });

    incMap.on(L.Draw.Event.EDITED, function () {
      drawnItems.eachLayer(function (layer) {
        const gj = layer.toGeoJSON();
        $('#incPolygonGeojson').val(JSON.stringify(gj));
        updatePolygonInfo(gj);
      });
    });

    incMap.on(L.Draw.Event.DELETED, function () {
      $('#incPolygonGeojson').val('');
      $('#btnSaveIncident').prop('disabled', true);
      $('#polygonInfo').hide();
      $('#saveHint').show();
    });

    // Load reference layers
    loadHouseholdLayer();
    loadBrgyLayer();
  }

  function switchTile(name) {
    if (currentTile) incMap.removeLayer(currentTile);
    currentTile = tileLayers[name].addTo(incMap);
    // Push tile to back
    currentTile.bringToBack();
    if (hhLayer)   hhLayer.bringToFront();
    if (brgyLayer) brgyLayer.bringToFront();
    $('.tile-switcher .btn').removeClass('active');
    $('#tile' + name.charAt(0).toUpperCase() + name.slice(1)).addClass('active');
  }

  $('#tileOSM').on('click',     () => switchTile('osm'));
  $('#tileSat').on('click',     () => switchTile('sat'));
  $('#tileTerrain').on('click', () => switchTile('terrain'));
  $('#tileHybrid').on('click',  () => switchTile('hybrid'));

  function loadHouseholdLayer() {
    $.getJSON(SELF + '?action=get_households_for_map', function (resp) {
      if (!resp.success) return;
      hhData = resp.data;
      if (hhLayer) incMap.removeLayer(hhLayer);
      hhLayer = L.layerGroup();
      resp.data.forEach(function (hh) {
        const vuln = (parseInt(hh.pwd_count)||0)
                   + (parseInt(hh.senior_count)||0)
                   + (parseInt(hh.infant_count)||0)
                   + (parseInt(hh.pregnant_count)||0);
        const color = vuln > 0 ? '#dc3545' : '#0d6efd';
        L.circleMarker([parseFloat(hh.latitude), parseFloat(hh.longitude)], {
          radius: 4, color: color, fillColor: color,
          fillOpacity: 0.75, weight: 1,
        }).addTo(hhLayer);
      });
      hhLayer.addTo(incMap);
    });
  }

  function loadBrgyLayer() {
    $.getJSON(SELF + '?action=get_barangay_boundaries', function (resp) {
      if (!resp.success) return;
      if (brgyLayer) incMap.removeLayer(brgyLayer);
      brgyLayer = L.layerGroup();
      resp.data.forEach(function (b) {
        try {
          const gj = typeof b.boundary_geojson === 'string'
            ? JSON.parse(b.boundary_geojson) : b.boundary_geojson;
          L.geoJSON(gj, {
            style: { color: '#adb5bd', weight: 1.5, fillOpacity: 0.05, dashArray: '4,4' },
            onEachFeature: function (feature, layer) {
              layer.bindTooltip(b.name, { sticky: true, className: 'leaflet-tooltip-brgy' });
            },
          }).addTo(brgyLayer);
        } catch(e) {}
      });
      brgyLayer.addTo(incMap);
      if (hhLayer) hhLayer.bringToFront();
    });
  }

  function updatePolygonInfo(gj) {
    // Count households inside polygon (client-side preview)
    let coords = null;
    if (gj.type === 'Feature') {
      coords = gj.geometry && gj.geometry.coordinates ? gj.geometry.coordinates[0] : null;
    } else if (gj.type === 'Polygon') {
      coords = gj.coordinates[0];
    }
    if (!coords || !hhData.length) {
      $('#polygonHHCount').text('?');
      return;
    }
    let count = 0;
    hhData.forEach(function (hh) {
      if (pointInPolygonJS(parseFloat(hh.latitude), parseFloat(hh.longitude), coords)) count++;
    });
    $('#polygonHHCount').text(count.toLocaleString());
  }

  // Client-side ray casting (for preview count only)
  function pointInPolygonJS(lat, lng, coords) {
    const n = coords.length;
    if (n < 3) return false;
    let inside = false, j = n - 1;
    for (let i = 0; i < n; i++) {
      const xi = coords[i][0], yi = coords[i][1]; // lng, lat
      const xj = coords[j][0], yj = coords[j][1];
      if (((yi > lat) !== (yj > lat)) &&
          (lng < (xj - xi) * (lat - yi) / (yj - yi) + xi)) {
        inside = !inside;
      }
      j = i;
    }
    return inside;
  }

  // ── Tab: init map when New Incident tab shown ────────────────────────────
  $('#tab-new-btn').on('shown.bs.tab', function () {
    if (!incMap) {
      initMap();
    } else {
      setTimeout(() => incMap.invalidateSize(), 100);
    }
  });

  // "New Incident Report" topbar button → switch to tab 2
  $('#btnNewIncident').on('click', function () {
    const tabEl = document.getElementById('tab-new-btn');
    bootstrap.Tab.getOrCreateInstance(tabEl).show();
  });

  // ── Load incidents ────────────────────────────────────────────────────────
  function loadIncidents() {
    const status = $('#filterStatus').val();
    const hazard = $('#filterHazard').val();
    let url = SELF + '?action=get_incidents';
    if (status) url += '&status=' + encodeURIComponent(status);
    if (hazard) url += '&hazard_type_id=' + encodeURIComponent(hazard);

    $('#incidentTableBody').html('<tr><td colspan="7" class="text-center py-3 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Loading...</td></tr>');

    $.getJSON(url, function (resp) {
      if (!resp.success) {
        $('#incidentTableBody').html('<tr><td colspan="7" class="text-center text-danger py-3">Failed to load incidents.</td></tr>');
        return;
      }
      const data = resp.data;
      if (!data.length) {
        $('#incidentTableBody').html('<tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>No incidents found.</td></tr>');
        return;
      }
      let html = '';
      data.forEach(function (inc, idx) {
        const statusClass = { ongoing: 'badge-ongoing', monitoring: 'badge-monitoring', resolved: 'badge-resolved' };
        const statusBadge = `<span class="badge ${statusClass[inc.status] || 'bg-secondary'} text-white">${capitalize(inc.status)}</span>`;
        const dotColor = inc.hazard_type_color || '#6c757d';
        const hazardBadge = `<span class="d-flex align-items-center gap-1">
          <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${escAttr(dotColor)};flex-shrink:0;"></span>
          ${escHtml(inc.hazard_type_name || '—')}
        </span>`;
        const pop = inc.total_affected_population > 0
          ? Number(inc.total_affected_population).toLocaleString()
          : '<span class="text-muted">—</span>';
        const dateStr = inc.incident_date ? formatDate(inc.incident_date) : '—';
        html += `<tr>
          <td class="ps-3 text-muted">${idx + 1}</td>
          <td class="fw-semibold">${escHtml(inc.title)}</td>
          <td>${hazardBadge}</td>
          <td>${escHtml(dateStr)}</td>
          <td>${statusBadge}</td>
          <td>${pop}</td>
          <td class="text-center">
            <button class="btn btn-sm btn-outline-primary me-1 btn-view" data-id="${inc.id}" title="View">
              <i class="fas fa-eye"></i>
            </button>
            <button class="btn btn-sm btn-outline-warning me-1 btn-edit-status"
              data-id="${inc.id}" data-status="${inc.status}" title="Edit Status">
              <i class="fas fa-edit"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger btn-delete" data-id="${inc.id}" data-title="${escAttr(inc.title)}" title="Delete">
              <i class="fas fa-trash"></i>
            </button>
          </td>
        </tr>`;
      });
      $('#incidentTableBody').html(html);
    }).fail(function () {
      $('#incidentTableBody').html('<tr><td colspan="7" class="text-center text-danger py-3">Request failed.</td></tr>');
    });
  }

  loadIncidents();

  $('#filterStatus, #filterHazard').on('change', loadIncidents);
  $('#btnResetFilters').on('click', function () {
    $('#filterStatus').val('');
    $('#filterHazard').val('');
    loadIncidents();
  });

  // ── Table actions via delegation ──────────────────────────────────────────
  $('#incidentTableBody').on('click', '.btn-view', function () {
    const id = $(this).data('id');
    openViewModal(id);
  });

  $('#incidentTableBody').on('click', '.btn-edit-status', function () {
    const id     = $(this).data('id');
    const status = $(this).data('status');
    $('#editStatusId').val(id);
    $('#editStatusVal').val(status);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('editStatusModal')).show();
  });

  $('#incidentTableBody').on('click', '.btn-delete', function () {
    const id    = $(this).data('id');
    const title = $(this).data('title');
    if (!confirm('Delete incident "' + title + '"?\n\nThis will also remove all affected area records. This cannot be undone.')) return;
    $.post(SELF, { action: 'delete_incident', id: id }, function (resp) {
      if (resp.success) {
        showToast('Incident deleted.', 'success');
        loadIncidents();
      } else {
        showToast('Delete failed: ' + (resp.error || 'Unknown error'), 'danger');
      }
    }, 'json').fail(function () {
      showToast('Request failed.', 'danger');
    });
  });

  // ── Update Status ──────────────────────────────────────────────────────────
  $('#btnConfirmStatus').on('click', function () {
    const id     = $('#editStatusId').val();
    const status = $('#editStatusVal').val();
    $.post(SELF, { action: 'update_status', id: id, status: status }, function (resp) {
      if (resp.success) {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('editStatusModal')).hide();
        showToast('Status updated.', 'success');
        loadIncidents();
      } else {
        showToast('Failed: ' + (resp.error || 'Unknown error'), 'danger');
      }
    }, 'json');
  });

  // ── Save Incident ─────────────────────────────────────────────────────────
  $('#btnSaveIncident').on('click', function () {
    const title       = $.trim($('#incTitle').val());
    const hazard_type = $('#incHazardType').val();
    const date        = $('#incDate').val();
    const status      = $('#incStatus').val();
    const description = $.trim($('#incDescription').val());
    const polygon     = $('#incPolygonGeojson').val();

    if (!title)       { showToast('Please enter an incident title.', 'warning');       return; }
    if (!hazard_type) { showToast('Please select a disaster type.', 'warning');        return; }
    if (!date)        { showToast('Please select an incident date.', 'warning');       return; }
    if (!polygon)     { showToast('Please draw a polygon on the map.', 'warning');     return; }

    const $btn = $(this);
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Computing...');

    $.ajax({
      url: SELF,
      type: 'POST',
      data: {
        action:          'save_incident',
        title:           title,
        hazard_type_id:  hazard_type,
        incident_date:   date,
        status:          status,
        description:     description,
        polygon_geojson: polygon,
      },
      dataType: 'json',
      success: function (resp) {
        $btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i>Save Incident Report');
        if (resp.success) {
          lastSavedId = resp.incident_id;
          showResultsModal(resp);
          // Switch to incident list
          bootstrap.Tab.getOrCreateInstance(document.getElementById('tab-list-btn')).show();
          loadIncidents();
          // Reset form
          $('#incTitle').val('');
          $('#incHazardType').val('');
          $('#incDate').val(new Date().toISOString().slice(0, 10));
          $('#incStatus').val('ongoing');
          $('#incDescription').val('');
          $('#incPolygonGeojson').val('');
          if (drawnItems) drawnItems.clearLayers();
          $('#polygonInfo').hide();
          $('#saveHint').show();
          $('#btnSaveIncident').prop('disabled', true);
        } else {
          showToast('Save failed: ' + (resp.error || 'Unknown error'), 'danger');
        }
      },
      error: function () {
        $btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i>Save Incident Report');
        showToast('Request failed. Please try again.', 'danger');
      },
    });
  });

  // ── Results Modal ─────────────────────────────────────────────────────────
  function showResultsModal(data) {
    let html = `
      <div class="text-center mb-4">
        <div class="big-metric">${Number(data.total_population).toLocaleString()}</div>
        <div class="text-muted">Total Affected Population</div>
      </div>
      <div class="row g-3 mb-4 text-center">
        <div class="col-6 col-md-3">
          <div class="border rounded p-2">
            <div class="fw-bold fs-5">${Number(data.total_households).toLocaleString()}</div>
            <div class="small text-muted">Households</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="border rounded p-2">
            <div class="fw-bold fs-5 text-warning">${Number(data.total_pwd).toLocaleString()}</div>
            <div class="small text-muted">PWD</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="border rounded p-2">
            <div class="fw-bold fs-5 text-info">${Number(data.total_seniors).toLocaleString()}</div>
            <div class="small text-muted">Seniors</div>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="border rounded p-2">
            <div class="fw-bold fs-5 text-danger">${Number(data.total_infants).toLocaleString()}</div>
            <div class="small text-muted">Infants</div>
          </div>
        </div>
      </div>`;

    if (data.breakdown && data.breakdown.length) {
      html += `
        <h6 class="fw-bold mb-2"><i class="fas fa-map-marker-alt me-1 text-danger"></i>Breakdown by Barangay</h6>
        <div class="table-responsive mb-3">
          <table class="table table-sm table-bordered breakdown-table">
            <thead class="table-dark">
              <tr>
                <th>Barangay</th>
                <th class="text-center">HH</th>
                <th class="text-center">Population</th>
                <th class="text-center">PWD</th>
                <th class="text-center">Seniors</th>
                <th class="text-center">Infants</th>
                <th class="text-center">Pregnant</th>
                <th class="text-center">IP</th>
              </tr>
            </thead>
            <tbody>`;
      let vuln_total = 0;
      data.breakdown.forEach(function (row) {
        const vuln = (row.affected_pwd || 0) + (row.affected_seniors || 0)
                   + (row.affected_infants || 0) + (row.affected_pregnant || 0);
        vuln_total += vuln;
        html += `<tr>
          <td class="fw-semibold">${escHtml(row.barangay_name || 'Barangay ' + row.barangay_id)}</td>
          <td class="text-center">${row.affected_households.toLocaleString()}</td>
          <td class="text-center fw-bold">${row.affected_population.toLocaleString()}</td>
          <td class="text-center text-warning">${row.affected_pwd}</td>
          <td class="text-center text-info">${row.affected_seniors}</td>
          <td class="text-center text-danger">${row.affected_infants}</td>
          <td class="text-center text-primary">${row.affected_pregnant}</td>
          <td class="text-center">${row.ip_count}</td>
        </tr>`;
      });
      html += `</tbody>
            <tfoot class="table-secondary fw-bold">
              <tr>
                <td>TOTAL</td>
                <td class="text-center">${Number(data.total_households).toLocaleString()}</td>
                <td class="text-center">${Number(data.total_population).toLocaleString()}</td>
                <td class="text-center text-warning">${Number(data.total_pwd).toLocaleString()}</td>
                <td class="text-center text-info">${Number(data.total_seniors).toLocaleString()}</td>
                <td class="text-center text-danger">${Number(data.total_infants).toLocaleString()}</td>
                <td class="text-center text-primary">${Number(data.total_pregnant).toLocaleString()}</td>
                <td class="text-center">${Number(data.total_ip).toLocaleString()}</td>
              </tr>
              <tr>
                <td colspan="2" class="text-muted small">Vulnerability total (PWD+Seniors+Infants+Pregnant)</td>
                <td colspan="6" class="fw-bold">${vuln_total.toLocaleString()} persons</td>
              </tr>
            </tfoot>
          </table>
        </div>`;
    } else {
      html += `<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No households were found inside the drawn polygon area.</div>`;
    }

    if (data.excluded_count > 0) {
      html += `<div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Warning:</strong> ${Number(data.excluded_count).toLocaleString()} household(s) were excluded from computation
        due to missing or invalid GPS coordinates. Consider running
        <a href="${BASE_URL}modules/households/gps_quality_report.php" class="alert-link">GPS Quality Report</a>.
      </div>`;
    }

    if (IS_ADMIN) {
      html += `<div class="alert alert-info border-start border-info border-4 mt-3" style="font-size:.85rem;">
        <strong><i class="fas fa-info-circle me-1"></i>SYSTEM CAPABILITY NOTE (Admin):</strong>
        This system currently supports manual polygon drawing for affected area mapping after an incident occurs.
        This can be upgraded in a future version to support real-time GPS-based incident tracking where the
        affected polygon is automatically generated from live field reports submitted during an active disaster.
        The household GPS infrastructure is already in place to support this upgrade.
      </div>`;
    }

    $('#resultsModalBody').html(html);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('resultsModal')).show();
  }

  $('#btnViewNewIncident').on('click', function () {
    if (!lastSavedId) return;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('resultsModal')).hide();
    setTimeout(() => openViewModal(lastSavedId), 350);
  });

  // ── View Incident Modal ───────────────────────────────────────────────────
  function openViewModal(id) {
    $('#viewModalTitle').text('Loading...');
    $('#viewModalBody').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('viewModal')).show();

    $.getJSON(SELF + '?action=get_incident&id=' + id, function (resp) {
      if (!resp.success) {
        $('#viewModalBody').html('<div class="alert alert-danger">Failed to load incident.</div>');
        return;
      }
      const inc   = resp.incident;
      const areas = resp.affected_areas || [];
      $('#viewModalTitle').text(escHtmlStr(inc.title));

      const statusClass = { ongoing: 'badge-ongoing', monitoring: 'badge-monitoring', resolved: 'badge-resolved' };

      let html = `
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <table class="table table-sm table-borderless mb-0">
              <tr><th class="text-muted small ps-0" width="40%">Title</th><td class="fw-semibold">${escHtml(inc.title)}</td></tr>
              <tr><th class="text-muted small ps-0">Hazard Type</th><td>${escHtml(inc.hazard_type_name || '—')}</td></tr>
              <tr><th class="text-muted small ps-0">Date</th><td>${formatDate(inc.incident_date)}</td></tr>
              <tr><th class="text-muted small ps-0">Status</th><td><span class="badge ${statusClass[inc.status] || 'bg-secondary'} text-white">${capitalize(inc.status)}</span></td></tr>
              <tr><th class="text-muted small ps-0">Affected Pop.</th><td class="fw-bold text-danger">${Number(inc.total_affected_population || 0).toLocaleString()}</td></tr>
              <tr><th class="text-muted small ps-0">Households</th><td>${Number(inc.total_affected_households || 0).toLocaleString()}</td></tr>
            </table>
          </div>
          <div class="col-md-6">
            <table class="table table-sm table-borderless mb-0">
              <tr><th class="text-muted small ps-0" width="40%">PWD</th><td>${Number(inc.total_affected_pwd || 0).toLocaleString()}</td></tr>
              <tr><th class="text-muted small ps-0">Seniors</th><td>${Number(inc.total_affected_seniors || 0).toLocaleString()}</td></tr>
              <tr><th class="text-muted small ps-0">Infants</th><td>${Number(inc.total_affected_infants || 0).toLocaleString()}</td></tr>
              <tr><th class="text-muted small ps-0">Minors</th><td>${Number(inc.total_affected_minors || 0).toLocaleString()}</td></tr>
              <tr><th class="text-muted small ps-0">Pregnant</th><td>${Number(inc.total_affected_pregnant || 0).toLocaleString()}</td></tr>
              <tr><th class="text-muted small ps-0">IP Count</th><td>${Number(inc.total_ip_count || 0).toLocaleString()}</td></tr>
            </table>
          </div>
        </div>`;

      if (inc.description) {
        html += `<div class="mb-3">
          <h6 class="text-muted small fw-semibold text-uppercase mb-1">Description</h6>
          <p class="mb-0" style="white-space:pre-wrap;">${escHtml(inc.description)}</p>
        </div>`;
      }

      // Map
      html += `<div id="viewIncidentMap" style="height:340px;border-radius:6px;border:1px solid #dee2e6;" class="mb-3"></div>`;

      // Affected areas table
      if (areas.length) {
        html += `
          <h6 class="fw-bold mb-2"><i class="fas fa-map-marker-alt me-1 text-danger"></i>Affected Areas</h6>
          <div class="table-responsive">
            <table class="table table-sm table-bordered breakdown-table">
              <thead class="table-dark">
                <tr>
                  <th>Barangay</th>
                  <th class="text-center">HH</th>
                  <th class="text-center">Population</th>
                  <th class="text-center">PWD</th>
                  <th class="text-center">Seniors</th>
                  <th class="text-center">Infants</th>
                  <th class="text-center">Pregnant</th>
                  <th class="text-center">IP</th>
                </tr>
              </thead>
              <tbody>`;
        areas.forEach(function (a) {
          html += `<tr>
            <td class="fw-semibold">${escHtml(a.barangay_name || '—')}</td>
            <td class="text-center">${Number(a.affected_households || 0).toLocaleString()}</td>
            <td class="text-center fw-bold">${Number(a.affected_population || 0).toLocaleString()}</td>
            <td class="text-center">${a.affected_pwd || 0}</td>
            <td class="text-center">${a.affected_seniors || 0}</td>
            <td class="text-center">${a.affected_infants || 0}</td>
            <td class="text-center">${a.affected_pregnant || 0}</td>
            <td class="text-center">${a.ip_count || 0}</td>
          </tr>`;
        });
        html += `</tbody></table></div>`;
      }

      $('#viewModalBody').html(html);

      // Init read-only map in modal
      setTimeout(function () {
        if (viewMapInst) {
          viewMapInst.remove();
          viewMapInst = null;
        }
        viewMapInst = L.map('viewIncidentMap', { center: [12.84, 120.77], zoom: 12 });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
          { attribution: '© OpenStreetMap contributors', maxZoom: 19 }).addTo(viewMapInst);

        if (inc.polygon_geojson) {
          try {
            const gj = typeof inc.polygon_geojson === 'string'
              ? JSON.parse(inc.polygon_geojson) : inc.polygon_geojson;
            const poly = L.geoJSON(gj, {
              style: { color: inc.hazard_type_color || '#dc3545', weight: 2.5, fillOpacity: 0.2 },
            }).addTo(viewMapInst);
            viewMapInst.fitBounds(poly.getBounds(), { padding: [30, 30] });
          } catch (e) {}
        }
      }, 300);

    }).fail(function () {
      $('#viewModalBody').html('<div class="alert alert-danger">Request failed.</div>');
    });
  }

  // Clean up view map when modal closes
  document.getElementById('viewModal').addEventListener('hidden.bs.modal', function () {
    if (viewMapInst) { viewMapInst.remove(); viewMapInst = null; }
  });

  // ── Helpers ───────────────────────────────────────────────────────────────
  function escHtml(str) {
    if (str == null) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }
  function escHtmlStr(str) { return escHtml(str); }
  function escAttr(str) { return escHtml(str); }

  function capitalize(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
  }

  function formatDate(dateStr) {
    if (!dateStr) return '—';
    try {
      const d = new Date(dateStr + 'T00:00:00');
      return d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
    } catch (e) { return dateStr; }
  }

})();
</script>
SCRIPTS;

require_once __DIR__ . '/../../includes/footer.php';
