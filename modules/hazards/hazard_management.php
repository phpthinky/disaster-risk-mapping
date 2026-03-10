<?php
/**
 * modules/hazards/hazard_management.php
 * Hazard Zone Management — admin and barangay_staff access.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/sync.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// --- Auth check ---
if (empty($_SESSION['user_id'])) {
    if (isset($_GET['action']) || isset($_POST['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$role            = $_SESSION['role']         ?? '';
$session_uid     = (int)($_SESSION['user_id']     ?? 0);
$session_bgy_id  = (int)($_SESSION['barangay_id'] ?? 0);

if (!in_array($role, ['admin', 'barangay_staff'])) {
    if (isset($_GET['action']) || isset($_POST['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
    http_response_code(403);
    die('<h3>Access Denied</h3>');
}

$is_admin = ($role === 'admin');
$is_staff = ($role === 'barangay_staff');

// -------------------------------------------------------------------------
// AJAX HANDLERS — must run before any HTML output
// -------------------------------------------------------------------------
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action) {
    header('Content-Type: application/json');

    // Helper: sanitize string
    $clean = function($val) {
        return htmlspecialchars(strip_tags(trim((string)$val)), ENT_QUOTES, 'UTF-8');
    };

    try {

        // ---- GET: get_hazard_zones ----
        if ($action === 'get_hazard_zones' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $where  = [];
            $params = [];

            if ($is_staff) {
                $where[]  = 'hz.barangay_id = ?';
                $params[] = $session_bgy_id;
            } elseif (!empty($_GET['barangay_id'])) {
                $where[]  = 'hz.barangay_id = ?';
                $params[] = (int)$_GET['barangay_id'];
            }

            $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $sql = "
                SELECT
                    hz.id,
                    hz.barangay_id,
                    hz.hazard_type_id,
                    hz.risk_level,
                    hz.area_km2,
                    hz.affected_population,
                    hz.coordinates,
                    hz.description,
                    hz.polygon_geojson,
                    hz.created_at,
                    ht.name  AS hazard_type_name,
                    ht.color AS hazard_type_color,
                    ht.icon  AS hazard_type_icon,
                    b.name   AS barangay_name
                FROM hazard_zones hz
                LEFT JOIN hazard_types ht ON ht.id = hz.hazard_type_id
                LEFT JOIN barangays b     ON b.id  = hz.barangay_id
                $whereSQL
                ORDER BY hz.created_at DESC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($rows);
            exit;
        }

        // ---- GET: get_hazard_zone (single) ----
        if ($action === 'get_hazard_zone' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) { echo json_encode(['success' => false, 'message' => 'Missing id']); exit; }

            $sql = "
                SELECT hz.*, ht.name AS hazard_type_name, b.name AS barangay_name
                FROM hazard_zones hz
                LEFT JOIN hazard_types ht ON ht.id = hz.hazard_type_id
                LEFT JOIN barangays    b  ON b.id  = hz.barangay_id
                WHERE hz.id = ?
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) { echo json_encode(['success' => false, 'message' => 'Record not found']); exit; }

            // Staff can only view their own barangay
            if ($is_staff && (int)$row['barangay_id'] !== $session_bgy_id) {
                echo json_encode(['success' => false, 'message' => 'Access denied']); exit;
            }

            echo json_encode($row);
            exit;
        }

        // ---- GET: get_hazard_types ----
        if ($action === 'get_hazard_types' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $stmt = $pdo->query("SELECT id, name, color, icon FROM hazard_types ORDER BY name ASC");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        }

        // ---- GET: get_barangay_population (helper for modal) ----
        if ($action === 'get_barangay_population' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $bid = (int)($_GET['barangay_id'] ?? 0);
            if (!$bid) { echo json_encode(['population' => 0]); exit; }
            $stmt = $pdo->prepare("SELECT population FROM barangays WHERE id = ? LIMIT 1");
            $stmt->execute([$bid]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['population' => $row ? (int)$row['population'] : 0]);
            exit;
        }

        // ---- POST: save_hazard_zone ----
        if ($action === 'save_hazard_zone' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $id              = (int)($_POST['id'] ?? 0);
            $hazard_type_id  = (int)($_POST['hazard_type_id'] ?? 0);
            $risk_level      = $clean($_POST['risk_level'] ?? '');
            $area_km2        = isset($_POST['area_km2']) && $_POST['area_km2'] !== '' ? (float)$_POST['area_km2'] : null;
            $coordinates     = $clean($_POST['coordinates'] ?? '');
            $description     = $clean($_POST['description'] ?? '');
            $polygon_geojson = trim($_POST['polygon_geojson'] ?? '');

            // Determine barangay_id — staff always uses their own
            if ($is_staff) {
                $barangay_id = $session_bgy_id;
            } else {
                $barangay_id = (int)($_POST['barangay_id'] ?? 0);
            }

            // Validate required fields
            if (!$barangay_id) {
                echo json_encode(['success' => false, 'message' => 'Barangay is required']); exit;
            }
            if (!$hazard_type_id) {
                echo json_encode(['success' => false, 'message' => 'Hazard type is required']); exit;
            }

            $valid_risk_levels = [
                'High Susceptible', 'Moderate Susceptible', 'Low Susceptible',
                'Prone', 'Generally Susceptible',
                'PEIS VIII Very destructive', 'PEIS VII Destructive',
                'General Inundation', 'Not Susceptible'
            ];
            if (!in_array($risk_level, $valid_risk_levels, true)) {
                echo json_encode(['success' => false, 'message' => 'Invalid risk level']); exit;
            }

            // Verify hazard_type_id exists
            $chk = $pdo->prepare("SELECT id FROM hazard_types WHERE id = ? LIMIT 1");
            $chk->execute([$hazard_type_id]);
            if (!$chk->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Invalid hazard type']); exit;
            }

            // Verify barangay exists
            $chkb = $pdo->prepare("SELECT id FROM barangays WHERE id = ? LIMIT 1");
            $chkb->execute([$barangay_id]);
            if (!$chkb->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Invalid barangay']); exit;
            }

            // Sanitize polygon_geojson — must be valid JSON or empty
            if ($polygon_geojson !== '') {
                $decoded = json_decode($polygon_geojson, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $polygon_geojson = null;
                }
            } else {
                $polygon_geojson = null;
            }

            if ($id) {
                // UPDATE — staff can only edit their own barangay's zones
                if ($is_staff) {
                    $own = $pdo->prepare("SELECT id FROM hazard_zones WHERE id = ? AND barangay_id = ? LIMIT 1");
                    $own->execute([$id, $session_bgy_id]);
                    if (!$own->fetch()) {
                        echo json_encode(['success' => false, 'message' => 'Access denied']); exit;
                    }
                }

                $stmt = $pdo->prepare("
                    UPDATE hazard_zones SET
                        barangay_id      = ?,
                        hazard_type_id   = ?,
                        risk_level       = ?,
                        area_km2         = ?,
                        coordinates      = ?,
                        description      = ?,
                        polygon_geojson  = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $barangay_id, $hazard_type_id, $risk_level,
                    $area_km2, $coordinates ?: null, $description ?: null,
                    $polygon_geojson, $id
                ]);
                $zone_id = $id;
            } else {
                // INSERT
                $stmt = $pdo->prepare("
                    INSERT INTO hazard_zones
                        (barangay_id, hazard_type_id, risk_level, area_km2, coordinates, description, polygon_geojson, affected_population)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 0)
                ");
                $stmt->execute([
                    $barangay_id, $hazard_type_id, $risk_level,
                    $area_km2, $coordinates ?: null, $description ?: null,
                    $polygon_geojson
                ]);
                $zone_id = (int)$pdo->lastInsertId();
            }

            // Compute affected_population from barangays.population
            $upd_pop = $pdo->prepare("
                UPDATE hazard_zones
                SET affected_population = (SELECT population FROM barangays WHERE id = ? LIMIT 1)
                WHERE id = ?
            ");
            $upd_pop->execute([$barangay_id, $zone_id]);

            // Sync hazard zones for this barangay
            sync_hazard_zones($pdo, $barangay_id);

            // Fetch updated affected_population to return
            $fetch = $pdo->prepare("SELECT affected_population FROM hazard_zones WHERE id = ? LIMIT 1");
            $fetch->execute([$zone_id]);
            $fetched = $fetch->fetch(PDO::FETCH_ASSOC);
            $affected_population = $fetched ? (int)$fetched['affected_population'] : 0;

            echo json_encode([
                'success'             => true,
                'message'             => $id ? 'Hazard zone updated successfully.' : 'Hazard zone added successfully.',
                'id'                  => $zone_id,
                'affected_population' => $affected_population,
            ]);
            exit;
        }

        // ---- POST: delete_hazard_zone ----
        if ($action === 'delete_hazard_zone' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) { echo json_encode(['success' => false, 'message' => 'Missing id']); exit; }

            // Fetch zone to get barangay_id before deleting
            $fetch = $pdo->prepare("SELECT barangay_id FROM hazard_zones WHERE id = ? LIMIT 1");
            $fetch->execute([$id]);
            $zone = $fetch->fetch(PDO::FETCH_ASSOC);

            if (!$zone) { echo json_encode(['success' => false, 'message' => 'Record not found']); exit; }

            // Staff can only delete from their own barangay
            if ($is_staff && (int)$zone['barangay_id'] !== $session_bgy_id) {
                echo json_encode(['success' => false, 'message' => 'Access denied']); exit;
            }

            $del = $pdo->prepare("DELETE FROM hazard_zones WHERE id = ?");
            $del->execute([$id]);

            echo json_encode(['success' => true, 'message' => 'Hazard zone deleted successfully.']);
            exit;
        }

        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        exit;

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------------------
// PAGE SETUP — fetch data for the HTML page
// -------------------------------------------------------------------------

// Fetch barangays for filter (admin only)
$barangays = [];
if ($is_admin) {
    $stmt = $pdo->query("SELECT id, name FROM barangays ORDER BY name ASC");
    $barangays = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Hazard types for modal select
$stmt_ht = $pdo->query("SELECT id, name, color, icon FROM hazard_types ORDER BY name ASC");
$hazard_types = $stmt_ht->fetchAll(PDO::FETCH_ASSOC);

// Staff barangay name
$staff_barangay_name = '';
if ($is_staff && $session_bgy_id) {
    $stmt_b = $pdo->prepare("SELECT name FROM barangays WHERE id = ? LIMIT 1");
    $stmt_b->execute([$session_bgy_id]);
    $row_b = $stmt_b->fetch(PDO::FETCH_ASSOC);
    $staff_barangay_name = $row_b ? $row_b['name'] : '';
}

$valid_risk_levels = [
    'High Susceptible', 'Moderate Susceptible', 'Low Susceptible',
    'Prone', 'Generally Susceptible',
    'PEIS VIII Very destructive', 'PEIS VII Destructive',
    'General Inundation', 'Not Susceptible'
];

$pageTitle = 'Hazard Zone Management';
$extraHead = '
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <style>
    /* ---- Stats cards ---- */
    .stat-card {
      background: #fff;
      border-radius: 10px;
      padding: 18px 20px;
      display: flex;
      align-items: center;
      gap: 16px;
      box-shadow: 0 1px 4px rgba(0,0,0,.08);
      border-left: 4px solid transparent;
    }
    .stat-card .stat-icon {
      width: 48px; height: 48px; border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.3rem; flex-shrink: 0;
    }
    .stat-card .stat-val  { font-size: 1.6rem; font-weight: 700; line-height: 1.1; }
    .stat-card .stat-lbl  { font-size: 0.78rem; color: #6c757d; }

    /* ---- Table ---- */
    .table-card { background: #fff; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }

    /* ---- Risk badges ---- */
    .badge-risk { font-size: .75rem; padding: .35em .65em; border-radius: 5px; font-weight: 600; color: #fff; white-space: nowrap; }
    .risk-high-susceptible      { background: #e74c3c; }
    .risk-moderate-susceptible  { background: #e67e22; }
    .risk-low-susceptible       { background: #f1c40f; color: #212529 !important; }
    .risk-prone                 { background: #9b59b6; }
    .risk-generally-susceptible { background: #3498db; }
    .risk-peis-viii             { background: #922b21; }
    .risk-peis-vii              { background: #cb4335; }
    .risk-general-inundation    { background: #1abc9c; }
    .risk-not-susceptible       { background: #27ae60; }

    /* ---- Hazard type dot ---- */
    .hz-dot { display: inline-block; width: 11px; height: 11px; border-radius: 50%; flex-shrink: 0; }

    /* ---- Leaflet map inside modal ---- */
    #hazardMap { height: 300px; width: 100%; border-radius: 8px; border: 1px solid #dee2e6; z-index: 0; }
    .leaflet-container { font-size: 13px; }

    /* ---- Section headings in modal ---- */
    .modal-section-heading {
      font-size: .7rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: .8px; color: #0d6efd; border-bottom: 2px solid #e9ecef;
      padding-bottom: 6px; margin-bottom: 14px;
    }
    .tile-btn { padding: 3px 10px; font-size: .78rem; }

    /* ---- Read-only population box ---- */
    .pop-info-box {
      background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6;
      padding: 12px 14px; font-size: .875rem;
    }
    .pop-info-box .pop-label { color: #6c757d; font-size: .78rem; }

    /* ---- Topbar ---- */
    .page-topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
    .page-topbar h4 { font-weight: 700; font-size: 1.15rem; margin: 0; }
  </style>
';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<!-- Main content -->
<div class="main-content">
  <div class="topbar">
    <div class="d-flex align-items-center gap-2">
      <i class="fas fa-exclamation-triangle text-warning"></i>
      <span class="fw-bold">Hazard Zone Management</span>
    </div>
    <div class="d-flex align-items-center gap-2">
      <?php if ($is_staff): ?>
        <span class="badge bg-info text-dark">
          <i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($staff_barangay_name) ?>
        </span>
      <?php endif; ?>
      <button class="btn btn-primary btn-sm" onclick="openAddModal()">
        <i class="fas fa-plus me-1"></i>Add Hazard Zone
      </button>
    </div>
  </div>

  <div class="px-3 pb-4">

    <!-- Stats Row -->
    <div class="row g-3 mb-4" id="statsRow">
      <div class="col-6 col-md-3">
        <div class="stat-card" style="border-left-color:#0d6efd;">
          <div class="stat-icon" style="background:#e8f0fe;color:#0d6efd;">
            <i class="fas fa-layer-group"></i>
          </div>
          <div>
            <div class="stat-val" id="statTotal">—</div>
            <div class="stat-lbl">Total Zones</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card" style="border-left-color:#e74c3c;">
          <div class="stat-icon" style="background:#fdecea;color:#e74c3c;">
            <i class="fas fa-fire"></i>
          </div>
          <div>
            <div class="stat-val" id="statHigh">—</div>
            <div class="stat-lbl">High Risk Zones</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card" style="border-left-color:#e67e22;">
          <div class="stat-icon" style="background:#fef3e2;color:#e67e22;">
            <i class="fas fa-exclamation-circle"></i>
          </div>
          <div>
            <div class="stat-val" id="statMod">—</div>
            <div class="stat-lbl">Moderate Risk Zones</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card" style="border-left-color:#1abc9c;">
          <div class="stat-icon" style="background:#e8f8f5;color:#1abc9c;">
            <i class="fas fa-users"></i>
          </div>
          <div>
            <div class="stat-val" id="statPop">—</div>
            <div class="stat-lbl">Total Affected Population</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Table Card -->
    <div class="table-card p-3">
      <!-- Toolbar -->
      <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <input type="text" id="searchInput" class="form-control form-control-sm" style="max-width:240px;" placeholder="Search hazard zones...">
        <?php if ($is_admin): ?>
        <select id="barangayFilter" class="form-select form-select-sm" style="max-width:200px;">
          <option value="">All Barangays</option>
          <?php foreach ($barangays as $b): ?>
            <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <button class="btn btn-outline-secondary btn-sm ms-auto" onclick="loadTable()">
          <i class="fas fa-sync-alt me-1"></i>Refresh
        </button>
      </div>

      <!-- Table -->
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="hazardTable">
          <thead class="table-light">
            <tr>
              <th>Hazard Type</th>
              <th>Barangay</th>
              <th>Risk Level</th>
              <th>Area (km²)</th>
              <th>
                Affected Population
                <i class="fas fa-info-circle text-muted ms-1"
                   data-bs-toggle="tooltip"
                   title="Auto-computed from barangay household data"
                   style="cursor:pointer;"></i>
              </th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody id="hazardTableBody">
            <tr><td colspan="6" class="text-center text-muted py-4">
              <i class="fas fa-spinner fa-spin me-2"></i>Loading…
            </td></tr>
          </tbody>
        </table>
      </div>
      <div id="tableFooter" class="mt-2 text-muted" style="font-size:.8rem;"></div>
    </div>

  </div><!-- /px-3 -->
</div><!-- /main-content -->

<!-- =====================================================================
     ADD / EDIT MODAL
     ===================================================================== -->
<div class="modal fade" id="hazardModal" tabindex="-1" aria-labelledby="hazardModalLabel" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="hazardModalLabel">
          <i class="fas fa-exclamation-triangle me-2"></i>Add Hazard Zone
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <form id="hazardForm" novalidate>
          <input type="hidden" id="hzId" name="id" value="">

          <!-- ============================================================
               SECTION 1 — Zone Information
               ============================================================ -->
          <div class="modal-section-heading">
            <i class="fas fa-info-circle me-1"></i>Zone Information
          </div>

          <div class="row g-3 mb-3">
            <!-- Hazard Type -->
            <div class="col-md-6">
              <label class="form-label fw-semibold">Hazard Type <span class="text-danger">*</span></label>
              <select class="form-select" id="hzHazardTypeId" name="hazard_type_id" required>
                <option value="">— Select hazard type —</option>
                <?php foreach ($hazard_types as $ht): ?>
                  <option value="<?= (int)$ht['id'] ?>"
                          data-color="<?= htmlspecialchars($ht['color'] ?? '#999') ?>"
                          data-icon="<?= htmlspecialchars($ht['icon'] ?? '') ?>">
                    <?= htmlspecialchars($ht['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback">Please select a hazard type.</div>
            </div>

            <!-- Barangay -->
            <div class="col-md-6">
              <label class="form-label fw-semibold">Barangay <span class="text-danger">*</span></label>
              <?php if ($is_admin): ?>
                <select class="form-select" id="hzBarangayId" name="barangay_id" required>
                  <option value="">— Select barangay —</option>
                  <?php foreach ($barangays as $b): ?>
                    <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                  <?php endforeach; ?>
                </select>
                <div class="invalid-feedback">Please select a barangay.</div>
              <?php else: ?>
                <input type="text" class="form-control bg-light" readonly
                       value="<?= htmlspecialchars($staff_barangay_name) ?>">
                <input type="hidden" id="hzBarangayId" name="barangay_id" value="<?= $session_bgy_id ?>">
              <?php endif; ?>
            </div>

            <!-- Risk Level -->
            <div class="col-md-6">
              <label class="form-label fw-semibold">Risk Level <span class="text-danger">*</span></label>
              <select class="form-select" id="hzRiskLevel" name="risk_level" required>
                <option value="">— Select risk level —</option>
                <?php foreach ($valid_risk_levels as $rl): ?>
                  <option value="<?= htmlspecialchars($rl) ?>"><?= htmlspecialchars($rl) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback">Please select a risk level.</div>
            </div>

            <!-- Area -->
            <div class="col-md-6">
              <label class="form-label fw-semibold">Area (km²)</label>
              <input type="number" class="form-control" id="hzArea" name="area_km2"
                     min="0" step="0.0001" placeholder="e.g. 2.5">
            </div>

            <!-- Description -->
            <div class="col-12">
              <label class="form-label fw-semibold">Description</label>
              <textarea class="form-control" id="hzDescription" name="description"
                        rows="3" placeholder="Brief description of this hazard zone…" maxlength="1000"></textarea>
            </div>
          </div>

          <!-- ============================================================
               SECTION 2 — Affected Population (READ-ONLY)
               ============================================================ -->
          <div class="modal-section-heading mt-4">
            <i class="fas fa-users me-1"></i>Affected Population
          </div>

          <div class="pop-info-box mb-3">
            <div class="d-flex align-items-start gap-2">
              <i class="fas fa-info-circle text-primary mt-1" style="flex-shrink:0;"></i>
              <div>
                <div class="fw-semibold">Auto-Computed Field</div>
                <div class="text-muted" style="font-size:.825rem;">
                  Affected population is automatically computed from the total population of the
                  selected barangay's household records. It cannot be set manually.
                </div>
              </div>
            </div>
            <hr class="my-2">
            <div class="d-flex align-items-center gap-3">
              <div>
                <div class="pop-label">Current Barangay Population</div>
                <div class="fw-bold" id="popDisplay" style="font-size:1.1rem;">
                  <span class="text-muted">— select a barangay —</span>
                </div>
              </div>
            </div>
          </div>

          <!-- ============================================================
               SECTION 3 — Location (optional)
               ============================================================ -->
          <div class="modal-section-heading mt-4">
            <i class="fas fa-map-marker-alt me-1"></i>Location
            <small class="text-muted fw-normal ms-1">(optional)</small>
          </div>

          <div class="mb-2">
            <label class="form-label fw-semibold">Center Coordinates
              <small class="text-muted fw-normal">(lat, lng)</small>
            </label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-crosshairs"></i></span>
              <input type="text" class="form-control" id="hzCoordinates" name="coordinates"
                     placeholder="e.g. 12.8400, 120.8500"
                     pattern="^-?\d+(\.\d+)?\s*,\s*-?\d+(\.\d+)?$">
              <button type="button" class="btn btn-outline-secondary" id="btnGotoCoords"
                      title="Go to coordinates">
                <i class="fas fa-location-arrow"></i>
              </button>
            </div>
            <div class="form-text">Click on the map to set coordinates, or type them above.</div>
          </div>

          <!-- Tile switcher -->
          <div class="mb-2 d-flex align-items-center gap-1 flex-wrap">
            <small class="text-muted me-1">Map layer:</small>
            <button type="button" class="btn btn-outline-primary btn-sm tile-btn active" data-tile="osm">OSM</button>
            <button type="button" class="btn btn-outline-secondary btn-sm tile-btn" data-tile="satellite">Satellite</button>
            <button type="button" class="btn btn-outline-secondary btn-sm tile-btn" data-tile="terrain">Terrain</button>
            <button type="button" class="btn btn-outline-secondary btn-sm tile-btn" data-tile="hybrid">Hybrid</button>
          </div>

          <!-- Leaflet map -->
          <div id="hazardMap" class="mb-3"></div>

          <!-- Polygon GeoJSON (hidden) -->
          <input type="hidden" id="hzPolygonGeojson" name="polygon_geojson" value="">
          <div class="text-muted" style="font-size:.78rem;">
            <i class="fas fa-draw-polygon me-1"></i>
            Polygon boundary is set when drawn via the map editor (future feature).
          </div>

        </form>
      </div><!-- /modal-body -->

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="fas fa-times me-1"></i>Cancel
        </button>
        <button type="button" class="btn btn-primary" id="btnSaveHazard">
          <i class="fas fa-save me-1"></i>Save Hazard Zone
        </button>
      </div>

    </div>
  </div>
</div>

<!-- =====================================================================
     DELETE CONFIRM MODAL
     ===================================================================== -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title"><i class="fas fa-trash me-2"></i>Delete Hazard Zone</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-1">Are you sure you want to delete this hazard zone?</p>
        <p class="fw-bold mb-0" id="deleteZoneName"></p>
        <p class="text-danger small mt-1"><i class="fas fa-exclamation-triangle me-1"></i>This action cannot be undone.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger btn-sm" id="btnConfirmDelete">
          <i class="fas fa-trash me-1"></i>Delete
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<?php
$extraFooter = <<<JS
<script>
// =========================================================================
// CONSTANTS & HELPERS
// =========================================================================
const IS_ADMIN   = <?= $is_admin ? 'true' : 'false' ?>;
const SESSION_BGY = <?= $session_bgy_id ?>;
const SELF_URL    = window.location.pathname;

// Risk level → CSS class mapping
const RISK_CLASS = {
  'High Susceptible':           'risk-high-susceptible',
  'Moderate Susceptible':       'risk-moderate-susceptible',
  'Low Susceptible':            'risk-low-susceptible',
  'Prone':                      'risk-prone',
  'Generally Susceptible':      'risk-generally-susceptible',
  'PEIS VIII Very destructive': 'risk-peis-viii',
  'PEIS VII Destructive':       'risk-peis-vii',
  'General Inundation':         'risk-general-inundation',
  'Not Susceptible':            'risk-not-susceptible',
};

function getRiskBadge(risk) {
  const cls = RISK_CLASS[risk] || 'bg-secondary';
  return `<span class="badge-risk \${cls}">\${escHtml(risk)}</span>`;
}

function escHtml(s) {
  if (!s && s !== 0) return '—';
  return String(s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function fmtNum(n) {
  if (n === null || n === undefined || n === '') return '—';
  return Number(n).toLocaleString();
}

// =========================================================================
// TABLE LOADING
// =========================================================================
function loadTable() {
  const search  = $('#searchInput').val().toLowerCase().trim();
  const bgyId   = IS_ADMIN ? $('#barangayFilter').val() : SESSION_BGY;

  const params = { action: 'get_hazard_zones' };
  if (bgyId) params.barangay_id = bgyId;

  $.ajax({
    url: SELF_URL,
    method: 'GET',
    data: params,
    dataType: 'json',
    success: function(rows) {
      // Filter client-side by search term
      const filtered = rows.filter(r => {
        if (!search) return true;
        const haystack = [r.hazard_type_name, r.barangay_name, r.risk_level, r.description].join(' ').toLowerCase();
        return haystack.includes(search);
      });

      updateStats(rows);  // stats use unfiltered data from same endpoint
      renderTable(filtered);
    },
    error: function() {
      $('#hazardTableBody').html('<tr><td colspan="6" class="text-center text-danger py-3"><i class="fas fa-exclamation-circle me-1"></i>Failed to load data</td></tr>');
    }
  });
}

function renderTable(rows) {
  if (!rows.length) {
    $('#hazardTableBody').html('<tr><td colspan="6" class="text-center text-muted py-4">No hazard zones found.</td></tr>');
    $('#tableFooter').text('');
    return;
  }

  let html = '';
  rows.forEach(r => {
    const dot   = r.hazard_type_color
      ? `<span class="hz-dot me-1" style="background:\${escHtml(r.hazard_type_color)};"></span>`
      : '';
    const icon  = r.hazard_type_icon
      ? `<i class="\${escHtml(r.hazard_type_icon)} me-1" style="color:\${escHtml(r.hazard_type_color)};"></i>`
      : dot;

    html += `<tr>
      <td>
        <div class="d-flex align-items-center gap-1">
          \${icon}\${escHtml(r.hazard_type_name)}
        </div>
      </td>
      <td>\${escHtml(r.barangay_name)}</td>
      <td>\${getRiskBadge(r.risk_level)}</td>
      <td>\${r.area_km2 ? fmtNum(r.area_km2) : '—'}</td>
      <td>
        \${fmtNum(r.affected_population)}
        <i class="fas fa-info-circle text-muted ms-1"
           data-bs-toggle="tooltip"
           title="Auto-computed from barangay household data"
           style="cursor:pointer;font-size:.75rem;"></i>
      </td>
      <td class="text-center">
        <button class="btn btn-sm btn-outline-primary py-0 px-2 me-1" onclick="openEditModal(\${r.id})" title="Edit">
          <i class="fas fa-pen"></i>
        </button>
        <button class="btn btn-sm btn-outline-danger py-0 px-2" onclick="confirmDelete(\${r.id}, '\${escHtml(r.hazard_type_name)} — \${escHtml(r.barangay_name)}')" title="Delete">
          <i class="fas fa-trash"></i>
        </button>
      </td>
    </tr>`;
  });

  $('#hazardTableBody').html(html);
  $('#tableFooter').text(`Showing \${rows.length} record\${rows.length !== 1 ? 's' : ''}`);

  // Re-init tooltips
  $('[data-bs-toggle="tooltip"]').each(function() {
    new bootstrap.Tooltip(this, { trigger: 'hover' });
  });
}

function updateStats(rows) {
  $('#statTotal').text(rows.length);
  $('#statHigh').text(rows.filter(r => r.risk_level === 'High Susceptible').length);
  $('#statMod').text(rows.filter(r => r.risk_level === 'Moderate Susceptible').length);
  const totalPop = rows.reduce((s, r) => s + (parseInt(r.affected_population) || 0), 0);
  $('#statPop').text(fmtNum(totalPop));
}

// Debounced search
let searchTimer;
$('#searchInput').on('input', function() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(loadTable, 300);
});
$('#barangayFilter').on('change', loadTable);

// =========================================================================
// LEAFLET MAP
// =========================================================================
let hazardMap = null;
let marker    = null;
let tileLayer = null;

const TILES = {
  osm: {
    url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    attr: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
  },
  satellite: {
    url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
    attr: '&copy; Esri &mdash; Source: Esri, DigitalGlobe',
  },
  terrain: {
    url: 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
    attr: '&copy; <a href="https://opentopomap.org">OpenTopoMap</a>',
  },
  hybrid: {
    url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}',
    attr: '&copy; Esri',
  },
};

const DEFAULT_CENTER = [12.84, 120.85]; // Sablayan, Occidental Mindoro
const DEFAULT_ZOOM   = 11;

function initMap() {
  if (hazardMap) {
    hazardMap.invalidateSize();
    return;
  }
  hazardMap = L.map('hazardMap', { zoomControl: true }).setView(DEFAULT_CENTER, DEFAULT_ZOOM);

  const t = TILES.osm;
  tileLayer = L.tileLayer(t.url, { attribution: t.attr, maxZoom: 19 }).addTo(hazardMap);

  hazardMap.on('click', function(e) {
    const lat = e.latlng.lat.toFixed(6);
    const lng = e.latlng.lng.toFixed(6);
    setMapCoords(lat, lng);
  });
}

function setMapCoords(lat, lng) {
  const latF = parseFloat(lat);
  const lngF = parseFloat(lng);
  if (isNaN(latF) || isNaN(lngF)) return;

  $('#hzCoordinates').val(latF.toFixed(6) + ', ' + lngF.toFixed(6));

  if (marker) {
    marker.setLatLng([latF, lngF]);
  } else {
    marker = L.marker([latF, lngF], { draggable: true }).addTo(hazardMap);
    marker.on('dragend', function() {
      const p = marker.getLatLng();
      $('#hzCoordinates').val(p.lat.toFixed(6) + ', ' + p.lng.toFixed(6));
    });
  }
  hazardMap.setView([latF, lngF], Math.max(hazardMap.getZoom(), 13));
}

function switchTile(name) {
  if (!hazardMap || !TILES[name]) return;
  if (tileLayer) hazardMap.removeLayer(tileLayer);
  const t = TILES[name];
  tileLayer = L.tileLayer(t.url, { attribution: t.attr, maxZoom: 19 }).addTo(hazardMap);

  $('.tile-btn').removeClass('active btn-outline-primary').addClass('btn-outline-secondary');
  $(\`.tile-btn[data-tile="\${name}"]\`).addClass('active btn-outline-primary').removeClass('btn-outline-secondary');
}

$(document).on('click', '.tile-btn', function() {
  switchTile($(this).data('tile'));
});

// Goto coords button
$('#btnGotoCoords').on('click', function() {
  const val = $('#hzCoordinates').val().trim();
  const parts = val.split(',');
  if (parts.length === 2) {
    setMapCoords(parts[0].trim(), parts[1].trim());
  }
});

// =========================================================================
// MODAL — ADD
// =========================================================================
function openAddModal() {
  resetForm();
  $('#hazardModalLabel').html('<i class="fas fa-plus me-2"></i>Add Hazard Zone');
  $('#hzId').val('');

  const modal = new bootstrap.Modal('#hazardModal');
  modal.show();

  // Init map after modal is shown
  $('#hazardModal').one('shown.bs.modal', function() {
    initMap();
    if (hazardMap) hazardMap.setView(DEFAULT_CENTER, DEFAULT_ZOOM);
    if (marker) { hazardMap.removeLayer(marker); marker = null; }

    // Load barangay population for staff (fixed barangay)
    if (!IS_ADMIN && SESSION_BGY) {
      loadBarangayPopulation(SESSION_BGY);
    }
  });
}

// =========================================================================
// MODAL — EDIT
// =========================================================================
function openEditModal(id) {
  resetForm();
  $('#hazardModalLabel').html('<i class="fas fa-pen me-2"></i>Edit Hazard Zone');

  $.ajax({
    url: SELF_URL,
    method: 'GET',
    data: { action: 'get_hazard_zone', id: id },
    dataType: 'json',
    success: function(row) {
      if (row.success === false) {
        showToast(row.message || 'Could not load record.', 'danger'); return;
      }
      populateForm(row);
      const modal = new bootstrap.Modal('#hazardModal');
      modal.show();

      $('#hazardModal').one('shown.bs.modal', function() {
        initMap();
        if (row.coordinates) {
          const parts = row.coordinates.split(',');
          if (parts.length === 2) {
            setMapCoords(parts[0].trim(), parts[1].trim());
          }
        } else {
          if (marker) { hazardMap.removeLayer(marker); marker = null; }
          hazardMap.setView(DEFAULT_CENTER, DEFAULT_ZOOM);
        }
      });
    },
    error: function() { showToast('Failed to load hazard zone.', 'danger'); }
  });
}

function populateForm(row) {
  $('#hzId').val(row.id);
  $('#hzHazardTypeId').val(row.hazard_type_id);
  if (IS_ADMIN) $('#hzBarangayId').val(row.barangay_id);
  $('#hzRiskLevel').val(row.risk_level);
  $('#hzArea').val(row.area_km2 || '');
  $('#hzDescription').val(row.description || '');
  $('#hzCoordinates').val(row.coordinates || '');
  $('#hzPolygonGeojson').val(row.polygon_geojson || '');

  // Load barangay population
  const bid = IS_ADMIN ? row.barangay_id : SESSION_BGY;
  if (bid) loadBarangayPopulation(bid);
}

function resetForm() {
  const form = document.getElementById('hazardForm');
  form.reset();
  form.classList.remove('was-validated');
  $('#hzId').val('');
  $('#hzPolygonGeojson').val('');
  $('#popDisplay').html('<span class="text-muted">— select a barangay —</span>');
}

// =========================================================================
// BARANGAY POPULATION (dynamic fetch)
// =========================================================================
function loadBarangayPopulation(barangay_id) {
  if (!barangay_id) {
    $('#popDisplay').html('<span class="text-muted">— select a barangay —</span>');
    return;
  }
  $.ajax({
    url: SELF_URL,
    method: 'GET',
    data: { action: 'get_barangay_population', barangay_id: barangay_id },
    dataType: 'json',
    success: function(res) {
      const pop = parseInt(res.population) || 0;
      $('#popDisplay').html(
        `<span class="text-success fw-bold">\${fmtNum(pop)}</span> <small class="text-muted">residents</small>`
      );
    },
    error: function() {
      $('#popDisplay').html('<span class="text-danger">Could not load</span>');
    }
  });
}

// Admin: watch barangay select change
$(document).on('change', '#hzBarangayId', function() {
  if (IS_ADMIN) loadBarangayPopulation($(this).val());
});

// =========================================================================
// SAVE
// =========================================================================
$('#btnSaveHazard').on('click', function() {
  const form = document.getElementById('hazardForm');

  // Bootstrap validation
  form.classList.add('was-validated');
  if (!form.checkValidity()) return;

  const data = {
    action:           'save_hazard_zone',
    id:               $('#hzId').val(),
    hazard_type_id:   $('#hzHazardTypeId').val(),
    barangay_id:      IS_ADMIN ? $('#hzBarangayId').val() : SESSION_BGY,
    risk_level:       $('#hzRiskLevel').val(),
    area_km2:         $('#hzArea').val(),
    description:      $('#hzDescription').val(),
    coordinates:      $('#hzCoordinates').val(),
    polygon_geojson:  $('#hzPolygonGeojson').val(),
  };

  const btn = $(this);
  btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Saving…');

  $.ajax({
    url:      SELF_URL,
    method:   'POST',
    data:     data,
    dataType: 'json',
    success: function(res) {
      if (res.success) {
        showToast(res.message, 'success');
        bootstrap.Modal.getInstance(document.getElementById('hazardModal')).hide();
        loadTable();
      } else {
        showToast(res.message || 'Save failed.', 'danger');
      }
    },
    error: function() {
      showToast('Server error. Please try again.', 'danger');
    },
    complete: function() {
      btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i>Save Hazard Zone');
    }
  });
});

// =========================================================================
// DELETE
// =========================================================================
let deleteTargetId = null;

function confirmDelete(id, label) {
  deleteTargetId = id;
  $('#deleteZoneName').text(label);
  new bootstrap.Modal('#deleteModal').show();
}

$('#btnConfirmDelete').on('click', function() {
  if (!deleteTargetId) return;
  const btn = $(this);
  btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Deleting…');

  $.ajax({
    url:      SELF_URL,
    method:   'POST',
    data:     { action: 'delete_hazard_zone', id: deleteTargetId },
    dataType: 'json',
    success: function(res) {
      if (res.success) {
        showToast(res.message, 'success');
        bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
        loadTable();
      } else {
        showToast(res.message || 'Delete failed.', 'danger');
      }
    },
    error: function() {
      showToast('Server error. Please try again.', 'danger');
    },
    complete: function() {
      btn.prop('disabled', false).html('<i class="fas fa-trash me-1"></i>Delete');
      deleteTargetId = null;
    }
  });
});

// =========================================================================
// INIT
// =========================================================================
$(document).ready(function() {
  // Init tooltips on stats row
  $('[data-bs-toggle="tooltip"]').each(function() {
    new bootstrap.Tooltip(this, { trigger: 'hover' });
  });

  loadTable();
});
</script>
JS;
?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
