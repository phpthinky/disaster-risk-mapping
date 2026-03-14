<?php
// household_management.php — Barangay Staff & Admin
session_start();
require_once 'config.php';
require_once 'sync_functions.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$role = $_SESSION['role'];
$userBarangayId = $_SESSION['barangay_id'] ?? null;
if (!in_array($role, ['admin', 'barangay_staff'])) { header('Location: dashboard.php'); exit; }

// ── AJAX handlers ──────────────────────────────────────
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    // List households
    if ($_GET['ajax'] === 'list') {
        $where = '';
        $params = [];
        if ($role === 'barangay_staff' && $userBarangayId) {
            $where = 'WHERE h.barangay_id = ?';
            $params[] = $userBarangayId;
        } elseif (isset($_GET['barangay_id']) && $_GET['barangay_id'] !== '') {
            $where = 'WHERE h.barangay_id = ?';
            $params[] = $_GET['barangay_id'];
        }
        $stmt = $pdo->prepare("
            SELECT h.*, b.name AS barangay_name
            FROM households h
            JOIN barangays b ON h.barangay_id = b.id
            $where
            ORDER BY h.created_at DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Summary
        $summary = ['total_households'=>0,'total_population'=>0,'pwd'=>0,'senior'=>0,'children'=>0,'ip'=>0,'infant'=>0,'pregnant'=>0];
        foreach ($rows as $r) {
            $summary['total_households']++;
            $summary['total_population'] += (int)$r['family_members'];
            $summary['pwd'] += (int)$r['pwd_count'];
            $summary['senior'] += (int)$r['senior_count'];
            $summary['children'] += (int)$r['minor_count'] + (int)$r['child_count'];
            $summary['ip'] += ($r['ip_non_ip'] === 'IP' ? 1 : 0);
            $summary['infant'] += (int)$r['infant_count'];
            $summary['pregnant'] += (int)$r['pregnant_count'];
        }
        echo json_encode(['data' => $rows, 'summary' => $summary]);
        exit;
    }

    // Get single household
    if ($_GET['ajax'] === 'get' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT h.*, b.name AS barangay_name FROM households h JOIN barangays b ON h.barangay_id = b.id WHERE h.id = ?");
        $stmt->execute([$_GET['id']]);
        $hh = $stmt->fetch(PDO::FETCH_ASSOC);
        // Get members
        $mStmt = $pdo->prepare("SELECT * FROM household_members WHERE household_id = ?");
        $mStmt->execute([$_GET['id']]);
        $hh['members'] = $mStmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($hh);
        exit;
    }

    // Get barangays for dropdown
    if ($_GET['ajax'] === 'barangays') {
        if ($role === 'barangay_staff' && $userBarangayId) {
            $stmt = $pdo->prepare("SELECT id, name, coordinates FROM barangays WHERE id = ?");
            $stmt->execute([$userBarangayId]);
        } else {
            $stmt = $pdo->query("SELECT id, name, coordinates FROM barangays ORDER BY name ASC");
        }
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // Save household
    if ($_GET['ajax'] === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_POST['id'] ?? null;
        $barangay_id = ($role === 'barangay_staff' && $userBarangayId) ? $userBarangayId : ($_POST['barangay_id'] ?? null);
        $household_head = trim($_POST['household_head'] ?? '');
        $lat = $_POST['latitude'] ?? null;
        $lng = $_POST['longitude'] ?? null;

        if ($household_head === '' || !$barangay_id) {
            echo json_encode(['ok' => false, 'msg' => 'Household head name and barangay are required.']);
            exit;
        }
        // GPS validation
        if ($lat === '' || $lng === '' || $lat === null || $lng === null) {
            echo json_encode(['ok' => false, 'msg' => 'GPS coordinates are required.']);
            exit;
        }
        $lat = (float)$lat; $lng = (float)$lng;
        if ($lat < 12.50 || $lat > 13.20 || $lng < 120.50 || $lng > 121.20) {
            echo json_encode(['ok' => false, 'msg' => 'GPS coordinates out of valid range (Lat: 12.50-13.20, Lng: 120.50-121.20).']);
            exit;
        }
        if ($lat == 0 && $lng == 0) {
            echo json_encode(['ok' => false, 'msg' => 'Invalid GPS: 0,0 is not a valid location.']);
            exit;
        }

        $fields = [
            'household_head', 'barangay_id', 'zone', 'sex', 'age', 'gender',
            'house_type', 'family_members', 'pwd_count', 'pregnant_count',
            'senior_count', 'infant_count', 'minor_count', 'latitude', 'longitude',
            'sitio_purok_zone', 'ip_non_ip', 'hh_id', 'child_count',
            'adolescent_count', 'young_adult_count', 'adult_count',
            'middle_aged_count', 'preparedness_kit', 'educational_attainment'
        ];
        $values = [
            $household_head, $barangay_id, $_POST['zone'] ?? '', $_POST['gender'] ?? 'Male',
            (int)($_POST['age'] ?? 0), $_POST['gender'] ?? 'Male', $_POST['house_type'] ?? '',
            (int)($_POST['family_members'] ?? 1), (int)($_POST['pwd_count'] ?? 0),
            (int)($_POST['pregnant_count'] ?? 0), (int)($_POST['senior_count'] ?? 0),
            (int)($_POST['infant_count'] ?? 0), (int)($_POST['minor_count'] ?? 0),
            $lat, $lng, $_POST['sitio_purok_zone'] ?? '', $_POST['ip_non_ip'] ?? 'Non-IP',
            $_POST['hh_id'] ?? '', (int)($_POST['child_count'] ?? 0),
            (int)($_POST['adolescent_count'] ?? 0), (int)($_POST['young_adult_count'] ?? 0),
            (int)($_POST['adult_count'] ?? 0), (int)($_POST['middle_aged_count'] ?? 0),
            $_POST['preparedness_kit'] ?? 'No', $_POST['educational_attainment'] ?? ''
        ];

        try {
            $pdo->beginTransaction();
            if ($id) {
                // Update
                $sets = implode(', ', array_map(fn($f) => "$f = ?", $fields));
                $stmt = $pdo->prepare("UPDATE households SET $sets, updated_at = NOW() WHERE id = ?");
                $values[] = $id;
                $stmt->execute($values);
                // Delete old members
                $pdo->prepare("DELETE FROM household_members WHERE household_id = ?")->execute([$id]);
            } else {
                // Insert
                $placeholders = implode(', ', array_fill(0, count($fields), '?'));
                $cols = implode(', ', $fields);
                $stmt = $pdo->prepare("INSERT INTO households ($cols) VALUES ($placeholders)");
                $stmt->execute($values);
                $id = $pdo->lastInsertId();
            }
            // Insert members
            $membersJson = $_POST['members_data'] ?? '[]';
            $members = json_decode($membersJson, true) ?: [];
            if (!empty($members)) {
                $mStmt = $pdo->prepare("INSERT INTO household_members
                    (household_id, full_name, age, gender, relationship, is_pwd, is_pregnant, is_senior, is_infant, is_minor)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($members as $m) {
                    $mStmt->execute([
                        $id, $m['name'] ?? '', (int)($m['age'] ?? 0), $m['gender'] ?? 'Male',
                        $m['relationship'] ?? '', $m['is_pwd'] ? 1 : 0, $m['is_pregnant'] ? 1 : 0,
                        $m['is_senior'] ? 1 : 0, $m['is_infant'] ? 1 : 0, $m['is_minor'] ? 1 : 0
                    ]);
                }
            }
            $pdo->commit();
            // Recompute composition from members, then sync barangay
            recompute_household_composition($pdo, (int)$id);
            handle_sync($pdo, (int)$barangay_id);
            echo json_encode(['ok' => true, 'msg' => 'Household saved successfully.']);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
        }
        exit;
    }

    // Delete household
    if ($_GET['ajax'] === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("SELECT barangay_id FROM households WHERE id = ?");
        $stmt->execute([$id]);
        $bid = $stmt->fetchColumn();
        if ($bid) {
            $pdo->prepare("DELETE FROM households WHERE id = ?")->execute([$id]);
            handle_sync($pdo, (int)$bid);
            echo json_encode(['ok' => true, 'msg' => 'Household deleted.']);
        } else {
            echo json_encode(['ok' => false, 'msg' => 'Household not found.']);
        }
        exit;
    }

    // GPS quality report
    if ($_GET['ajax'] === 'gps_quality') {
        $sql = "
            SELECT h.id, h.household_head, h.latitude, h.longitude, b.name AS barangay_name, h.barangay_id,
                CASE
                    WHEN h.latitude IS NULL OR h.longitude IS NULL THEN 'missing'
                    WHEN h.latitude = 0 AND h.longitude = 0 THEN 'invalid'
                    WHEN h.latitude < 12.50 OR h.latitude > 13.20 OR h.longitude < 120.50 OR h.longitude > 121.20 THEN 'out_of_range'
                    ELSE 'valid'
                END AS gps_status
            FROM households h
            JOIN barangays b ON h.barangay_id = b.id
        ";
        if ($role === 'barangay_staff' && $userBarangayId) {
            $sql .= " WHERE h.barangay_id = " . (int)$userBarangayId;
        }
        $sql .= " ORDER BY gps_status DESC, b.name ASC";
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        // Summary per barangay
        $summary = [];
        foreach ($rows as $r) {
            $bid = $r['barangay_id'];
            if (!isset($summary[$bid])) $summary[$bid] = ['barangay_name' => $r['barangay_name'], 'total' => 0, 'valid' => 0];
            $summary[$bid]['total']++;
            if ($r['gps_status'] === 'valid') $summary[$bid]['valid']++;
        }
        echo json_encode(['data' => $rows, 'summary' => array_values($summary)]);
        exit;
    }

    // Get members for a household
    if ($_GET['ajax'] === 'members' && isset($_GET['household_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM household_members WHERE household_id = ?");
        $stmt->execute([$_GET['household_id']]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // Save single member
    if ($_GET['ajax'] === 'save_member' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $hid = (int)$_POST['household_id'];
        $mid = $_POST['member_id'] ?? null;

        if ($mid) {
            $pdo->prepare("UPDATE household_members SET full_name=?, age=?, gender=?, relationship=?, is_pwd=?, is_pregnant=?, is_senior=?, is_infant=?, is_minor=? WHERE id=?")
                ->execute([$_POST['full_name'], (int)$_POST['age'], $_POST['gender'], $_POST['relationship'],
                    $_POST['is_pwd']?1:0, $_POST['is_pregnant']?1:0, $_POST['is_senior']?1:0, $_POST['is_infant']?1:0, $_POST['is_minor']?1:0, $mid]);
        } else {
            $pdo->prepare("INSERT INTO household_members (household_id, full_name, age, gender, relationship, is_pwd, is_pregnant, is_senior, is_infant, is_minor) VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([$hid, $_POST['full_name'], (int)$_POST['age'], $_POST['gender'], $_POST['relationship'],
                    $_POST['is_pwd']?1:0, $_POST['is_pregnant']?1:0, $_POST['is_senior']?1:0, $_POST['is_infant']?1:0, $_POST['is_minor']?1:0]);
        }
        // Recompute household composition from members, then sync barangay
        $barangay_id = recompute_household_composition($pdo, $hid);
        if ($barangay_id) handle_sync($pdo, $barangay_id);

        // Return updated composition for UI refresh
        $comp = $pdo->prepare("SELECT family_members, pwd_count, pregnant_count, senior_count, infant_count, minor_count, child_count, adolescent_count, young_adult_count, adult_count, middle_aged_count FROM households WHERE id = ?");
        $comp->execute([$hid]);
        echo json_encode(['ok' => true, 'composition' => $comp->fetch(PDO::FETCH_ASSOC)]);
        exit;
    }

    // Delete member
    if ($_GET['ajax'] === 'delete_member' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $mid = (int)$_POST['member_id'];
        $m = $pdo->prepare("SELECT hm.household_id, h.barangay_id FROM household_members hm JOIN households h ON hm.household_id = h.id WHERE hm.id = ?");
        $m->execute([$mid]);
        $row = $m->fetch(PDO::FETCH_ASSOC);
        $pdo->prepare("DELETE FROM household_members WHERE id = ?")->execute([$mid]);
        if ($row) {
            // Recompute household composition from remaining members, then sync barangay
            recompute_household_composition($pdo, (int)$row['household_id']);
            handle_sync($pdo, (int)$row['barangay_id']);
            // Return updated composition for UI refresh
            $comp = $pdo->prepare("SELECT family_members, pwd_count, pregnant_count, senior_count, infant_count, minor_count, child_count, adolescent_count, young_adult_count, adult_count, middle_aged_count FROM households WHERE id = ?");
            $comp->execute([$row['household_id']]);
            echo json_encode(['ok' => true, 'composition' => $comp->fetch(PDO::FETCH_ASSOC)]);
        } else {
            echo json_encode(['ok' => true]);
        }
        exit;
    }

    exit;
}

// Get barangays for filter dropdown (server-side for initial page)
$barangayList = [];
if ($role === 'admin') {
    $barangayList = $pdo->query("SELECT id, name FROM barangays ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Household Management - Sablayan Risk Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body { background: #f0f2f5; }
        .main-content { margin-left: 0; }
        @media(min-width:768px){ .main-content { margin-left: 16.666667%; } }
        .stat-card { border-left: 4px solid; border-radius: .5rem; }
        .stat-card.blue { border-color: #0d6efd; }
        .stat-card.green { border-color: #198754; }
        .stat-card.amber { border-color: #ffc107; }
        .stat-card.red { border-color: #dc3545; }
        .stat-card.purple { border-color: #6f42c1; }
        .stat-card.teal { border-color: #20c997; }
        .vuln-icon { font-size: .75rem; margin-right: 2px; }
        .gps-valid { color: #198754; }
        .gps-invalid { color: #dc3545; }
        .gps-missing { color: #ffc107; }
        #formMap { height: 300px; border-radius: .5rem; }
        .nav-tabs .nav-link { font-weight: 500; }
        .table th { white-space: nowrap; font-size: .85rem; }
        .table td { font-size: .85rem; }
    </style>
</head>
<body>
<div class="container-fluid">
<div class="row">
<?php include 'sidebar.php'; ?>
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
    <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h4 class="fw-bold"><i class="fas fa-house-user me-2"></i>Household Management</h4>
    </div>

    <!-- Summary cards -->
    <div class="row g-2 mb-3" id="summaryCards"></div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3" id="mainTabs">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabList"><i class="fas fa-list me-1"></i> Household List</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabAdd"><i class="fas fa-plus me-1"></i> Add Household</a></li>
        <?php if ($role === 'admin'): ?>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabGPS"><i class="fas fa-satellite me-1"></i> GPS Quality</a></li>
        <?php endif; ?>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabMembers"><i class="fas fa-users me-1"></i> Members</a></li>
    </ul>

    <div class="tab-content">
        <!-- TAB 1: Household List -->
        <div class="tab-pane fade show active" id="tabList">
            <div class="row mb-3">
                <?php if ($role === 'admin'): ?>
                <div class="col-md-4">
                    <select class="form-select" id="filterBarangay" onchange="loadHouseholds()">
                        <option value="">All Barangays</option>
                        <?php foreach ($barangayList as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-4">
                    <input type="text" class="form-control" id="searchHH" placeholder="Search household head...">
                </div>
            </div>
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>HH ID</th>
                                    <th>Household Head</th>
                                    <th>Barangay</th>
                                    <th>Zone/Sitio</th>
                                    <th class="text-center">Members</th>
                                    <th class="text-center">Vulnerabilities</th>
                                    <th class="text-center">GPS</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="hhBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <nav class="mt-2"><ul class="pagination pagination-sm justify-content-center" id="pagination"></ul></nav>
        </div>

        <!-- TAB 2: Add/Edit Household -->
        <div class="tab-pane fade" id="tabAdd">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white"><h6 class="mb-0" id="formTitle"><i class="fas fa-plus me-1"></i> Add New Household</h6></div>
                <div class="card-body">
                    <form id="hhForm">
                        <input type="hidden" name="id" id="hhId">
                        <!-- Section 1: Household Info -->
                        <h6 class="text-primary border-bottom pb-2 mb-3"><i class="fas fa-info-circle me-1"></i> Household Information</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4"><label class="form-label">Household Head <span class="text-danger">*</span></label><input type="text" class="form-control" name="household_head" id="fHead" required></div>
                            <div class="col-md-4"><label class="form-label">HH ID</label><input type="text" class="form-control" name="hh_id" id="fHhId"></div>
                            <div class="col-md-4">
                                <label class="form-label">Barangay <span class="text-danger">*</span></label>
                                <select class="form-select" name="barangay_id" id="fBarangay" required></select>
                            </div>
                            <div class="col-md-3"><label class="form-label">Zone</label><input type="text" class="form-control" name="zone" id="fZone"></div>
                            <div class="col-md-3"><label class="form-label">Sitio/Purok/Zone</label><input type="text" class="form-control" name="sitio_purok_zone" id="fSitio"></div>
                            <div class="col-md-2"><label class="form-label">Sex</label><select class="form-select" name="gender" id="fGender"><option value="Male">Male</option><option value="Female">Female</option></select></div>
                            <div class="col-md-2"><label class="form-label">Age</label><input type="number" class="form-control" name="age" id="fAge" min="0" value="0"></div>
                            <div class="col-md-2"><label class="form-label">House Type</label><input type="text" class="form-control" name="house_type" id="fHouseType"></div>
                            <div class="col-md-3"><label class="form-label">IP / Non-IP</label><select class="form-select" name="ip_non_ip" id="fIP"><option value="Non-IP">Non-IP</option><option value="IP">IP</option></select></div>
                            <div class="col-md-3"><label class="form-label">Education</label><input type="text" class="form-control" name="educational_attainment" id="fEduc"></div>
                            <div class="col-md-3"><label class="form-label">Preparedness Kit</label><select class="form-select" name="preparedness_kit" id="fKit"><option value="No">No</option><option value="Yes">Yes</option></select></div>
                        </div>

                        <!-- Section 2: Family Composition (AUTO-GENERATED — read-only) -->
                        <h6 class="text-primary border-bottom pb-2 mb-3">
                            <i class="fas fa-users me-1"></i> Family Composition
                            <span class="badge bg-info ms-2">Auto-computed from members</span>
                        </h6>
                        <div class="alert alert-light border py-2 small mb-3">
                            <i class="fas fa-info-circle text-info me-1"></i>
                            These fields are <strong>automatically calculated</strong> from household members. They cannot be edited manually.
                            Add or edit members in the <strong>Members</strong> tab to update these counts.
                        </div>
                        <div class="row g-2 mb-3" id="compositionPanel">
                            <div class="col-6 col-md-3">
                                <div class="bg-light rounded p-2 text-center">
                                    <div class="small text-muted"><i class="fas fa-users me-1"></i>Total Members</div>
                                    <div class="fs-5 fw-bold" id="compTotal">1</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="bg-light rounded p-2 text-center">
                                    <div class="small text-muted"><i class="fas fa-wheelchair vuln-icon text-primary"></i> PWD</div>
                                    <div class="fs-5 fw-bold" id="compPwd">0</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="bg-light rounded p-2 text-center">
                                    <div class="small text-muted"><i class="fas fa-person-pregnant vuln-icon text-danger"></i> Pregnant</div>
                                    <div class="fs-5 fw-bold" id="compPregnant">0</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="bg-light rounded p-2 text-center">
                                    <div class="small text-muted"><i class="fas fa-person-cane vuln-icon text-warning"></i> Senior (60+)</div>
                                    <div class="fs-5 fw-bold" id="compSenior">0</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="bg-light rounded p-2 text-center">
                                    <div class="small text-muted"><i class="fas fa-baby vuln-icon text-info"></i> Infant (0-2)</div>
                                    <div class="fs-5 fw-bold" id="compInfant">0</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="bg-light rounded p-2 text-center">
                                    <div class="small text-muted">Minor (3-12)</div>
                                    <div class="fs-5 fw-bold" id="compMinor">0</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="bg-light rounded p-2 text-center">
                                    <div class="small text-muted">Child (3-5)</div>
                                    <div class="fs-5 fw-bold" id="compChild">0</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="bg-light rounded p-2 text-center">
                                    <div class="small text-muted">Adolescent (13-17)</div>
                                    <div class="fs-5 fw-bold" id="compAdol">0</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="bg-light rounded p-2 text-center">
                                    <div class="small text-muted">Young Adult (18-24)</div>
                                    <div class="fs-5 fw-bold" id="compYoung">0</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="bg-light rounded p-2 text-center">
                                    <div class="small text-muted">Adult (25-44)</div>
                                    <div class="fs-5 fw-bold" id="compAdult">0</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="bg-light rounded p-2 text-center">
                                    <div class="small text-muted">Middle Aged (45-59)</div>
                                    <div class="fs-5 fw-bold" id="compMiddle">0</div>
                                </div>
                            </div>
                        </div>
                        <!-- Hidden fields for form submission (auto-filled, not user-editable) -->
                        <input type="hidden" name="family_members" id="fMembers" value="1">
                        <input type="hidden" name="pwd_count" id="fPwd" value="0">
                        <input type="hidden" name="pregnant_count" id="fPregnant" value="0">
                        <input type="hidden" name="senior_count" id="fSenior" value="0">
                        <input type="hidden" name="infant_count" id="fInfant" value="0">
                        <input type="hidden" name="minor_count" id="fMinor" value="0">
                        <input type="hidden" name="child_count" id="fChild" value="0">
                        <input type="hidden" name="adolescent_count" id="fAdol" value="0">
                        <input type="hidden" name="young_adult_count" id="fYoung" value="0">
                        <input type="hidden" name="adult_count" id="fAdult" value="0">
                        <input type="hidden" name="middle_aged_count" id="fMiddle" value="0">

                        <!-- Section 3: GPS -->
                        <h6 class="text-primary border-bottom pb-2 mb-3"><i class="fas fa-map-pin me-1"></i> GPS Coordinates <span class="text-danger">*</span></h6>
                        <div class="row g-3 mb-3">
                            <div class="col-12">
                                <div class="btn-group mb-2">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="useDeviceGPS()"><i class="fas fa-location-crosshairs me-1"></i> Use My Current Location</button>
                                </div>
                                <div class="text-muted small mb-2">Click on the map, use device GPS, or enter coordinates manually. Satellite view recommended for finding exact house location.</div>
                            </div>
                            <div class="col-md-8"><div id="formMap"></div></div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Latitude <span class="text-danger">*</span></label>
                                    <input type="number" step="0.00000001" class="form-control" name="latitude" id="fLat" placeholder="12.50 - 13.20" required>
                                    <div class="form-text">Valid range: 12.50 - 13.20</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Longitude <span class="text-danger">*</span></label>
                                    <input type="number" step="0.00000001" class="form-control" name="longitude" id="fLng" placeholder="120.50 - 121.20" required>
                                    <div class="form-text">Valid range: 120.50 - 121.20</div>
                                </div>
                                <div id="gpsError" class="alert alert-danger d-none small"></div>
                            </div>
                        </div>

                        <!-- Hidden members data -->
                        <input type="hidden" name="members_data" id="fMembersData" value="[]">

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save Household</button>
                            <button type="button" class="btn btn-secondary" onclick="resetForm()">Clear</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- TAB 3: GPS Quality Report -->
        <?php if ($role === 'admin'): ?>
        <div class="tab-pane fade" id="tabGPS">
            <div class="card shadow-sm">
                <div class="card-header"><h6 class="mb-0"><i class="fas fa-satellite me-1"></i> GPS Data Quality Report</h6></div>
                <div class="card-body">
                    <div class="row g-3 mb-3" id="gpsSummary"></div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-dark"><tr><th>Barangay</th><th>Total</th><th>Valid GPS</th><th>% Verified</th></tr></thead>
                            <tbody id="gpsBody"></tbody>
                        </table>
                    </div>
                    <h6 class="mt-3">Flagged Households (Invalid/Missing GPS)</h6>
                    <div class="table-responsive">
                        <table class="table table-sm"><thead><tr><th>Household Head</th><th>Barangay</th><th>Status</th><th>Action</th></tr></thead>
                            <tbody id="gpsFlagged"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- TAB 4: Members Management -->
        <div class="tab-pane fade" id="tabMembers">
            <div class="card shadow-sm">
                <div class="card-header"><h6 class="mb-0"><i class="fas fa-users me-1"></i> Household Members Management</h6></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Select Household</label>
                        <select class="form-select" id="memberHHSelect" onchange="loadMembers()">
                            <option value="">-- Select a household --</option>
                        </select>
                    </div>
                    <!-- Composition summary for selected household -->
                    <div id="membersComposition" class="d-none mb-3">
                        <div class="alert alert-light border py-2 small mb-2">
                            <i class="fas fa-calculator text-info me-1"></i>
                            <strong>Auto-computed Family Composition</strong> (updates after every member change)
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-4 col-md-2"><div class="card p-2 text-center"><div class="text-muted" style="font-size:.7rem">Total</div><div class="fw-bold" id="mcTotal">0</div></div></div>
                            <div class="col-4 col-md-2"><div class="card p-2 text-center"><div class="text-muted" style="font-size:.7rem">PWD</div><div class="fw-bold text-primary" id="mcPwd">0</div></div></div>
                            <div class="col-4 col-md-2"><div class="card p-2 text-center"><div class="text-muted" style="font-size:.7rem">Pregnant</div><div class="fw-bold text-danger" id="mcPregnant">0</div></div></div>
                            <div class="col-4 col-md-2"><div class="card p-2 text-center"><div class="text-muted" style="font-size:.7rem">Senior</div><div class="fw-bold text-warning" id="mcSenior">0</div></div></div>
                            <div class="col-4 col-md-2"><div class="card p-2 text-center"><div class="text-muted" style="font-size:.7rem">Infant</div><div class="fw-bold text-info" id="mcInfant">0</div></div></div>
                            <div class="col-4 col-md-2"><div class="card p-2 text-center"><div class="text-muted" style="font-size:.7rem">Minor</div><div class="fw-bold text-secondary" id="mcMinor">0</div></div></div>
                        </div>
                    </div>
                    <div id="membersList"></div>
                    <div id="memberForm" class="d-none mt-3">
                        <h6>Add/Edit Member</h6>
                        <form id="mForm">
                            <input type="hidden" name="household_id" id="mHHId">
                            <input type="hidden" name="member_id" id="mId">
                            <div class="row g-2 mb-2">
                                <div class="col-md-3"><input type="text" class="form-control form-control-sm" name="full_name" id="mName" placeholder="Full Name" required></div>
                                <div class="col-md-2"><input type="number" class="form-control form-control-sm" name="age" id="mAge" placeholder="Age" required></div>
                                <div class="col-md-2"><select class="form-select form-select-sm" name="gender" id="mGender"><option value="Male">Male</option><option value="Female">Female</option><option value="Other">Other</option></select></div>
                                <div class="col-md-3"><input type="text" class="form-control form-control-sm" name="relationship" id="mRel" placeholder="Relationship" required></div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-auto"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_pwd" id="mPwd"><label class="form-check-label small" for="mPwd">PWD</label></div></div>
                                <div class="col-auto"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_pregnant" id="mPregnant"><label class="form-check-label small" for="mPregnant">Pregnant</label></div></div>
                                <div class="col-auto"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_senior" id="mSenior"><label class="form-check-label small" for="mSenior">Senior</label></div></div>
                                <div class="col-auto"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_infant" id="mInfant"><label class="form-check-label small" for="mInfant">Infant</label></div></div>
                                <div class="col-auto"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_minor" id="mMinor"><label class="form-check-label small" for="mMinor">Minor</label></div></div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i> Save Member</button>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="$('#memberForm').addClass('d-none')">Cancel</button>
                        </form>
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
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const role = '<?= $role ?>';
let allHouseholds = [];
let currentPage = 1;
const perPage = 20;
let formMap, formMarker;

// Tile layers
function tileLayers(){
    return {
        'OpenStreetMap': L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom:19}),
        'Satellite': L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {maxZoom:19}),
        'Terrain': L.tileLayer('https://tile.opentopomap.org/{z}/{x}/{y}.png', {maxZoom:17}),
        'Hybrid': L.layerGroup([
            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {maxZoom:19}),
            L.tileLayer('https://stamen-tiles.a.ssl.fastly.net/toner-labels/{z}/{x}/{y}.png', {maxZoom:19, opacity:0.7})
        ])
    };
}

$(document).ready(function(){
    loadBarangayDropdown();
    loadHouseholds();

    $('#searchHH').on('input', function(){ renderTable(); });

    // Init map when Add tab is shown
    $('a[href="#tabAdd"]').on('shown.bs.tab', function(){
        if (!formMap) initFormMap();
        else formMap.invalidateSize();
    });

    // GPS Quality tab
    $('a[href="#tabGPS"]').on('shown.bs.tab', loadGPSQuality);

    // Members tab
    $('a[href="#tabMembers"]').on('shown.bs.tab', loadHouseholdDropdownForMembers);

    // Form submit
    $('#hhForm').on('submit', function(e){
        e.preventDefault();
        let data = $(this).serialize();
        $.post('household_management.php?ajax=save', data, function(res){
            if (res.ok) {
                showToast(res.msg, 'success');
                resetForm();
                loadHouseholds();
                // Switch to list tab
                new bootstrap.Tab(document.querySelector('a[href="#tabList"]')).show();
            } else {
                showToast(res.msg, 'danger');
            }
        }, 'json');
    });

    // Member form submit
    $('#mForm').on('submit', function(e){
        e.preventDefault();
        let data = $(this).serialize();
        $.post('household_management.php?ajax=save_member', data, function(res){
            if (res.ok) {
                loadMembers();
                $('#memberForm').addClass('d-none');
                showToast('Member saved.', 'success');
                // Refresh composition panel from server response
                if (res.composition) updateMembersComposition(res.composition);
            }
        }, 'json');
    });

    // Lat/Lng manual input → update marker
    $('#fLat, #fLng').on('input', function(){
        let lat = parseFloat($('#fLat').val());
        let lng = parseFloat($('#fLng').val());
        if (!isNaN(lat) && !isNaN(lng) && formMap) {
            updateMapMarker(lat, lng);
        }
    });
});

function loadBarangayDropdown(){
    $.getJSON('household_management.php?ajax=barangays', function(data){
        let opts = '';
        if (role === 'admin') opts = '<option value="">-- Select --</option>';
        data.forEach(b => {
            let sel = (data.length === 1) ? 'selected' : '';
            opts += `<option value="${b.id}" data-coords="${b.coordinates||''}" ${sel}>${b.name}</option>`;
        });
        $('#fBarangay').html(opts);
    });
}

function loadHouseholds(){
    let bid = $('#filterBarangay').length ? $('#filterBarangay').val() : '';
    $.getJSON('household_management.php?ajax=list&barangay_id=' + bid, function(res){
        allHouseholds = res.data;
        renderSummary(res.summary);
        currentPage = 1;
        renderTable();
        // Update member dropdown
        loadHouseholdDropdownForMembers();
    });
}

function renderSummary(s){
    $('#summaryCards').html(`
        <div class="col-6 col-md-2"><div class="card stat-card blue p-2"><div class="text-muted small">Households</div><div class="fs-5 fw-bold">${s.total_households}</div></div></div>
        <div class="col-6 col-md-2"><div class="card stat-card green p-2"><div class="text-muted small">Population</div><div class="fs-5 fw-bold">${s.total_population.toLocaleString()}</div></div></div>
        <div class="col-6 col-md-2"><div class="card stat-card purple p-2"><div class="text-muted small">PWD</div><div class="fs-5 fw-bold">${s.pwd}</div></div></div>
        <div class="col-6 col-md-2"><div class="card stat-card amber p-2"><div class="text-muted small">Seniors</div><div class="fs-5 fw-bold">${s.senior}</div></div></div>
        <div class="col-6 col-md-2"><div class="card stat-card teal p-2"><div class="text-muted small">Children</div><div class="fs-5 fw-bold">${s.children}</div></div></div>
        <div class="col-6 col-md-2"><div class="card stat-card red p-2"><div class="text-muted small">IP</div><div class="fs-5 fw-bold">${s.ip}</div></div></div>
    `);
}

function renderTable(){
    let search = ($('#searchHH').val()||'').toLowerCase();
    let filtered = search ? allHouseholds.filter(h => h.household_head.toLowerCase().includes(search) || (h.barangay_name||'').toLowerCase().includes(search)) : allHouseholds;
    let total = filtered.length;
    let pages = Math.ceil(total / perPage);
    let start = (currentPage-1) * perPage;
    let pageData = filtered.slice(start, start + perPage);

    let html = '';
    if (pageData.length === 0) html = '<tr><td colspan="8" class="text-center text-muted py-3">No households found.</td></tr>';
    pageData.forEach(h => {
        let vulns = [];
        if (parseInt(h.pwd_count)>0) vulns.push('<span class="badge bg-primary vuln-icon" title="PWD"><i class="fas fa-wheelchair"></i></span>');
        if (parseInt(h.senior_count)>0) vulns.push('<span class="badge bg-warning vuln-icon" title="Senior"><i class="fas fa-person-cane"></i></span>');
        if (parseInt(h.infant_count)>0) vulns.push('<span class="badge bg-info vuln-icon" title="Infant"><i class="fas fa-baby"></i></span>');
        if (parseInt(h.pregnant_count)>0) vulns.push('<span class="badge bg-danger vuln-icon" title="Pregnant"><i class="fas fa-person-pregnant"></i></span>');
        if (h.ip_non_ip==='IP') vulns.push('<span class="badge bg-success vuln-icon" title="IP">IP</span>');

        let gps = 'missing';
        if (h.latitude && h.longitude && h.latitude != 0 && h.longitude != 0) {
            let lat = parseFloat(h.latitude), lng = parseFloat(h.longitude);
            if (lat >= 12.50 && lat <= 13.20 && lng >= 120.50 && lng <= 121.20) gps = 'valid';
            else gps = 'invalid';
        }
        let gpsIcon = gps === 'valid' ? '<span class="gps-valid"><i class="fas fa-check-circle"></i></span>'
                     : gps === 'invalid' ? '<span class="gps-invalid"><i class="fas fa-times-circle"></i></span>'
                     : '<span class="gps-missing"><i class="fas fa-exclamation-triangle"></i></span>';

        html += `<tr>
            <td>${esc(h.hh_id||'-')}</td>
            <td class="fw-semibold">${esc(h.household_head)}</td>
            <td>${esc(h.barangay_name)}</td>
            <td>${esc(h.sitio_purok_zone || h.zone || '-')}</td>
            <td class="text-center">${h.family_members}</td>
            <td class="text-center">${vulns.join(' ') || '-'}</td>
            <td class="text-center">${gpsIcon}</td>
            <td class="text-center">
                <button class="btn btn-sm btn-outline-primary" onclick="editHousehold(${h.id})" title="Edit"><i class="fas fa-edit"></i></button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteHousehold(${h.id})" title="Delete"><i class="fas fa-trash"></i></button>
            </td>
        </tr>`;
    });
    $('#hhBody').html(html);

    // Pagination
    let pHtml = '';
    for (let p = 1; p <= pages; p++) {
        pHtml += `<li class="page-item ${p===currentPage?'active':''}"><a class="page-link" href="#" onclick="goPage(${p});return false;">${p}</a></li>`;
    }
    $('#pagination').html(pHtml);
}

function goPage(p){ currentPage = p; renderTable(); }

function editHousehold(id){
    $.getJSON('household_management.php?ajax=get&id=' + id, function(h){
        if (!h) return;
        new bootstrap.Tab(document.querySelector('a[href="#tabAdd"]')).show();
        setTimeout(function(){
            if (!formMap) initFormMap();
            $('#formTitle').html('<i class="fas fa-edit me-1"></i> Edit Household');
            $('#hhId').val(h.id);
            $('#fHead').val(h.household_head);
            $('#fHhId').val(h.hh_id);
            $('#fBarangay').val(h.barangay_id);
            $('#fZone').val(h.zone);
            $('#fSitio').val(h.sitio_purok_zone);
            $('#fGender').val(h.gender);
            $('#fAge').val(h.age);
            $('#fHouseType').val(h.house_type);
            $('#fIP').val(h.ip_non_ip);
            $('#fEduc').val(h.educational_attainment);
            $('#fKit').val(h.preparedness_kit);
            // Populate composition (read-only display + hidden fields)
            updateCompositionPanel(h);
            $('#fLat').val(h.latitude);
            $('#fLng').val(h.longitude);
            if (h.latitude && h.longitude) {
                updateMapMarker(parseFloat(h.latitude), parseFloat(h.longitude));
                formMap.setView([parseFloat(h.latitude), parseFloat(h.longitude)], 16);
            }
            // Store members
            $('#fMembersData').val(JSON.stringify(h.members || []));
        }, 200);
    });
}

// Update the composition panel display and hidden fields from household data
function updateCompositionPanel(h){
    $('#compTotal').text(h.family_members || 0);
    $('#compPwd').text(h.pwd_count || 0);
    $('#compPregnant').text(h.pregnant_count || 0);
    $('#compSenior').text(h.senior_count || 0);
    $('#compInfant').text(h.infant_count || 0);
    $('#compMinor').text(h.minor_count || 0);
    $('#compChild').text(h.child_count || 0);
    $('#compAdol').text(h.adolescent_count || 0);
    $('#compYoung').text(h.young_adult_count || 0);
    $('#compAdult').text(h.adult_count || 0);
    $('#compMiddle').text(h.middle_aged_count || 0);
    // Update hidden fields
    $('#fMembers').val(h.family_members || 1);
    $('#fPwd').val(h.pwd_count || 0);
    $('#fPregnant').val(h.pregnant_count || 0);
    $('#fSenior').val(h.senior_count || 0);
    $('#fInfant').val(h.infant_count || 0);
    $('#fMinor').val(h.minor_count || 0);
    $('#fChild').val(h.child_count || 0);
    $('#fAdol').val(h.adolescent_count || 0);
    $('#fYoung').val(h.young_adult_count || 0);
    $('#fAdult').val(h.adult_count || 0);
    $('#fMiddle').val(h.middle_aged_count || 0);
}

function resetCompositionPanel(){
    $('#compTotal').text('1');
    $('#compPwd, #compPregnant, #compSenior, #compInfant, #compMinor, #compChild, #compAdol, #compYoung, #compAdult, #compMiddle').text('0');
}

function deleteHousehold(id){
    if (!confirm('Delete this household?')) return;
    $.post('household_management.php?ajax=delete', {id:id}, function(res){
        if (res.ok) { showToast(res.msg, 'success'); loadHouseholds(); }
        else showToast(res.msg, 'danger');
    }, 'json');
}

function resetForm(){
    $('#hhForm')[0].reset();
    $('#hhId').val('');
    $('#fMembersData').val('[]');
    $('#formTitle').html('<i class="fas fa-plus me-1"></i> Add New Household');
    if (formMarker) { formMap.removeLayer(formMarker); formMarker = null; }
    $('#gpsError').addClass('d-none');
    resetCompositionPanel();
}

// ── Map for form ──
function initFormMap(){
    let tiles = tileLayers();
    formMap = L.map('formMap', { layers: [tiles['Satellite']], center: [12.84, 120.87], zoom: 11 });
    L.control.layers(tiles).addTo(formMap);

    formMap.on('click', function(e){
        updateMapMarker(e.latlng.lat, e.latlng.lng);
        $('#fLat').val(e.latlng.lat.toFixed(8));
        $('#fLng').val(e.latlng.lng.toFixed(8));
        validateGPS();
    });

    // If barangay selected, zoom to it
    let sel = $('#fBarangay option:selected');
    let coords = sel.data('coords');
    if (coords) {
        let parts = String(coords).split(',').map(Number);
        if (parts.length === 2) formMap.setView([parts[0], parts[1]], 14);
    }
}

function updateMapMarker(lat, lng){
    if (formMarker) formMap.removeLayer(formMarker);
    formMarker = L.marker([lat, lng], {draggable: true}).addTo(formMap);
    formMarker.on('dragend', function(e){
        let pos = e.target.getLatLng();
        $('#fLat').val(pos.lat.toFixed(8));
        $('#fLng').val(pos.lng.toFixed(8));
        validateGPS();
    });
    formMap.setView([lat, lng], Math.max(formMap.getZoom(), 15));
}

function useDeviceGPS(){
    if (!navigator.geolocation) { showToast('Geolocation not supported.', 'warning'); return; }
    navigator.geolocation.getCurrentPosition(function(pos){
        let lat = pos.coords.latitude, lng = pos.coords.longitude;
        $('#fLat').val(lat.toFixed(8));
        $('#fLng').val(lng.toFixed(8));
        if (!formMap) initFormMap();
        updateMapMarker(lat, lng);
        validateGPS();
        showToast('GPS coordinates captured.', 'success');
    }, function(err){
        showToast('GPS error: ' + err.message, 'danger');
    }, {enableHighAccuracy: true});
}

function validateGPS(){
    let lat = parseFloat($('#fLat').val()), lng = parseFloat($('#fLng').val());
    let err = '';
    if (isNaN(lat) || isNaN(lng)) err = 'Enter valid coordinates.';
    else if (lat < 12.50 || lat > 13.20) err = 'Latitude must be between 12.50 and 13.20.';
    else if (lng < 120.50 || lng > 121.20) err = 'Longitude must be between 120.50 and 121.20.';
    else if (lat === 0 && lng === 0) err = 'Invalid: 0,0 is not valid.';

    if (err) { $('#gpsError').text(err).removeClass('d-none'); }
    else { $('#gpsError').addClass('d-none'); }
}

// ── GPS Quality ──
function loadGPSQuality(){
    $.getJSON('household_management.php?ajax=gps_quality', function(res){
        let summaryHtml = '';
        res.summary.forEach(s => {
            let pct = s.total > 0 ? Math.round(s.valid / s.total * 100) : 0;
            let color = pct >= 90 ? 'success' : pct >= 50 ? 'warning' : 'danger';
            summaryHtml += `<div class="col-md-4 col-lg-3"><div class="card p-2"><div class="small fw-bold">${s.barangay_name}</div><div class="progress mt-1" style="height:6px"><div class="progress-bar bg-${color}" style="width:${pct}%"></div></div><div class="text-muted" style="font-size:.75rem">${s.valid}/${s.total} (${pct}%)</div></div></div>`;
        });
        $('#gpsSummary').html(summaryHtml);

        let bHtml = '';
        res.summary.forEach(s => {
            let pct = s.total > 0 ? Math.round(s.valid / s.total * 100) : 0;
            bHtml += `<tr><td>${s.barangay_name}</td><td>${s.total}</td><td>${s.valid}</td><td><span class="badge bg-${pct>=90?'success':pct>=50?'warning':'danger'}">${pct}%</span></td></tr>`;
        });
        $('#gpsBody').html(bHtml);

        let flagged = res.data.filter(h => h.gps_status !== 'valid');
        let fHtml = '';
        flagged.forEach(h => {
            let status = h.gps_status === 'missing' ? '<span class="badge bg-warning">Missing</span>' : '<span class="badge bg-danger">Invalid</span>';
            fHtml += `<tr><td>${esc(h.household_head)}</td><td>${esc(h.barangay_name)}</td><td>${status}</td><td><button class="btn btn-sm btn-outline-primary" onclick="editHousehold(${h.id})"><i class="fas fa-edit"></i> Fix</button></td></tr>`;
        });
        $('#gpsFlagged').html(fHtml || '<tr><td colspan="4" class="text-center text-success">All GPS data is valid!</td></tr>');
    });
}

// ── Members ──
function loadHouseholdDropdownForMembers(){
    let opts = '<option value="">-- Select a household --</option>';
    allHouseholds.forEach(h => opts += `<option value="${h.id}">${esc(h.household_head)} (${esc(h.barangay_name)})</option>`);
    $('#memberHHSelect').html(opts);
    $('#membersList').html('');
}

// Update members tab composition panel from server data
function updateMembersComposition(c){
    $('#mcTotal').text(c.family_members || 0);
    $('#mcPwd').text(c.pwd_count || 0);
    $('#mcPregnant').text(c.pregnant_count || 0);
    $('#mcSenior').text(c.senior_count || 0);
    $('#mcInfant').text(c.infant_count || 0);
    $('#mcMinor').text(c.minor_count || 0);
    $('#membersComposition').removeClass('d-none');
}

function loadMembers(){
    let hid = $('#memberHHSelect').val();
    if (!hid) { $('#membersList').html(''); $('#membersComposition').addClass('d-none'); return; }
    // Fetch composition for this household
    $.getJSON('household_management.php?ajax=get&id=' + hid, function(h){
        if (h) updateMembersComposition(h);
    });
    $.getJSON('household_management.php?ajax=members&household_id=' + hid, function(members){
        let html = `<button class="btn btn-sm btn-primary mb-2" onclick="addMember(${hid})"><i class="fas fa-plus me-1"></i> Add Member</button>`;
        html += '<table class="table table-sm"><thead><tr><th>Name</th><th>Age</th><th>Gender</th><th>Relationship</th><th>Flags</th><th>Actions</th></tr></thead><tbody>';
        members.forEach(m => {
            let flags = [];
            if (m.is_pwd == 1) flags.push('PWD');
            if (m.is_pregnant == 1) flags.push('Pregnant');
            if (m.is_senior == 1) flags.push('Senior');
            if (m.is_infant == 1) flags.push('Infant');
            if (m.is_minor == 1) flags.push('Minor');
            html += `<tr><td>${esc(m.full_name)}</td><td>${m.age}</td><td>${m.gender}</td><td>${esc(m.relationship)}</td><td>${flags.join(', ')||'-'}</td>
                <td><button class="btn btn-sm btn-outline-primary" onclick="editMember(${m.id}, ${hid})"><i class="fas fa-edit"></i></button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteMember(${m.id})"><i class="fas fa-trash"></i></button></td></tr>`;
        });
        html += '</tbody></table>';
        $('#membersList').html(html);
    });
}

function addMember(hid){
    $('#mForm')[0].reset();
    $('#mHHId').val(hid);
    $('#mId').val('');
    $('#memberForm').removeClass('d-none');
}

function editMember(mid, hid){
    $.getJSON('household_management.php?ajax=members&household_id=' + hid, function(members){
        let m = members.find(x => x.id == mid);
        if (!m) return;
        $('#mHHId').val(hid);
        $('#mId').val(m.id);
        $('#mName').val(m.full_name);
        $('#mAge').val(m.age);
        $('#mGender').val(m.gender);
        $('#mRel').val(m.relationship);
        $('#mPwd').prop('checked', m.is_pwd == 1);
        $('#mPregnant').prop('checked', m.is_pregnant == 1);
        $('#mSenior').prop('checked', m.is_senior == 1);
        $('#mInfant').prop('checked', m.is_infant == 1);
        $('#mMinor').prop('checked', m.is_minor == 1);
        $('#memberForm').removeClass('d-none');
    });
}

function deleteMember(mid){
    if (!confirm('Delete this member?')) return;
    $.post('household_management.php?ajax=delete_member', {member_id: mid}, function(res){
        if (res.ok) {
            loadMembers();
            showToast('Member deleted.', 'success');
            if (res.composition) updateMembersComposition(res.composition);
        }
    }, 'json');
}

function showToast(msg, type){
    let id = 'toast'+Date.now();
    $('body').append(`<div id="${id}" class="position-fixed bottom-0 end-0 p-3" style="z-index:9999">
        <div class="toast show align-items-center text-bg-${type} border-0"><div class="d-flex"><div class="toast-body">${msg}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div></div>`);
    setTimeout(()=>$('#'+id).remove(), 4000);
}

function esc(s){ if(!s) return ''; let d=document.createElement('div'); d.textContent=s; return d.innerHTML; }
</script>
</body>
</html>
