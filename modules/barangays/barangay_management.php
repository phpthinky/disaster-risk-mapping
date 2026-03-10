<?php
/**
 * modules/barangays/barangay_management.php
 * Admin-only: Barangay Management (list, add, edit, delete)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/sync.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// --- Admin guard ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die('403 Forbidden: Admin access required.');
}

// =========================================================
// AJAX handlers — must come BEFORE any HTML output
// =========================================================
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    $action = $_POST['action'];

    // --------------------------------------------------
    // ADD BARANGAY
    // --------------------------------------------------
    if ($action === 'add_barangay') {
        $name             = trim($_POST['name'] ?? '');
        $area_km2         = isset($_POST['area_km2']) && $_POST['area_km2'] !== '' ? floatval($_POST['area_km2']) : null;
        $coordinates      = trim($_POST['coordinates'] ?? '');
        $assigned_user_id = isset($_POST['assigned_user_id']) && $_POST['assigned_user_id'] !== '' ? intval($_POST['assigned_user_id']) : null;

        if ($name === '') {
            echo json_encode(['success' => false, 'message' => 'Barangay name is required.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO barangays (name, area_km2, coordinates, created_at)
                 VALUES (:name, :area_km2, :coordinates, NOW())'
            );
            $stmt->execute([
                ':name'        => $name,
                ':area_km2'    => $area_km2,
                ':coordinates' => $coordinates !== '' ? $coordinates : null,
            ]);
            $newId = (int)$pdo->lastInsertId();

            if ($assigned_user_id) {
                $uStmt = $pdo->prepare('UPDATE users SET barangay_id = :bid WHERE id = :uid');
                $uStmt->execute([':bid' => $newId, ':uid' => $assigned_user_id]);
            }

            echo json_encode(['success' => true, 'message' => 'Barangay added successfully.', 'id' => $newId]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }

    // --------------------------------------------------
    // EDIT BARANGAY
    // --------------------------------------------------
    if ($action === 'edit_barangay') {
        $id               = intval($_POST['id'] ?? 0);
        $name             = trim($_POST['name'] ?? '');
        $area_km2         = isset($_POST['area_km2']) && $_POST['area_km2'] !== '' ? floatval($_POST['area_km2']) : null;
        $coordinates      = trim($_POST['coordinates'] ?? '');
        $assigned_user_id = isset($_POST['assigned_user_id']) && $_POST['assigned_user_id'] !== '' ? intval($_POST['assigned_user_id']) : null;

        if ($id <= 0 || $name === '') {
            echo json_encode(['success' => false, 'message' => 'Invalid data.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare(
                'UPDATE barangays
                    SET name = :name, area_km2 = :area_km2, coordinates = :coordinates
                  WHERE id = :id'
            );
            $stmt->execute([
                ':name'        => $name,
                ':area_km2'    => $area_km2,
                ':coordinates' => $coordinates !== '' ? $coordinates : null,
                ':id'          => $id,
            ]);

            // Clear existing staff assignment for this barangay, then re-assign
            $clearStmt = $pdo->prepare('UPDATE users SET barangay_id = NULL WHERE barangay_id = :bid');
            $clearStmt->execute([':bid' => $id]);

            if ($assigned_user_id) {
                $uStmt = $pdo->prepare('UPDATE users SET barangay_id = :bid WHERE id = :uid');
                $uStmt->execute([':bid' => $id, ':uid' => $assigned_user_id]);
            }

            echo json_encode(['success' => true, 'message' => 'Barangay updated successfully.']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }

    // --------------------------------------------------
    // DELETE BARANGAY
    // --------------------------------------------------
    if ($action === 'delete_barangay') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid barangay ID.']);
            exit;
        }

        try {
            // Check for linked households
            $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM households WHERE barangay_id = :id');
            $checkStmt->execute([':id' => $id]);
            $householdCount = (int)$checkStmt->fetchColumn();

            if ($householdCount > 0) {
                echo json_encode([
                    'success' => false,
                    'message' => "Cannot delete: {$householdCount} household(s) are linked to this barangay."
                ]);
                exit;
            }

            // Unlink any staff
            $unlinkStmt = $pdo->prepare('UPDATE users SET barangay_id = NULL WHERE barangay_id = :id');
            $unlinkStmt->execute([':id' => $id]);

            $delStmt = $pdo->prepare('DELETE FROM barangays WHERE id = :id');
            $delStmt->execute([':id' => $id]);

            echo json_encode(['success' => true, 'message' => 'Barangay deleted successfully.']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    // --------------------------------------------------
    // GET BARANGAY (for edit modal)
    // --------------------------------------------------
    if ($_GET['action'] === 'get_barangay') {
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('SELECT * FROM barangays WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $barangay = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$barangay) {
                echo json_encode(['success' => false, 'message' => 'Barangay not found.']);
                exit;
            }

            // Get assigned user
            $uStmt = $pdo->prepare(
                'SELECT id, username FROM users WHERE barangay_id = :bid AND role = :role LIMIT 1'
            );
            $uStmt->execute([':bid' => $id, ':role' => 'barangay_staff']);
            $assignedUser = $uStmt->fetch(PDO::FETCH_ASSOC);

            $barangay['assigned_user_id']   = $assignedUser ? (int)$assignedUser['id'] : null;
            $barangay['assigned_username']  = $assignedUser ? $assignedUser['username'] : null;

            echo json_encode(['success' => true, 'barangay' => $barangay]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

// =========================================================
// Page data — fetched for initial HTML render
// =========================================================

// All barangays with assigned staff and household count
$barangaysStmt = $pdo->query(
    'SELECT b.*,
            u.id   AS staff_id,
            u.username AS staff_username
       FROM barangays b
       LEFT JOIN users u ON u.barangay_id = b.id AND u.role = \'barangay_staff\'
      ORDER BY b.name ASC'
);
$barangays = $barangaysStmt->fetchAll(PDO::FETCH_ASSOC);

// Summary stats
$statsStmt = $pdo->query(
    'SELECT COUNT(*) AS total_barangays,
            COALESCE(SUM(household_count), 0) AS total_households,
            COALESCE(SUM(population), 0) AS total_population,
            SUM(CASE WHEN boundary_geojson IS NOT NULL AND boundary_geojson != \'\' THEN 1 ELSE 0 END) AS boundaries_drawn
       FROM barangays'
);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Staff list for dropdowns
$staffStmt = $pdo->query(
    'SELECT id, username FROM users WHERE role = \'barangay_staff\' AND is_active = 1 ORDER BY username ASC'
);
$staffList = $staffStmt->fetchAll(PDO::FETCH_ASSOC);

// =========================================================
// HTML output
// =========================================================

$pageTitle = 'Barangay Management';
$extraHead = '';

require_once BASE_PATH . '/includes/header.php';
require_once BASE_PATH . '/includes/sidebar.php';
?>

<!-- Main Content -->
<div class="main-content">

  <!-- Topbar -->
  <div class="topbar">
    <div class="d-flex align-items-center gap-2">
      <i class="fas fa-city text-primary"></i>
      <strong>Barangay Management</strong>
    </div>
    <div class="text-muted" style="font-size:.8rem;">
      <?= htmlspecialchars($_SESSION['username'] ?? '') ?> &mdash; Admin
    </div>
  </div>

  <!-- Page Body -->
  <div class="p-4">

    <!-- Summary Stats -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary bg-opacity-10 p-3">
              <i class="fas fa-city text-primary fa-lg"></i>
            </div>
            <div>
              <div class="fs-4 fw-bold"><?= (int)$stats['total_barangays'] ?></div>
              <div class="text-muted small">Total Barangays</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-circle bg-success bg-opacity-10 p-3">
              <i class="fas fa-house-user text-success fa-lg"></i>
            </div>
            <div>
              <div class="fs-4 fw-bold"><?= number_format((int)$stats['total_households']) ?></div>
              <div class="text-muted small">Total Households</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-circle bg-info bg-opacity-10 p-3">
              <i class="fas fa-users text-info fa-lg"></i>
            </div>
            <div>
              <div class="fs-4 fw-bold"><?= number_format((int)$stats['total_population']) ?></div>
              <div class="text-muted small">Total Population</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-circle bg-warning bg-opacity-10 p-3">
              <i class="fas fa-draw-polygon text-warning fa-lg"></i>
            </div>
            <div>
              <div class="fs-4 fw-bold"><?= (int)$stats['boundaries_drawn'] ?></div>
              <div class="text-muted small">Boundaries Drawn</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Card with Barangay List -->
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between py-3">
        <h5 class="mb-0">
          <i class="fas fa-city me-2 text-primary"></i>Barangay List
        </h5>
        <button class="btn btn-primary btn-sm" id="btnAddBarangay">
          <i class="fas fa-plus me-1"></i>Add Barangay
        </button>
      </div>
      <div class="card-body">

        <!-- Search -->
        <div class="mb-3">
          <div class="input-group" style="max-width:320px;">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="text" id="barangaySearch" class="form-control" placeholder="Search barangays...">
          </div>
        </div>

        <!-- Table -->
        <div class="table-responsive">
          <table class="table table-hover align-middle" id="barangayTable">
            <thead class="table-light">
              <tr>
                <th style="width:45px;">#</th>
                <th>Barangay Name</th>
                <th>Assigned Staff</th>
                <th class="text-center">Households</th>
                <th class="text-center">Population</th>
                <th class="text-center">Boundary</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody id="barangayTableBody">
              <?php if (empty($barangays)): ?>
                <tr id="noResultsRow">
                  <td colspan="7" class="text-center text-muted py-4">
                    <i class="fas fa-inbox fa-2x d-block mb-2"></i>No barangays found.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($barangays as $i => $b): ?>
                  <tr data-name="<?= htmlspecialchars(strtolower($b['name'])) ?>">
                    <td><?= $i + 1 ?></td>
                    <td>
                      <strong><?= htmlspecialchars($b['name']) ?></strong>
                      <?php if ($b['area_km2']): ?>
                        <br><small class="text-muted"><?= number_format((float)$b['area_km2'], 2) ?> km²</small>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($b['staff_username']): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                          <i class="fas fa-user-check me-1"></i><?= htmlspecialchars($b['staff_username']) ?>
                        </span>
                      <?php else: ?>
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                          <i class="fas fa-user-slash me-1"></i>Unassigned
                        </span>
                      <?php endif; ?>
                    </td>
                    <td class="text-center"><?= number_format((int)($b['household_count'] ?? 0)) ?></td>
                    <td class="text-center"><?= number_format((int)($b['population'] ?? 0)) ?></td>
                    <td class="text-center">
                      <?php if (!empty($b['boundary_geojson'])): ?>
                        <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Drawn</span>
                      <?php else: ?>
                        <span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>Not drawn</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-end">
                      <div class="d-flex gap-1 justify-content-end">
                        <button class="btn btn-outline-primary btn-sm btn-edit"
                                data-id="<?= (int)$b['id'] ?>"
                                title="Edit">
                          <i class="fas fa-edit"></i>
                        </button>
                        <a href="<?= BASE_URL ?>modules/barangays/barangay_boundary.php?barangay_id=<?= (int)$b['id'] ?>"
                           class="btn btn-outline-success btn-sm"
                           title="Draw Boundary">
                          <i class="fas fa-draw-polygon"></i>
                        </a>
                        <button class="btn btn-outline-danger btn-sm btn-delete"
                                data-id="<?= (int)$b['id'] ?>"
                                data-name="<?= htmlspecialchars($b['name'], ENT_QUOTES) ?>"
                                title="Delete">
                          <i class="fas fa-trash"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <!-- No-results message for search -->
        <div id="searchNoResults" class="text-center text-muted py-3" style="display:none;">
          <i class="fas fa-search fa-lg d-block mb-2"></i>No barangays match your search.
        </div>

      </div>
    </div>

  </div><!-- /p-4 -->
</div><!-- /main-content -->

<!-- =====================================================
     ADD BARANGAY MODAL
     ===================================================== -->
<div class="modal fade" id="addBarangayModal" tabindex="-1" aria-labelledby="addBarangayModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="addBarangayModalLabel">
          <i class="fas fa-plus-circle me-2"></i>Add Barangay
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="addBarangayForm" novalidate>
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fw-semibold">Barangay Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="addName" name="name" required
                     placeholder="e.g. Barangay San Jose">
              <div class="invalid-feedback">Barangay name is required.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Area (km²)</label>
              <input type="number" class="form-control" id="addArea" name="area_km2"
                     step="0.01" min="0" placeholder="e.g. 12.50">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Center Coordinates</label>
              <input type="text" class="form-control" id="addCoordinates" name="coordinates"
                     placeholder="12.9000, 121.0000">
              <div class="form-text">Format: lat, lng</div>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Assign Staff</label>
              <select class="form-select" id="addAssignedUser" name="assigned_user_id">
                <option value="">-- Select Staff --</option>
                <?php foreach ($staffList as $staff): ?>
                  <option value="<?= (int)$staff['id'] ?>"><?= htmlspecialchars($staff['username']) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Only active barangay_staff accounts are shown.</div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="fas fa-times me-1"></i>Cancel
        </button>
        <button type="button" class="btn btn-primary" id="btnSaveAdd">
          <i class="fas fa-save me-1"></i>Save
        </button>
      </div>
    </div>
  </div>
</div>

<!-- =====================================================
     EDIT BARANGAY MODAL
     ===================================================== -->
<div class="modal fade" id="editBarangayModal" tabindex="-1" aria-labelledby="editBarangayModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title" id="editBarangayModalLabel">
          <i class="fas fa-edit me-2"></i>Edit Barangay
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">

        <!-- Loading spinner shown while fetching -->
        <div id="editLoadingSpinner" class="text-center py-4">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <div class="mt-2 text-muted small">Loading barangay data...</div>
        </div>

        <form id="editBarangayForm" novalidate style="display:none;">
          <input type="hidden" id="editId" name="id">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fw-semibold">Barangay Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="editName" name="name" required>
              <div class="invalid-feedback">Barangay name is required.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Area (km²)</label>
              <input type="number" class="form-control" id="editArea" name="area_km2" step="0.01" min="0">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Center Coordinates</label>
              <input type="text" class="form-control" id="editCoordinates" name="coordinates"
                     placeholder="12.9000, 121.0000">
              <div class="form-text">Format: lat, lng</div>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Assign Staff</label>
              <select class="form-select" id="editAssignedUser" name="assigned_user_id">
                <option value="">-- Select Staff --</option>
                <?php foreach ($staffList as $staff): ?>
                  <option value="<?= (int)$staff['id'] ?>"><?= htmlspecialchars($staff['username']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Computed stats (read-only) -->
            <div class="col-12">
              <hr>
              <div class="d-flex align-items-center gap-2 mb-3">
                <i class="fas fa-chart-bar text-info"></i>
                <span class="fw-semibold">Computed Statistics</span>
                <span class="badge bg-info-subtle text-info border border-info-subtle ms-1">Read-only</span>
              </div>
              <p class="text-muted small mb-3">
                <i class="fas fa-info-circle me-1"></i>
                Population figures are auto-computed from household records and cannot be manually edited.
              </p>
              <div class="row g-2">
                <div class="col-6 col-md-4">
                  <div class="card bg-light border-0">
                    <div class="card-body py-2 px-3">
                      <div class="small text-muted">Population</div>
                      <div class="fw-bold" id="editStatPopulation">—</div>
                    </div>
                  </div>
                </div>
                <div class="col-6 col-md-4">
                  <div class="card bg-light border-0">
                    <div class="card-body py-2 px-3">
                      <div class="small text-muted">Households</div>
                      <div class="fw-bold" id="editStatHouseholds">—</div>
                    </div>
                  </div>
                </div>
                <div class="col-6 col-md-4">
                  <div class="card bg-light border-0">
                    <div class="card-body py-2 px-3">
                      <div class="small text-muted">PWD</div>
                      <div class="fw-bold" id="editStatPwd">—</div>
                    </div>
                  </div>
                </div>
                <div class="col-6 col-md-4">
                  <div class="card bg-light border-0">
                    <div class="card-body py-2 px-3">
                      <div class="small text-muted">Seniors</div>
                      <div class="fw-bold" id="editStatSeniors">—</div>
                    </div>
                  </div>
                </div>
                <div class="col-6 col-md-4">
                  <div class="card bg-light border-0">
                    <div class="card-body py-2 px-3">
                      <div class="small text-muted">Infants</div>
                      <div class="fw-bold" id="editStatInfants">—</div>
                    </div>
                  </div>
                </div>
                <div class="col-6 col-md-4">
                  <div class="card bg-light border-0">
                    <div class="card-body py-2 px-3">
                      <div class="small text-muted">IP</div>
                      <div class="fw-bold" id="editStatIp">—</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="fas fa-times me-1"></i>Cancel
        </button>
        <button type="button" class="btn btn-warning" id="btnSaveEdit">
          <i class="fas fa-save me-1"></i>Update
        </button>
      </div>
    </div>
  </div>
</div>

<!-- =====================================================
     DELETE CONFIRM MODAL
     ===================================================== -->
<div class="modal fade" id="deleteBarangayModal" tabindex="-1" aria-labelledby="deleteBarangayModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="deleteBarangayModalLabel">
          <i class="fas fa-trash me-2"></i>Delete Barangay
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-1">Are you sure you want to delete:</p>
        <p class="fw-bold mb-0" id="deleteBarangayName"></p>
        <p class="text-muted small mt-2">This action cannot be undone. All associated data must be removed first.</p>
      </div>
      <div class="modal-footer">
        <input type="hidden" id="deleteBarangayId">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger btn-sm" id="btnConfirmDelete">
          <i class="fas fa-trash me-1"></i>Delete
        </button>
      </div>
    </div>
  </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>

<script>
$(function () {

  const PAGE_URL = '<?= BASE_URL ?>modules/barangays/barangay_management.php';

  // -------------------------------------------------------
  // Client-side search / filter
  // -------------------------------------------------------
  $('#barangaySearch').on('keyup', function () {
    const term = $(this).val().toLowerCase().trim();
    let visibleCount = 0;

    $('#barangayTableBody tr').each(function () {
      const name = $(this).data('name') || '';
      if (!term || name.includes(term)) {
        $(this).show();
        visibleCount++;
      } else {
        $(this).hide();
      }
    });

    if (visibleCount === 0) {
      $('#searchNoResults').show();
    } else {
      $('#searchNoResults').hide();
    }
  });

  // -------------------------------------------------------
  // ADD BARANGAY
  // -------------------------------------------------------
  $('#btnAddBarangay').on('click', function () {
    $('#addBarangayForm')[0].reset();
    $('#addBarangayForm').removeClass('was-validated');
    $('#addBarangayModal').modal('show');
  });

  $('#btnSaveAdd').on('click', function () {
    const $form = $('#addBarangayForm');
    $form.addClass('was-validated');

    if (!$form[0].checkValidity()) return;

    const $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');

    $.ajax({
      url: PAGE_URL,
      type: 'POST',
      data: {
        action: 'add_barangay',
        name: $('#addName').val().trim(),
        area_km2: $('#addArea').val(),
        coordinates: $('#addCoordinates').val().trim(),
        assigned_user_id: $('#addAssignedUser').val()
      },
      dataType: 'json',
      success: function (res) {
        if (res.success) {
          $('#addBarangayModal').modal('hide');
          showToast(res.message, 'success');
          setTimeout(function () { location.reload(); }, 800);
        } else {
          showToast(res.message || 'An error occurred.', 'danger');
        }
      },
      error: function () {
        showToast('Server error. Please try again.', 'danger');
      },
      complete: function () {
        $btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i>Save');
      }
    });
  });

  // -------------------------------------------------------
  // EDIT BARANGAY — open modal and load data
  // -------------------------------------------------------
  $(document).on('click', '.btn-edit', function () {
    const id = $(this).data('id');

    $('#editBarangayForm').hide().trigger('reset').removeClass('was-validated');
    $('#editLoadingSpinner').show();
    $('#editBarangayModal').modal('show');

    $.ajax({
      url: PAGE_URL,
      type: 'GET',
      data: { action: 'get_barangay', id: id },
      dataType: 'json',
      success: function (res) {
        if (!res.success) {
          showToast(res.message || 'Failed to load barangay.', 'danger');
          $('#editBarangayModal').modal('hide');
          return;
        }

        const b = res.barangay;

        $('#editId').val(b.id);
        $('#editName').val(b.name);
        $('#editArea').val(b.area_km2 || '');
        $('#editCoordinates').val(b.coordinates || '');
        $('#editAssignedUser').val(b.assigned_user_id || '');

        // Computed stats
        $('#editStatPopulation').text(formatNumber(b.population));
        $('#editStatHouseholds').text(formatNumber(b.household_count));
        $('#editStatPwd').text(formatNumber(b.pwd_count));
        $('#editStatSeniors').text(formatNumber(b.senior_count));
        $('#editStatInfants').text(formatNumber(b.infant_count));
        $('#editStatIp').text(formatNumber(b.ip_count));

        $('#editLoadingSpinner').hide();
        $('#editBarangayForm').show();
      },
      error: function () {
        showToast('Failed to fetch barangay data.', 'danger');
        $('#editBarangayModal').modal('hide');
      }
    });
  });

  $('#btnSaveEdit').on('click', function () {
    const $form = $('#editBarangayForm');
    $form.addClass('was-validated');

    if (!$form[0].checkValidity()) return;

    const $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');

    $.ajax({
      url: PAGE_URL,
      type: 'POST',
      data: {
        action: 'edit_barangay',
        id: $('#editId').val(),
        name: $('#editName').val().trim(),
        area_km2: $('#editArea').val(),
        coordinates: $('#editCoordinates').val().trim(),
        assigned_user_id: $('#editAssignedUser').val()
      },
      dataType: 'json',
      success: function (res) {
        if (res.success) {
          $('#editBarangayModal').modal('hide');
          showToast(res.message, 'success');
          setTimeout(function () { location.reload(); }, 800);
        } else {
          showToast(res.message || 'An error occurred.', 'danger');
        }
      },
      error: function () {
        showToast('Server error. Please try again.', 'danger');
      },
      complete: function () {
        $btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i>Update');
      }
    });
  });

  // -------------------------------------------------------
  // DELETE BARANGAY
  // -------------------------------------------------------
  $(document).on('click', '.btn-delete', function () {
    const id   = $(this).data('id');
    const name = $(this).data('name');

    $('#deleteBarangayId').val(id);
    $('#deleteBarangayName').text(name);
    $('#deleteBarangayModal').modal('show');
  });

  $('#btnConfirmDelete').on('click', function () {
    const id  = $('#deleteBarangayId').val();
    const $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Deleting...');

    $.ajax({
      url: PAGE_URL,
      type: 'POST',
      data: { action: 'delete_barangay', id: id },
      dataType: 'json',
      success: function (res) {
        $('#deleteBarangayModal').modal('hide');
        if (res.success) {
          showToast(res.message, 'success');
          setTimeout(function () { location.reload(); }, 800);
        } else {
          showToast(res.message || 'Could not delete barangay.', 'danger');
        }
      },
      error: function () {
        showToast('Server error. Please try again.', 'danger');
      },
      complete: function () {
        $btn.prop('disabled', false).html('<i class="fas fa-trash me-1"></i>Delete');
      }
    });
  });

  // -------------------------------------------------------
  // Utility
  // -------------------------------------------------------
  function formatNumber(val) {
    const n = parseInt(val, 10);
    if (isNaN(n)) return '0';
    return n.toLocaleString();
  }

});
</script>
