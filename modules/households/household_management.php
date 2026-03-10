<?php
/**
 * modules/households/household_management.php
 * Household Management — DRMS
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/sync.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// ── Auth ──────────────────────────────────────────────────────────────────────
if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}
$role        = $_SESSION['role']        ?? '';
$sessionBrgy = $_SESSION['barangay_id'] ?? null;

if (!in_array($role, ['admin', 'barangay_staff'])) {
    http_response_code(403);
    echo '<h3>Access Denied</h3>';
    exit;
}

// ── AJAX handlers (before any HTML) ──────────────────────────────────────────
$action = $_REQUEST['action'] ?? '';

if ($action !== '') {
    header('Content-Type: application/json');

    // ── helper ────────────────────────────────────────────────────────────────
    function json_out($data) { echo json_encode($data); exit; }

    function validate_gps($lat, $lng) {
        if ($lat === null || $lng === null || ($lat == 0 && $lng == 0)) return false;
        $lat = (float)$lat; $lng = (float)$lng;
        return ($lat >= GPS_LAT_MIN && $lat <= GPS_LAT_MAX &&
                $lng >= GPS_LNG_MIN && $lng <= GPS_LNG_MAX);
    }

    // ── 1. get_households ─────────────────────────────────────────────────────
    if ($action === 'get_households') {
        $brgy_filter = null;
        if ($role === 'barangay_staff') {
            $brgy_filter = $sessionBrgy;
        } elseif (!empty($_GET['barangay_id'])) {
            $brgy_filter = (int)$_GET['barangay_id'];
        }

        $sql = "SELECT h.*, b.name AS barangay_name
                FROM households h
                LEFT JOIN barangays b ON b.id = h.barangay_id";
        $params = [];
        if ($brgy_filter) {
            $sql .= " WHERE h.barangay_id = ?";
            $params[] = $brgy_filter;
        }
        $sql .= " ORDER BY h.household_head ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        json_out($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // ── 2. get_household ──────────────────────────────────────────────────────
    if ($action === 'get_household') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) json_out(['success' => false, 'message' => 'No ID provided.']);
        $stmt = $pdo->prepare("SELECT h.*, b.name AS barangay_name
                               FROM households h
                               LEFT JOIN barangays b ON b.id = h.barangay_id
                               WHERE h.id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) json_out(['success' => false, 'message' => 'Household not found.']);
        // staff can only view their own barangay
        if ($role === 'barangay_staff' && $row['barangay_id'] != $sessionBrgy) {
            json_out(['success' => false, 'message' => 'Access denied.']);
        }
        json_out($row);
    }

    // ── 3. save_household ─────────────────────────────────────────────────────
    if ($action === 'save_household') {
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;

        $barangay_id = $role === 'barangay_staff'
            ? (int)$sessionBrgy
            : (int)($_POST['barangay_id'] ?? 0);

        $lat = isset($_POST['latitude'])  && $_POST['latitude']  !== '' ? (float)$_POST['latitude']  : null;
        $lng = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;

        if ($lat !== null && $lng !== null) {
            if (!validate_gps($lat, $lng)) {
                json_out(['success' => false, 'message' => 'Invalid GPS coordinates. Must be within Sablayan bounds.']);
            }
        }

        $fields = [
            'household_head'       => trim($_POST['household_head']       ?? ''),
            'barangay_id'          => $barangay_id,
            'zone'                 => trim($_POST['zone']                 ?? ''),
            'sitio_purok_zone'     => trim($_POST['zone']                 ?? ''),
            'sex'                  => $_POST['sex']                       ?? '',
            'age'                  => is_numeric($_POST['age'] ?? '') ? (int)$_POST['age'] : null,
            'gender'               => $_POST['gender']                    ?? '',
            'house_type'           => trim($_POST['house_type']           ?? ''),
            'hh_id'                => trim($_POST['hh_id']                ?? ''),
            'ip_non_ip'            => $_POST['ip_non_ip']                 ?? '',
            'educational_attainment' => trim($_POST['educational_attainment'] ?? ''),
            'preparedness_kit'     => $_POST['preparedness_kit']          ?? '',
            'family_members'       => is_numeric($_POST['family_members'] ?? '') ? (int)$_POST['family_members'] : 0,
            'pwd_count'            => (int)($_POST['pwd_count']           ?? 0),
            'pregnant_count'       => (int)($_POST['pregnant_count']      ?? 0),
            'senior_count'         => (int)($_POST['senior_count']        ?? 0),
            'infant_count'         => (int)($_POST['infant_count']        ?? 0),
            'minor_count'          => (int)($_POST['minor_count']         ?? 0),
            'child_count'          => (int)($_POST['child_count']         ?? 0),
            'adolescent_count'     => (int)($_POST['adolescent_count']    ?? 0),
            'young_adult_count'    => (int)($_POST['young_adult_count']   ?? 0),
            'adult_count'          => (int)($_POST['adult_count']         ?? 0),
            'middle_aged_count'    => (int)($_POST['middle_aged_count']   ?? 0),
            'latitude'             => $lat,
            'longitude'            => $lng,
        ];

        if (empty($fields['household_head'])) {
            json_out(['success' => false, 'message' => 'Household head name is required.']);
        }
        if (!$fields['barangay_id']) {
            json_out(['success' => false, 'message' => 'Barangay is required.']);
        }
        if ($fields['family_members'] < 1) {
            json_out(['success' => false, 'message' => 'Family members must be at least 1.']);
        }

        try {
            if ($id) {
                // UPDATE
                if ($role === 'barangay_staff') {
                    $chk = $pdo->prepare("SELECT barangay_id FROM households WHERE id = ? LIMIT 1");
                    $chk->execute([$id]);
                    $existing = $chk->fetch(PDO::FETCH_ASSOC);
                    if (!$existing || $existing['barangay_id'] != $sessionBrgy) {
                        json_out(['success' => false, 'message' => 'Access denied.']);
                    }
                }
                $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($fields)));
                $stmt = $pdo->prepare("UPDATE households SET $set, updated_at = NOW() WHERE id = :__id");
                $params = $fields;
                $params['__id'] = $id;
                $stmt->execute($params);
                handle_sync($pdo, $barangay_id);
                json_out(['success' => true, 'message' => 'Household updated successfully.', 'id' => $id]);
            } else {
                // INSERT
                $cols   = implode(', ', array_keys($fields));
                $placeholders = implode(', ', array_map(fn($k) => ":$k", array_keys($fields)));
                $stmt = $pdo->prepare("INSERT INTO households ($cols, created_at, updated_at)
                                       VALUES ($placeholders, NOW(), NOW())");
                $stmt->execute($fields);
                $newId = (int)$pdo->lastInsertId();
                handle_sync($pdo, $barangay_id);
                json_out(['success' => true, 'message' => 'Household added successfully.', 'id' => $newId]);
            }
        } catch (PDOException $e) {
            json_out(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }

    // ── 4. delete_household ───────────────────────────────────────────────────
    if ($action === 'delete_household') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) json_out(['success' => false, 'message' => 'No ID provided.']);

        $stmt = $pdo->prepare("SELECT barangay_id FROM households WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) json_out(['success' => false, 'message' => 'Household not found.']);

        if ($role === 'barangay_staff' && $row['barangay_id'] != $sessionBrgy) {
            json_out(['success' => false, 'message' => 'Access denied.']);
        }

        try {
            $del = $pdo->prepare("DELETE FROM households WHERE id = ?");
            $del->execute([$id]);
            handle_sync($pdo, $row['barangay_id']);
            json_out(['success' => true, 'message' => 'Household deleted successfully.']);
        } catch (PDOException $e) {
            json_out(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }

    // ── 5. get_summary ────────────────────────────────────────────────────────
    if ($action === 'get_summary') {
        $brgy_filter = null;
        if ($role === 'barangay_staff') {
            $brgy_filter = $sessionBrgy;
        } elseif (!empty($_GET['barangay_id'])) {
            $brgy_filter = (int)$_GET['barangay_id'];
        }

        $sql = "SELECT
                    COUNT(*)                                             AS total_households,
                    COALESCE(SUM(family_members), 0)                    AS total_population,
                    COALESCE(SUM(pwd_count), 0)                        AS pwd_count,
                    COALESCE(SUM(senior_count), 0)                     AS senior_count,
                    COALESCE(SUM(minor_count) + SUM(child_count), 0)   AS children_count,
                    COUNT(CASE WHEN ip_non_ip = 'IP' THEN 1 END)       AS ip_count
                FROM households";
        $params = [];
        if ($brgy_filter) {
            $sql .= " WHERE barangay_id = ?";
            $params[] = $brgy_filter;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        json_out($stmt->fetch(PDO::FETCH_ASSOC));
    }

    json_out(['success' => false, 'message' => 'Unknown action.']);
}

// ── Fetch barangays for dropdowns ─────────────────────────────────────────────
$barangays = $pdo->query("SELECT id, name, coordinates FROM barangays ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Household Management';
$extraHead = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />';
require_once BASE_PATH . '/includes/header.php';
require_once BASE_PATH . '/includes/sidebar.php';
?>

<!-- ── MAIN CONTENT ─────────────────────────────────────────────────────────── -->
<div class="main-content">

  <!-- Topbar -->
  <div class="topbar">
    <div class="d-flex align-items-center gap-2">
      <i class="fas fa-house-user text-primary"></i>
      <h5 class="mb-0 fw-semibold">Household Management</h5>
    </div>
    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-primary btn-sm" id="btnAddHousehold">
        <i class="fas fa-plus me-1"></i> Add Household
      </button>
    </div>
  </div>

  <div class="p-3 p-md-4">

    <!-- Summary Cards -->
    <div class="row g-3 mb-4" id="summaryCards">
      <div class="col-6 col-md">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
              <i class="fas fa-home text-primary"></i>
            </div>
            <div>
              <div class="text-muted small">Total Households</div>
              <div class="fw-bold fs-5" id="stat_total_households">—</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
              <i class="fas fa-users text-success"></i>
            </div>
            <div>
              <div class="text-muted small">Total Population</div>
              <div class="fw-bold fs-5" id="stat_total_population">—</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-circle bg-danger bg-opacity-10 d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
              <i class="fas fa-wheelchair text-danger"></i>
            </div>
            <div>
              <div class="text-muted small">PWD</div>
              <div class="fw-bold fs-5" id="stat_pwd_count">—</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-circle bg-warning bg-opacity-10 d-flex align-items-center justify-content-center" style="width:44px;height:44px;">
              <i class="fas fa-user-clock text-warning"></i>
            </div>
            <div>
              <div class="text-muted small">Senior Citizens</div>
              <div class="fw-bold fs-5" id="stat_senior_count">—</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-circle bg-purple bg-opacity-10 d-flex align-items-center justify-content-center" style="width:44px;height:44px;background:rgba(111,66,193,.1);">
              <i class="fas fa-feather-alt" style="color:#6f42c1;"></i>
            </div>
            <div>
              <div class="text-muted small">IP Households</div>
              <div class="fw-bold fs-5" id="stat_ip_count">—</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3" id="hhTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-list-btn" data-bs-toggle="tab" data-bs-target="#tab-list" type="button" role="tab">
          <i class="fas fa-list me-1"></i> Household List
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-gps-btn" data-bs-toggle="tab" data-bs-target="#tab-gps" type="button" role="tab">
          <i class="fas fa-satellite-dish me-1"></i> GPS Quality
        </button>
      </li>
    </ul>

    <div class="tab-content">

      <!-- Tab 1: Household List -->
      <div class="tab-pane fade show active" id="tab-list" role="tabpanel">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white border-bottom py-3">
            <div class="row g-2 align-items-center">
              <?php if ($role === 'admin'): ?>
              <div class="col-auto">
                <select class="form-select form-select-sm" id="filterBarangay" style="min-width:180px;">
                  <option value="">All Barangays</option>
                  <?php foreach ($barangays as $b): ?>
                  <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <?php endif; ?>
              <div class="col">
                <input type="text" class="form-control form-control-sm" id="searchHousehold" placeholder="Search household head, zone, HH ID…">
              </div>
              <div class="col-auto">
                <button class="btn btn-outline-secondary btn-sm" id="btnRefreshTable">
                  <i class="fas fa-sync-alt"></i>
                </button>
              </div>
            </div>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover table-sm align-middle mb-0" id="householdsTable">
                <thead class="table-light">
                  <tr>
                    <th class="ps-3">HH ID</th>
                    <th>Household Head</th>
                    <th>Barangay</th>
                    <th>Zone / Sitio</th>
                    <th class="text-center">Members</th>
                    <th class="text-center">Vulnerability</th>
                    <th class="text-center">GPS Status</th>
                    <th class="text-center pe-3">Actions</th>
                  </tr>
                </thead>
                <tbody id="hhTableBody">
                  <tr><td colspan="8" class="text-center py-4 text-muted">
                    <i class="fas fa-spinner fa-spin me-2"></i> Loading…
                  </td></tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="card-footer bg-white text-muted small py-2 px-3">
            Showing <span id="hhCount">0</span> households
          </div>
        </div>
      </div>

      <!-- Tab 2: GPS Quality -->
      <div class="tab-pane fade" id="tab-gps" role="tabpanel">
        <div class="card border-0 shadow-sm p-4 text-center">
          <i class="fas fa-map-marker-alt fa-3x text-secondary mb-3"></i>
          <h5>GPS Quality Report</h5>
          <p class="text-muted">View detailed GPS validation statistics and coordinates mapping for all households.</p>
          <a href="<?= BASE_URL ?>modules/households/gps_quality_report.php" class="btn btn-primary">
            <i class="fas fa-external-link-alt me-1"></i> Open GPS Quality Report
          </a>
        </div>
      </div>

    </div><!-- /tab-content -->
  </div>
</div><!-- /main-content -->


<!-- ═══════════════════════════════════════════════════════════════════════════
     ADD / EDIT MODAL
════════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="hhModal" tabindex="-1" aria-labelledby="hhModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="hhModalLabel"><i class="fas fa-house-user me-2"></i> Add Household</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <form id="hhForm" novalidate>
          <input type="hidden" id="hh_id_hidden" name="id" value="">

          <div class="accordion accordion-flush" id="hhAccordion">

            <!-- ── Section 1: Household Information ───────────────────────── -->
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button bg-light fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#sec1">
                  <i class="fas fa-id-card me-2 text-primary"></i> Section 1 — Household Information
                </button>
              </h2>
              <div id="sec1" class="accordion-collapse collapse show" data-bs-parent="#hhAccordion">
                <div class="accordion-body">
                  <div class="row g-3">

                    <div class="col-md-4">
                      <label class="form-label small fw-semibold">HH ID</label>
                      <input type="text" class="form-control form-control-sm" name="hh_id" id="f_hh_id" placeholder="e.g. BR-001">
                    </div>
                    <div class="col-md-8">
                      <label class="form-label small fw-semibold">Household Head Name <span class="text-danger">*</span></label>
                      <input type="text" class="form-control form-control-sm" name="household_head" id="f_household_head" required placeholder="Full name">
                    </div>

                    <?php if ($role === 'admin'): ?>
                    <div class="col-md-6">
                      <label class="form-label small fw-semibold">Barangay <span class="text-danger">*</span></label>
                      <select class="form-select form-select-sm" name="barangay_id" id="f_barangay_id" required>
                        <option value="">— Select Barangay —</option>
                        <?php foreach ($barangays as $b): ?>
                        <option value="<?= $b['id'] ?>" data-coords="<?= htmlspecialchars($b['coordinates'] ?? '') ?>">
                          <?= htmlspecialchars($b['name']) ?>
                        </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <?php else: ?>
                    <input type="hidden" name="barangay_id" id="f_barangay_id" value="<?= (int)$sessionBrgy ?>">
                    <div class="col-md-6">
                      <label class="form-label small fw-semibold">Barangay</label>
                      <input type="text" class="form-control form-control-sm bg-light"
                        value="<?php
                          foreach ($barangays as $b) {
                            if ($b['id'] == $sessionBrgy) { echo htmlspecialchars($b['name']); break; }
                          }
                        ?>" readonly>
                    </div>
                    <?php endif; ?>

                    <div class="col-md-6">
                      <label class="form-label small fw-semibold">Zone / Sitio / Purok</label>
                      <input type="text" class="form-control form-control-sm" name="zone" id="f_zone" placeholder="e.g. Purok 3, Sitio Bato">
                    </div>

                    <div class="col-md-3">
                      <label class="form-label small fw-semibold">Sex</label>
                      <select class="form-select form-select-sm" name="sex" id="f_sex">
                        <option value="">— Select —</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                      </select>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label small fw-semibold">Age</label>
                      <input type="number" class="form-control form-control-sm" name="age" id="f_age" min="0" max="120" placeholder="Age">
                    </div>
                    <div class="col-md-3">
                      <label class="form-label small fw-semibold">Gender</label>
                      <select class="form-select form-select-sm" name="gender" id="f_gender">
                        <option value="">— Select —</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                      </select>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label small fw-semibold">IP / Non-IP</label>
                      <select class="form-select form-select-sm" name="ip_non_ip" id="f_ip_non_ip">
                        <option value="">— Select —</option>
                        <option value="IP">IP</option>
                        <option value="Non-IP">Non-IP</option>
                      </select>
                    </div>

                    <div class="col-md-6">
                      <label class="form-label small fw-semibold">House Type</label>
                      <input type="text" class="form-control form-control-sm" name="house_type" id="f_house_type" placeholder="e.g. Concrete, Wood, Mixed">
                    </div>
                    <div class="col-md-3">
                      <label class="form-label small fw-semibold">Educational Attainment</label>
                      <input type="text" class="form-control form-control-sm" name="educational_attainment" id="f_educational_attainment" placeholder="e.g. College">
                    </div>
                    <div class="col-md-3">
                      <label class="form-label small fw-semibold">Preparedness Kit</label>
                      <select class="form-select form-select-sm" name="preparedness_kit" id="f_preparedness_kit">
                        <option value="">— Select —</option>
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                      </select>
                    </div>

                  </div>
                </div>
              </div>
            </div>

            <!-- ── Section 2: Family Composition ─────────────────────────── -->
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed bg-light fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#sec2">
                  <i class="fas fa-users me-2 text-success"></i> Section 2 — Family Composition
                </button>
              </h2>
              <div id="sec2" class="accordion-collapse collapse" data-bs-parent="#hhAccordion">
                <div class="accordion-body">
                  <div class="row g-3">

                    <div class="col-12">
                      <label class="form-label small fw-semibold">Total Family Members <span class="text-danger">*</span></label>
                      <input type="number" class="form-control form-control-sm" name="family_members" id="f_family_members" required min="1" placeholder="e.g. 5">
                    </div>

                    <?php
                    $countFields = [
                        ['name'=>'pwd_count',         'label'=>'PWD',             'icon'=>'fas fa-wheelchair',      'color'=>'danger'],
                        ['name'=>'pregnant_count',    'label'=>'Pregnant',        'icon'=>'fas fa-baby',            'color'=>'pink',   'style'=>'color:#d63384;'],
                        ['name'=>'senior_count',      'label'=>'Senior (60+)',    'icon'=>'fas fa-user-clock',      'color'=>'warning'],
                        ['name'=>'infant_count',      'label'=>'Infant (0-2)',    'icon'=>'fas fa-baby-carriage',   'color'=>'info'],
                        ['name'=>'minor_count',       'label'=>'Minor',           'icon'=>'fas fa-child',           'color'=>'secondary'],
                        ['name'=>'child_count',       'label'=>'Child',           'icon'=>'fas fa-child',           'color'=>'primary'],
                        ['name'=>'adolescent_count',  'label'=>'Adolescent',      'icon'=>'fas fa-user-graduate',   'color'=>'info'],
                        ['name'=>'young_adult_count', 'label'=>'Young Adult',     'icon'=>'fas fa-user-tie',        'color'=>'success'],
                        ['name'=>'adult_count',       'label'=>'Adult',           'icon'=>'fas fa-user',            'color'=>'primary'],
                        ['name'=>'middle_aged_count', 'label'=>'Middle-Aged',     'icon'=>'fas fa-user-circle',     'color'=>'secondary'],
                    ];
                    foreach (array_chunk($countFields, 2) as $pair):
                    ?>
                    <div class="col-md-6">
                      <div class="row g-2">
                        <?php foreach ($pair as $cf): ?>
                        <div class="col-6">
                          <label class="form-label small fw-semibold">
                            <i class="<?= $cf['icon'] ?> text-<?= $cf['color'] ?>" <?= isset($cf['style']) ? 'style="'.$cf['style'].'"' : '' ?>></i>
                            <?= $cf['label'] ?>
                          </label>
                          <input type="number" class="form-control form-control-sm" name="<?= $cf['name'] ?>" id="f_<?= $cf['name'] ?>" min="0" value="0">
                        </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                    <?php endforeach; ?>

                  </div>
                </div>
              </div>
            </div>

            <!-- ── Section 3: GPS Coordinates ────────────────────────────── -->
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed bg-light fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#sec3">
                  <i class="fas fa-map-marker-alt me-2 text-danger"></i> Section 3 — GPS Coordinates
                </button>
              </h2>
              <div id="sec3" class="accordion-collapse collapse" data-bs-parent="#hhAccordion">
                <div class="accordion-body">

                  <div id="gpsAlert" class="alert alert-danger d-none py-2 small">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    <span id="gpsAlertMsg">Coordinates outside Sablayan bounds.</span>
                  </div>

                  <!-- GPS sub-tabs -->
                  <ul class="nav nav-pills nav-sm mb-3" id="gpsTabs" role="tablist">
                    <li class="nav-item me-1">
                      <button class="nav-link active btn-sm px-3 py-1" id="gps-map-tab" data-bs-toggle="pill" data-bs-target="#gps-map-pane" type="button">
                        <i class="fas fa-map me-1"></i> Click Map
                      </button>
                    </li>
                    <li class="nav-item me-1">
                      <button class="nav-link btn-sm px-3 py-1" id="gps-device-tab" data-bs-toggle="pill" data-bs-target="#gps-device-pane" type="button">
                        <i class="fas fa-crosshairs me-1"></i> Device GPS
                      </button>
                    </li>
                    <li class="nav-item">
                      <button class="nav-link btn-sm px-3 py-1" id="gps-manual-tab" data-bs-toggle="pill" data-bs-target="#gps-manual-pane" type="button">
                        <i class="fas fa-keyboard me-1"></i> Manual Input
                      </button>
                    </li>
                  </ul>

                  <div class="tab-content" id="gpsTabContent">

                    <!-- Click Map -->
                    <div class="tab-pane fade show active" id="gps-map-pane" role="tabpanel">
                      <p class="text-muted small mb-2"><i class="fas fa-info-circle me-1"></i> Click anywhere on the map to drop a marker and auto-fill coordinates.</p>
                      <!-- Tile switcher -->
                      <div class="d-flex gap-1 mb-2" id="tileSwitcher">
                        <button type="button" class="btn btn-outline-secondary btn-sm active" data-tile="osm">OSM</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-tile="satellite">Satellite</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-tile="terrain">Terrain</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-tile="hybrid">Hybrid</button>
                      </div>
                      <div id="hhMap" style="height:300px; border-radius:8px; border:1px solid #dee2e6; z-index:0;"></div>
                    </div>

                    <!-- Device GPS -->
                    <div class="tab-pane fade" id="gps-device-pane" role="tabpanel">
                      <p class="text-muted small mb-3"><i class="fas fa-info-circle me-1"></i> Click below to use your device's GPS sensor to auto-fill coordinates.</p>
                      <button type="button" class="btn btn-success" id="btnGetGPS">
                        <i class="fas fa-crosshairs me-2"></i> Get My Location
                      </button>
                      <div id="gpsStatus" class="mt-2 small text-muted"></div>
                    </div>

                    <!-- Manual Input -->
                    <div class="tab-pane fade" id="gps-manual-pane" role="tabpanel">
                      <p class="text-muted small mb-3">
                        <i class="fas fa-info-circle me-1"></i>
                        Enter coordinates manually. Valid range: Lat <?= GPS_LAT_MIN ?>–<?= GPS_LAT_MAX ?>, Lng <?= GPS_LNG_MIN ?>–<?= GPS_LNG_MAX ?>
                      </p>
                      <div class="row g-3">
                        <div class="col-md-6">
                          <label class="form-label small fw-semibold">Latitude</label>
                          <input type="number" class="form-control" id="manualLat" step="0.000001"
                            placeholder="e.g. 12.8372" min="<?= GPS_LAT_MIN ?>" max="<?= GPS_LAT_MAX ?>">
                        </div>
                        <div class="col-md-6">
                          <label class="form-label small fw-semibold">Longitude</label>
                          <input type="number" class="form-control" id="manualLng" step="0.000001"
                            placeholder="e.g. 120.7654" min="<?= GPS_LNG_MIN ?>" max="<?= GPS_LNG_MAX ?>">
                        </div>
                      </div>
                      <button type="button" class="btn btn-primary btn-sm mt-3" id="btnApplyManual">
                        <i class="fas fa-check me-1"></i> Apply Coordinates
                      </button>
                    </div>

                  </div><!-- /gpsTabContent -->

                  <!-- Coordinate display -->
                  <div class="row g-3 mt-2">
                    <div class="col-md-6">
                      <label class="form-label small fw-semibold">Latitude</label>
                      <input type="text" class="form-control form-control-sm bg-light" id="dispLat" readonly placeholder="—">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label small fw-semibold">Longitude</label>
                      <input type="text" class="form-control form-control-sm bg-light" id="dispLng" readonly placeholder="—">
                    </div>
                  </div>

                  <!-- Hidden inputs for actual submission -->
                  <input type="hidden" name="latitude"  id="f_latitude">
                  <input type="hidden" name="longitude" id="f_longitude">

                </div>
              </div>
            </div>

          </div><!-- /accordion -->
        </form>
      </div>
      <div class="modal-footer">
        <div id="formError" class="text-danger small me-auto d-none"><i class="fas fa-exclamation-circle me-1"></i><span></span></div>
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary btn-sm" id="btnSaveHousehold">
          <i class="fas fa-save me-1"></i> Save Household
        </button>
      </div>
    </div>
  </div>
</div>


<!-- ═══════════════════════════════════════════════════════════════════════════
     VIEW MODAL
════════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title" id="viewModalLabel"><i class="fas fa-eye me-2"></i> Household Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="viewModalBody">
        <div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>


<!-- ═══════════════════════════════════════════════════════════════════════════
     DELETE CONFIRM MODAL
════════════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h6 class="modal-title" id="deleteModalLabel"><i class="fas fa-trash me-2"></i> Confirm Delete</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-1">Delete household of:</p>
        <p class="fw-bold" id="deleteHouseholdName">—</p>
        <p class="text-muted small mb-0">This action cannot be undone.</p>
      </div>
      <div class="modal-footer">
        <input type="hidden" id="deleteHouseholdId">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger btn-sm" id="btnConfirmDelete">
          <i class="fas fa-trash me-1"></i> Delete
        </button>
      </div>
    </div>
  </div>
</div>


<!-- ═══════════════════════════════════════════════════════════════════════════
     JAVASCRIPT
════════════════════════════════════════════════════════════════════════════ -->
<?php
$extraFooter = ob_start() ? '' : '';
ob_start();
?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function ($) {
  'use strict';

  // ── Constants ──────────────────────────────────────────────────────────────
  const GPS_LAT_MIN = <?= GPS_LAT_MIN ?>;
  const GPS_LAT_MAX = <?= GPS_LAT_MAX ?>;
  const GPS_LNG_MIN = <?= GPS_LNG_MIN ?>;
  const GPS_LNG_MAX = <?= GPS_LNG_MAX ?>;
  const IS_ADMIN = <?= $role === 'admin' ? 'true' : 'false' ?>;
  const SESSION_BRGY = <?= $sessionBrgy ? (int)$sessionBrgy : 'null' ?>;

  // Default center (mid of Sablayan bounds)
  const DEFAULT_CENTER = [12.85, 120.85];

  // Barangay coordinate lookup (for map centering)
  const BRGY_COORDS = {
    <?php foreach ($barangays as $b): ?>
    <?= $b['id'] ?>: "<?= addslashes($b['coordinates'] ?? '') ?>",
    <?php endforeach; ?>
  };

  // ── Leaflet map instance ───────────────────────────────────────────────────
  let hhMap = null;
  let hhMarker = null;
  let mapInitialized = false;

  const tileLayers = {};

  function initMap(centerLat, centerLng) {
    if (hhMap) {
      hhMap.remove();
      hhMap = null;
      hhMarker = null;
      mapInitialized = false;
    }

    const center = [centerLat || DEFAULT_CENTER[0], centerLng || DEFAULT_CENTER[1]];
    hhMap = L.map('hhMap', { zoomControl: true }).setView(center, 13);

    // Tile layers
    tileLayers.osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap contributors', maxZoom: 19
    });
    tileLayers.satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
      attribution: 'Tiles © Esri', maxZoom: 19
    });
    tileLayers.terrain = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenTopoMap contributors', maxZoom: 17
    });
    tileLayers.hybrid = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}', {
      attribution: 'Tiles © Esri', maxZoom: 19
    });

    tileLayers.osm.addTo(hhMap);

    // Tile switcher buttons
    $('#tileSwitcher button').removeClass('active');
    $('#tileSwitcher [data-tile="osm"]').addClass('active');

    $('#tileSwitcher button').on('click', function () {
      const t = $(this).data('tile');
      $('#tileSwitcher button').removeClass('active');
      $(this).addClass('active');
      Object.values(tileLayers).forEach(l => hhMap.removeLayer(l));
      tileLayers[t].addTo(hhMap);
    });

    // Click to place marker
    hhMap.on('click', function (e) {
      placeMarker(e.latlng.lat, e.latlng.lng, true);
    });

    mapInitialized = true;

    // If existing coordinates, drop marker
    const existLat = parseFloat($('#f_latitude').val());
    const existLng = parseFloat($('#f_longitude').val());
    if (!isNaN(existLat) && !isNaN(existLng) && existLat !== 0 && existLng !== 0) {
      placeMarker(existLat, existLng, false);
      hhMap.setView([existLat, existLng], 15);
    }
  }

  function placeMarker(lat, lng, pan) {
    if (!hhMap) return;
    if (hhMarker) {
      hhMarker.setLatLng([lat, lng]);
    } else {
      hhMarker = L.marker([lat, lng], { draggable: true }).addTo(hhMap);
      hhMarker.on('dragend', function () {
        const pos = hhMarker.getLatLng();
        setCoords(pos.lat, pos.lng);
      });
    }
    if (pan) hhMap.panTo([lat, lng]);
    setCoords(lat, lng);
  }

  function setCoords(lat, lng) {
    const latR = parseFloat(lat.toFixed(6));
    const lngR = parseFloat(lng.toFixed(6));
    $('#f_latitude').val(latR);
    $('#f_longitude').val(lngR);
    $('#dispLat').val(latR);
    $('#dispLng').val(lngR);
    $('#manualLat').val(latR);
    $('#manualLng').val(lngR);
    validateGPS(latR, lngR);
  }

  function validateGPS(lat, lng) {
    const lat_f = parseFloat(lat);
    const lng_f = parseFloat(lng);
    if (isNaN(lat_f) || isNaN(lng_f)) {
      showGpsAlert('No coordinates set. GPS is optional but must be valid if provided.');
      return false;
    }
    if (lat_f === 0 && lng_f === 0) {
      showGpsAlert('Coordinates (0,0) are not valid.');
      return false;
    }
    if (lat_f < GPS_LAT_MIN || lat_f > GPS_LAT_MAX || lng_f < GPS_LNG_MIN || lng_f > GPS_LNG_MAX) {
      showGpsAlert(`Coordinates out of bounds. Lat: ${GPS_LAT_MIN}–${GPS_LAT_MAX}, Lng: ${GPS_LNG_MIN}–${GPS_LNG_MAX}`);
      return false;
    }
    hideGpsAlert();
    return true;
  }

  function showGpsAlert(msg) {
    $('#gpsAlertMsg').text(msg);
    $('#gpsAlert').removeClass('d-none');
  }

  function hideGpsAlert() {
    $('#gpsAlert').addClass('d-none');
  }

  function getBrgyCenter(brgyId) {
    const coords = BRGY_COORDS[brgyId] || '';
    if (!coords) return DEFAULT_CENTER;
    const parts = coords.split(',');
    if (parts.length === 2) {
      const lat = parseFloat(parts[0]);
      const lng = parseFloat(parts[1]);
      if (!isNaN(lat) && !isNaN(lng)) return [lat, lng];
    }
    return DEFAULT_CENTER;
  }

  // ── Summary Cards ──────────────────────────────────────────────────────────
  function loadSummary(brgyId) {
    let url = '?action=get_summary';
    if (brgyId) url += '&barangay_id=' + brgyId;
    $.getJSON(url, function (d) {
      $('#stat_total_households').text(parseInt(d.total_households || 0).toLocaleString());
      $('#stat_total_population').text(parseInt(d.total_population || 0).toLocaleString());
      $('#stat_pwd_count').text(parseInt(d.pwd_count || 0).toLocaleString());
      $('#stat_senior_count').text(parseInt(d.senior_count || 0).toLocaleString());
      $('#stat_ip_count').text(parseInt(d.ip_count || 0).toLocaleString());
    });
  }

  // ── Table ──────────────────────────────────────────────────────────────────
  let allHouseholds = [];

  function loadTable(brgyId) {
    let url = '?action=get_households';
    if (brgyId) url += '&barangay_id=' + brgyId;
    $('#hhTableBody').html('<tr><td colspan="8" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-2"></i> Loading…</td></tr>');
    $.getJSON(url, function (data) {
      allHouseholds = data;
      renderTable(data);
    }).fail(function () {
      $('#hhTableBody').html('<tr><td colspan="8" class="text-center py-3 text-danger">Failed to load data.</td></tr>');
    });
  }

  function gpsStatus(lat, lng) {
    const la = parseFloat(lat), lo = parseFloat(lng);
    if (!lat && !lng) return '<span class="badge bg-warning text-dark"><i class="fas fa-question-circle me-1"></i>Missing</span>';
    if (la === 0 && lo === 0) return '<span class="badge bg-warning text-dark"><i class="fas fa-question-circle me-1"></i>Missing</span>';
    if (la >= GPS_LAT_MIN && la <= GPS_LAT_MAX && lo >= GPS_LNG_MIN && lo <= GPS_LNG_MAX) {
      return '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Valid</span>';
    }
    return '<span class="badge bg-danger"><i class="fas fa-times me-1"></i>Invalid</span>';
  }

  function vulnIcons(h) {
    let html = '';
    if (parseInt(h.pwd_count) > 0)      html += `<span class="badge bg-danger me-1" title="PWD: ${h.pwd_count}"><i class="fas fa-wheelchair"></i> ${h.pwd_count}</span>`;
    if (parseInt(h.senior_count) > 0)   html += `<span class="badge bg-warning text-dark me-1" title="Senior: ${h.senior_count}"><i class="fas fa-user-clock"></i> ${h.senior_count}</span>`;
    if (parseInt(h.infant_count) > 0)   html += `<span class="badge bg-info me-1" title="Infant: ${h.infant_count}"><i class="fas fa-baby-carriage"></i> ${h.infant_count}</span>`;
    if (parseInt(h.pregnant_count) > 0) html += `<span class="badge me-1" style="background:#d63384;color:#fff;" title="Pregnant: ${h.pregnant_count}"><i class="fas fa-baby"></i> ${h.pregnant_count}</span>`;
    if ((h.ip_non_ip || '').toUpperCase() === 'IP') html += `<span class="badge me-1" style="background:#6f42c1;color:#fff;" title="Indigenous People"><i class="fas fa-feather-alt"></i> IP</span>`;
    return html || '<span class="text-muted small">—</span>';
  }

  function renderTable(data) {
    const search = ($('#searchHousehold').val() || '').toLowerCase();
    const filtered = search
      ? data.filter(h =>
          (h.household_head || '').toLowerCase().includes(search) ||
          (h.hh_id || '').toLowerCase().includes(search) ||
          (h.zone || '').toLowerCase().includes(search) ||
          (h.sitio_purok_zone || '').toLowerCase().includes(search) ||
          (h.barangay_name || '').toLowerCase().includes(search)
        )
      : data;

    if (!filtered.length) {
      $('#hhTableBody').html('<tr><td colspan="8" class="text-center py-4 text-muted"><i class="fas fa-inbox fa-2x d-block mb-2"></i>No households found.</td></tr>');
      $('#hhCount').text(0);
      return;
    }

    let html = '';
    filtered.forEach(function (h) {
      const zone = h.sitio_purok_zone || h.zone || '—';
      const hhDisplay = h.hh_id ? `<span class="badge bg-light text-dark border">${esc(h.hh_id)}</span>` : `<span class="text-muted small">#${h.id}</span>`;
      html += `
      <tr>
        <td class="ps-3">${hhDisplay}</td>
        <td><span class="fw-semibold">${esc(h.household_head || '—')}</span></td>
        <td>${esc(h.barangay_name || '—')}</td>
        <td class="text-muted small">${esc(zone)}</td>
        <td class="text-center"><span class="badge bg-light text-dark border">${parseInt(h.family_members || 0)}</span></td>
        <td class="text-center" style="white-space:nowrap;">${vulnIcons(h)}</td>
        <td class="text-center">${gpsStatus(h.latitude, h.longitude)}</td>
        <td class="text-center pe-3" style="white-space:nowrap;">
          <button class="btn btn-outline-info btn-sm py-0 px-2 me-1 btnView" data-id="${h.id}" title="View">
            <i class="fas fa-eye"></i>
          </button>
          <button class="btn btn-outline-primary btn-sm py-0 px-2 me-1 btnEdit" data-id="${h.id}" title="Edit">
            <i class="fas fa-edit"></i>
          </button>
          <button class="btn btn-outline-danger btn-sm py-0 px-2 btnDelete" data-id="${h.id}" data-name="${esc(h.household_head || '')}" title="Delete">
            <i class="fas fa-trash"></i>
          </button>
        </td>
      </tr>`;
    });
    $('#hhTableBody').html(html);
    $('#hhCount').text(filtered.length);
  }

  function esc(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  // ── Modal helpers ──────────────────────────────────────────────────────────
  function resetForm() {
    $('#hhForm')[0].reset();
    $('#hh_id_hidden').val('');
    $('#f_latitude').val('');
    $('#f_longitude').val('');
    $('#dispLat').val('');
    $('#dispLng').val('');
    $('#manualLat').val('');
    $('#manualLng').val('');
    hideGpsAlert();
    $('#formError').addClass('d-none');
    // Reset count fields to 0
    $('[id^="f_"][type="number"]').each(function() {
      if ($(this).attr('min') === '0') $(this).val(0);
    });
  }

  function openAddModal() {
    resetForm();
    $('#hhModalLabel').html('<i class="fas fa-plus-circle me-2"></i> Add Household');
    // Open first section
    $('#sec1').collapse('show');

    // Center map on barangay if staff
    let center = DEFAULT_CENTER;
    if (!IS_ADMIN && SESSION_BRGY) {
      center = getBrgyCenter(SESSION_BRGY);
    }
    $('#hhModal').modal('show');
    // Init map after modal shown
    $('#hhModal').one('shown.bs.modal', function () {
      initMap(center[0], center[1]);
      if (hhMap) hhMap.invalidateSize();
    });
  }

  function openEditModal(id) {
    resetForm();
    $('#hhModalLabel').html('<i class="fas fa-edit me-2"></i> Edit Household');
    $.getJSON('?action=get_household&id=' + id, function (h) {
      if (h.success === false) { showToast(h.message, 'danger'); return; }
      // Fill form
      $('#hh_id_hidden').val(h.id);
      $('#f_hh_id').val(h.hh_id || '');
      $('#f_household_head').val(h.household_head || '');
      if (IS_ADMIN) $('#f_barangay_id').val(h.barangay_id || '');
      $('#f_zone').val(h.sitio_purok_zone || h.zone || '');
      $('#f_sex').val(h.sex || '');
      $('#f_age').val(h.age || '');
      $('#f_gender').val(h.gender || '');
      $('#f_house_type').val(h.house_type || '');
      $('#f_ip_non_ip').val(h.ip_non_ip || '');
      $('#f_educational_attainment').val(h.educational_attainment || '');
      $('#f_preparedness_kit').val(h.preparedness_kit || '');
      $('#f_family_members').val(h.family_members || 0);
      $('#f_pwd_count').val(h.pwd_count || 0);
      $('#f_pregnant_count').val(h.pregnant_count || 0);
      $('#f_senior_count').val(h.senior_count || 0);
      $('#f_infant_count').val(h.infant_count || 0);
      $('#f_minor_count').val(h.minor_count || 0);
      $('#f_child_count').val(h.child_count || 0);
      $('#f_adolescent_count').val(h.adolescent_count || 0);
      $('#f_young_adult_count').val(h.young_adult_count || 0);
      $('#f_adult_count').val(h.adult_count || 0);
      $('#f_middle_aged_count').val(h.middle_aged_count || 0);
      // GPS
      if (h.latitude && h.longitude && !(parseFloat(h.latitude) === 0 && parseFloat(h.longitude) === 0)) {
        $('#f_latitude').val(h.latitude);
        $('#f_longitude').val(h.longitude);
        $('#dispLat').val(h.latitude);
        $('#dispLng').val(h.longitude);
      }

      $('#hhModal').modal('show');
      $('#hhModal').one('shown.bs.modal', function () {
        const lat = parseFloat(h.latitude) || DEFAULT_CENTER[0];
        const lng = parseFloat(h.longitude) || DEFAULT_CENTER[1];
        const brgyId = h.barangay_id;
        const center = (h.latitude && h.longitude && !(lat === 0 && lng === 0))
          ? [lat, lng]
          : getBrgyCenter(brgyId);
        initMap(center[0], center[1]);
        if (hhMap) hhMap.invalidateSize();
      });
    });
  }

  // ── View Modal ─────────────────────────────────────────────────────────────
  function openViewModal(id) {
    $('#viewModalBody').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>');
    $('#viewModal').modal('show');
    $.getJSON('?action=get_household&id=' + id, function (h) {
      if (h.success === false) { $('#viewModalBody').html('<p class="text-danger text-center py-3">' + esc(h.message) + '</p>'); return; }

      const gpsHtml = (h.latitude && h.longitude && !(parseFloat(h.latitude) === 0 && parseFloat(h.longitude) === 0))
        ? `<span class="badge bg-success me-2"><i class="fas fa-check me-1"></i>Valid GPS</span>
           <code>${h.latitude}, ${h.longitude}</code>`
        : '<span class="badge bg-warning text-dark"><i class="fas fa-question-circle me-1"></i>No GPS Data</span>';

      const row = (label, val) => `<tr><th class="text-muted fw-normal small" style="width:40%">${label}</th><td class="fw-semibold small">${val || '—'}</td></tr>`;

      $('#viewModalBody').html(`
        <div class="p-3">
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
              <h5 class="mb-0">${esc(h.household_head)}</h5>
              <small class="text-muted">${esc(h.barangay_name || '')} · ${esc(h.sitio_purok_zone || h.zone || 'No zone')}</small>
            </div>
            ${h.hh_id ? `<span class="badge bg-primary fs-6">${esc(h.hh_id)}</span>` : `<span class="badge bg-secondary">#${h.id}</span>`}
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <div class="card border-0 bg-light h-100">
                <div class="card-body p-3">
                  <h6 class="text-primary mb-2"><i class="fas fa-id-card me-1"></i> Basic Info</h6>
                  <table class="table table-sm table-borderless mb-0">
                    ${row('Barangay', esc(h.barangay_name))}
                    ${row('Zone/Sitio', esc(h.sitio_purok_zone || h.zone))}
                    ${row('Sex', esc(h.sex))}
                    ${row('Age', h.age)}
                    ${row('Gender', esc(h.gender))}
                    ${row('House Type', esc(h.house_type))}
                    ${row('IP/Non-IP', esc(h.ip_non_ip))}
                    ${row('Education', esc(h.educational_attainment))}
                    ${row('Preparedness Kit', esc(h.preparedness_kit))}
                  </table>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="card border-0 bg-light h-100">
                <div class="card-body p-3">
                  <h6 class="text-success mb-2"><i class="fas fa-users me-1"></i> Family Composition</h6>
                  <table class="table table-sm table-borderless mb-0">
                    ${row('Total Members', `<strong>${h.family_members || 0}</strong>`)}
                    ${parseInt(h.pwd_count)>0 ? row('<span class="text-danger"><i class="fas fa-wheelchair me-1"></i>PWD</span>', h.pwd_count) : ''}
                    ${parseInt(h.senior_count)>0 ? row('<span class="text-warning"><i class="fas fa-user-clock me-1"></i>Senior (60+)</span>', h.senior_count) : ''}
                    ${parseInt(h.infant_count)>0 ? row('<span class="text-info"><i class="fas fa-baby-carriage me-1"></i>Infant</span>', h.infant_count) : ''}
                    ${parseInt(h.pregnant_count)>0 ? row('<span style="color:#d63384;"><i class="fas fa-baby me-1"></i>Pregnant</span>', h.pregnant_count) : ''}
                    ${parseInt(h.minor_count)>0 ? row('Minor', h.minor_count) : ''}
                    ${parseInt(h.child_count)>0 ? row('Child', h.child_count) : ''}
                    ${parseInt(h.adolescent_count)>0 ? row('Adolescent', h.adolescent_count) : ''}
                    ${parseInt(h.young_adult_count)>0 ? row('Young Adult', h.young_adult_count) : ''}
                    ${parseInt(h.adult_count)>0 ? row('Adult', h.adult_count) : ''}
                    ${parseInt(h.middle_aged_count)>0 ? row('Middle-Aged', h.middle_aged_count) : ''}
                  </table>
                </div>
              </div>
            </div>
          </div>

          <div class="card border-0 bg-light mt-3">
            <div class="card-body p-3">
              <h6 class="text-danger mb-2"><i class="fas fa-map-marker-alt me-1"></i> GPS Coordinates</h6>
              ${gpsHtml}
            </div>
          </div>

          <div class="text-muted small mt-3">
            Created: ${h.created_at || '—'} &nbsp;|&nbsp; Updated: ${h.updated_at || '—'}
          </div>
        </div>
      `);
    });
  }

  // ── Save handler ───────────────────────────────────────────────────────────
  $('#btnSaveHousehold').on('click', function () {
    const $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Saving…');
    $('#formError').addClass('d-none');

    const head = $('#f_household_head').val().trim();
    if (!head) {
      showFormError('Household Head Name is required.');
      resetBtn($btn); return;
    }

    const fm = parseInt($('#f_family_members').val());
    if (!fm || fm < 1) {
      showFormError('Family members must be at least 1.');
      resetBtn($btn); return;
    }

    const lat = $('#f_latitude').val();
    const lng = $('#f_longitude').val();

    // GPS: if provided, validate; if empty, allow (GPS optional)
    if (lat !== '' && lng !== '') {
      if (!validateGPS(parseFloat(lat), parseFloat(lng))) {
        showFormError('Invalid GPS coordinates. Must be within Sablayan bounds.');
        resetBtn($btn); return;
      }
    }

    const formData = $('#hhForm').serialize();

    $.ajax({
      url: '?action=save_household',
      method: 'POST',
      data: formData,
      dataType: 'json',
      success: function (res) {
        if (res.success) {
          showToast(res.message, 'success');
          $('#hhModal').modal('hide');
          const brgy = IS_ADMIN ? ($('#filterBarangay').val() || '') : '';
          loadTable(brgy);
          loadSummary(brgy);
        } else {
          showFormError(res.message);
        }
      },
      error: function () {
        showFormError('Server error. Please try again.');
      },
      complete: function () {
        resetBtn($btn);
      }
    });
  });

  function showFormError(msg) {
    $('#formError').removeClass('d-none').find('span').text(msg);
  }

  function resetBtn($btn) {
    $btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Household');
  }

  // ── Delete handler ─────────────────────────────────────────────────────────
  $(document).on('click', '.btnDelete', function () {
    $('#deleteHouseholdId').val($(this).data('id'));
    $('#deleteHouseholdName').text($(this).data('name') || '—');
    $('#deleteModal').modal('show');
  });

  $('#btnConfirmDelete').on('click', function () {
    const id = $('#deleteHouseholdId').val();
    const $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>');
    $.ajax({
      url: window.location.pathname,
      method: 'POST',
      data: { action: 'delete_household', id: id },
      dataType: 'json',
      success: function (res) {
        if (res.success) {
          showToast(res.message, 'success');
          $('#deleteModal').modal('hide');
          const brgy = IS_ADMIN ? ($('#filterBarangay').val() || '') : '';
          loadTable(brgy);
          loadSummary(brgy);
        } else {
          showToast(res.message, 'danger');
        }
      },
      error: function () { showToast('Server error.', 'danger'); },
      complete: function () {
        $btn.prop('disabled', false).html('<i class="fas fa-trash me-1"></i> Delete');
      }
    });
  });

  // ── Event bindings ─────────────────────────────────────────────────────────
  $('#btnAddHousehold').on('click', function () { openAddModal(); });

  $(document).on('click', '.btnEdit', function () { openEditModal($(this).data('id')); });
  $(document).on('click', '.btnView', function () { openViewModal($(this).data('id')); });

  $('#searchHousehold').on('keyup', function () { renderTable(allHouseholds); });

  if (IS_ADMIN) {
    $('#filterBarangay').on('change', function () {
      const brgy = $(this).val();
      loadTable(brgy);
      loadSummary(brgy);
    });
  }

  $('#btnRefreshTable').on('click', function () {
    const brgy = IS_ADMIN ? ($('#filterBarangay').val() || '') : '';
    loadTable(brgy);
    loadSummary(brgy);
  });

  // Barangay change → update map center for add modal
  if (IS_ADMIN) {
    $('#f_barangay_id').on('change', function () {
      if (!mapInitialized || !hhMap) return;
      const coords = $(this).find(':selected').data('coords') || '';
      if (!coords) return;
      const parts = coords.split(',');
      if (parts.length === 2) {
        const lat = parseFloat(parts[0]);
        const lng = parseFloat(parts[1]);
        if (!isNaN(lat) && !isNaN(lng)) hhMap.setView([lat, lng], 13);
      }
    });
  }

  // Device GPS
  $('#btnGetGPS').on('click', function () {
    if (!navigator.geolocation) {
      $('#gpsStatus').html('<span class="text-danger">Geolocation not supported by this browser.</span>');
      return;
    }
    const $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> Getting location…');
    $('#gpsStatus').html('<span class="text-muted">Requesting GPS signal…</span>');
    navigator.geolocation.getCurrentPosition(
      function (pos) {
        const lat = pos.coords.latitude;
        const lng = pos.coords.longitude;
        placeMarker(lat, lng, true);
        $('#gpsStatus').html(`<span class="text-success"><i class="fas fa-check me-1"></i>Location acquired: ${lat.toFixed(6)}, ${lng.toFixed(6)}</span>`);
        $btn.prop('disabled', false).html('<i class="fas fa-crosshairs me-2"></i> Get My Location');
        // Switch to map tab to show marker
        $('#gps-map-tab').trigger('click');
      },
      function (err) {
        $('#gpsStatus').html('<span class="text-danger"><i class="fas fa-times me-1"></i>Error: ' + err.message + '</span>');
        $btn.prop('disabled', false).html('<i class="fas fa-crosshairs me-2"></i> Get My Location');
      },
      { enableHighAccuracy: true, timeout: 15000 }
    );
  });

  // Manual input → apply
  $('#btnApplyManual').on('click', function () {
    const lat = parseFloat($('#manualLat').val());
    const lng = parseFloat($('#manualLng').val());
    if (isNaN(lat) || isNaN(lng)) {
      showGpsAlert('Please enter valid numeric coordinates.');
      return;
    }
    placeMarker(lat, lng, true);
    // Switch to map tab
    $('#gps-map-tab').trigger('click');
    if (hhMap) hhMap.invalidateSize();
  });

  // When GPS tab shown, invalidate map
  $('button[data-bs-toggle="pill"]').on('shown.bs.tab', function () {
    if ($(this).attr('id') === 'gps-map-tab' && hhMap) {
      setTimeout(function () { hhMap.invalidateSize(); }, 100);
    }
  });

  // When accordion section 3 opens, invalidate map
  document.getElementById('sec3').addEventListener('shown.bs.collapse', function () {
    if (hhMap) setTimeout(function () { hhMap.invalidateSize(); }, 150);
  });

  // Clean up map when modal hidden
  $('#hhModal').on('hidden.bs.modal', function () {
    if (hhMap) { hhMap.remove(); hhMap = null; hhMarker = null; mapInitialized = false; }
  });

  // ── Init ───────────────────────────────────────────────────────────────────
  const initBrgy = IS_ADMIN ? '' : SESSION_BRGY;
  loadTable(initBrgy);
  loadSummary(initBrgy);

})(jQuery);
</script>
<?php
$extraFooter = ob_get_clean();
require_once BASE_PATH . '/includes/footer.php';
?>
