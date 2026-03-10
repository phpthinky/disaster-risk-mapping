<?php
// incident_reports.php
// Phase 4 — Incident Reports with polygon drawing and auto-computation of affected households.
//
// WHAT THIS IS:
//   An incident report = an actual disaster event that already happened or is currently happening.
//   Staff draws a polygon over the affected area. The system auto-counts all households
//   whose GPS coordinates fall inside that polygon using a PHP ray-casting algorithm.
//
// WHAT THIS IS NOT:
//   This is NOT hazard_zones, which are permanent pre-mapped risk assessment areas.

session_start();
require_once 'config.php';
require_once __DIR__ . '/functions/population_functions.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'barangay_staff'])) {
    header('Location: login.php');
    exit;
}

// Create incident_reports table if not exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS incident_reports (
        id INT PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        hazard_type_id INT,
        incident_date DATE NOT NULL,
        status ENUM('ongoing','resolved','monitoring') DEFAULT 'ongoing',
        description TEXT,
        polygon_geojson LONGTEXT,
        total_affected_households INT DEFAULT 0,
        total_affected_population INT DEFAULT 0,
        total_affected_pwd INT DEFAULT 0,
        total_affected_seniors INT DEFAULT 0,
        total_affected_infants INT DEFAULT 0,
        total_affected_minors INT DEFAULT 0,
        total_affected_pregnant INT DEFAULT 0,
        total_ip_count INT DEFAULT 0,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");

// Create affected_areas table if not exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS affected_areas (
        id INT PRIMARY KEY AUTO_INCREMENT,
        incident_id INT NOT NULL,
        barangay_id INT NOT NULL,
        affected_households INT DEFAULT 0,
        affected_population INT DEFAULT 0,
        affected_pwd INT DEFAULT 0,
        affected_seniors INT DEFAULT 0,
        affected_infants INT DEFAULT 0,
        affected_minors INT DEFAULT 0,
        affected_pregnant INT DEFAULT 0,
        ip_count INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (incident_id) REFERENCES incident_reports(id) ON DELETE CASCADE
    )
");

// ──────────────────────────────────────────────────────────────────────────────
// Handle form submission — save incident report + run auto-computation
// ──────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_incident'])) {
    $title          = trim($_POST['title']);
    $hazard_type_id = (int)$_POST['hazard_type_id'];
    $incident_date  = $_POST['incident_date'];
    $status         = $_POST['status'];
    $description    = trim($_POST['description']);
    $polygon_geojson = $_POST['polygon_geojson'] ?? '';

    if (empty($polygon_geojson)) {
        $error = "Please draw the affected area polygon on the map before saving.";
    } else {
        // Parse GeoJSON ring
        $geojson = json_decode($polygon_geojson, true);
        $ring = [];
        if ($geojson['type'] === 'FeatureCollection' && !empty($geojson['features'])) {
            $geo = $geojson['features'][0]['geometry'];
        } elseif ($geojson['type'] === 'Feature') {
            $geo = $geojson['geometry'];
        } else {
            $geo = $geojson;
        }
        if (isset($geo['type']) && $geo['type'] === 'Polygon') {
            $ring = $geo['coordinates'][0];
        }

        if (empty($ring)) {
            $error = "Invalid polygon. Please draw the affected area again.";
        } else {
            // Fetch all households with valid GPS
            $hh_stmt = $pdo->query("
                SELECT h.id, h.barangay_id, h.family_members, h.pwd_count,
                       h.senior_count, h.infant_count, h.minor_count, h.pregnant_count,
                       h.ip_non_ip, h.latitude, h.longitude
                FROM households h
                WHERE h.latitude BETWEEN 12.50 AND 13.20
                  AND h.longitude BETWEEN 120.50 AND 121.20
            ");
            $all_households = $hh_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Run point-in-polygon ray casting
            $totals = [
                'households' => 0, 'population' => 0, 'pwd' => 0,
                'seniors' => 0, 'infants' => 0, 'minors' => 0,
                'pregnant' => 0, 'ip' => 0
            ];
            // Per-barangay breakdown
            $by_barangay = [];

            foreach ($all_households as $hh) {
                if (point_in_polygon((float)$hh['latitude'], (float)$hh['longitude'], $ring)) {
                    $bid = $hh['barangay_id'];
                    if (!isset($by_barangay[$bid])) {
                        $by_barangay[$bid] = ['households'=>0,'population'=>0,'pwd'=>0,'seniors'=>0,'infants'=>0,'minors'=>0,'pregnant'=>0,'ip'=>0];
                    }
                    $by_barangay[$bid]['households']++;
                    $by_barangay[$bid]['population'] += (int)$hh['family_members'];
                    $by_barangay[$bid]['pwd']        += (int)$hh['pwd_count'];
                    $by_barangay[$bid]['seniors']    += (int)$hh['senior_count'];
                    $by_barangay[$bid]['infants']    += (int)$hh['infant_count'];
                    $by_barangay[$bid]['minors']     += (int)$hh['minor_count'];
                    $by_barangay[$bid]['pregnant']   += (int)$hh['pregnant_count'];
                    if ($hh['ip_non_ip'] === 'IP') $by_barangay[$bid]['ip']++;

                    $totals['households']++;
                    $totals['population'] += (int)$hh['family_members'];
                    $totals['pwd']        += (int)$hh['pwd_count'];
                    $totals['seniors']    += (int)$hh['senior_count'];
                    $totals['infants']    += (int)$hh['infant_count'];
                    $totals['minors']     += (int)$hh['minor_count'];
                    $totals['pregnant']   += (int)$hh['pregnant_count'];
                    if ($hh['ip_non_ip'] === 'IP') $totals['ip']++;
                }
            }

            // Insert incident report
            $stmt = $pdo->prepare("
                INSERT INTO incident_reports
                    (title, hazard_type_id, incident_date, status, description, polygon_geojson,
                     total_affected_households, total_affected_population, total_affected_pwd,
                     total_affected_seniors, total_affected_infants, total_affected_minors,
                     total_affected_pregnant, total_ip_count, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $title, $hazard_type_id, $incident_date, $status, $description, $polygon_geojson,
                $totals['households'], $totals['population'], $totals['pwd'],
                $totals['seniors'], $totals['infants'], $totals['minors'],
                $totals['pregnant'], $totals['ip'],
                $_SESSION['user_id']
            ]);
            $incident_id = $pdo->lastInsertId();

            // Insert per-barangay affected_areas
            $area_stmt = $pdo->prepare("
                INSERT INTO affected_areas
                    (incident_id, barangay_id, affected_households, affected_population,
                     affected_pwd, affected_seniors, affected_infants, affected_minors,
                     affected_pregnant, ip_count)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($by_barangay as $bid => $d) {
                $area_stmt->execute([
                    $incident_id, $bid,
                    $d['households'], $d['population'], $d['pwd'],
                    $d['seniors'], $d['infants'], $d['minors'],
                    $d['pregnant'], $d['ip']
                ]);
            }

            // Show result immediately
            $saved_totals    = $totals;
            $saved_by_brgy   = $by_barangay;
            $saved_incident_id = $incident_id;
            $success = "Incident report saved! Auto-computation complete.";
        }
    }
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $inc_id = (int)$_POST['incident_id'];
    $new_status = $_POST['new_status'];
    $pdo->prepare("UPDATE incident_reports SET status = ?, updated_at = NOW() WHERE id = ?")
        ->execute([$new_status, $inc_id]);
    header("Location: incident_reports.php?updated=1");
    exit;
}

// Handle delete
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    $pdo->prepare("DELETE FROM incident_reports WHERE id = ?")->execute([$del_id]);
    header("Location: incident_reports.php?deleted=1");
    exit;
}

// Get data for form dropdowns
$hazard_types = $pdo->query("SELECT * FROM hazard_types ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get all incidents for list
$incidents_stmt = $pdo->query("
    SELECT ir.*, ht.name as hazard_type_name, u.username as created_by_name
    FROM incident_reports ir
    LEFT JOIN hazard_types ht ON ir.hazard_type_id = ht.id
    LEFT JOIN users u ON ir.created_by = u.id
    ORDER BY ir.incident_date DESC, ir.created_at DESC
");
$incidents = $incidents_stmt->fetchAll(PDO::FETCH_ASSOC);

// View specific incident detail
$view_incident = null;
$view_areas = [];
if (isset($_GET['view_id'])) {
    $view_id = (int)$_GET['view_id'];
    $stmt = $pdo->prepare("
        SELECT ir.*, ht.name as hazard_type_name
        FROM incident_reports ir
        LEFT JOIN hazard_types ht ON ir.hazard_type_id = ht.id
        WHERE ir.id = ?
    ");
    $stmt->execute([$view_id]);
    $view_incident = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($view_incident) {
        $area_stmt = $pdo->prepare("
            SELECT aa.*, b.name as barangay_name
            FROM affected_areas aa
            JOIN barangays b ON aa.barangay_id = b.id
            WHERE aa.incident_id = ?
            ORDER BY aa.affected_population DESC
        ");
        $area_stmt->execute([$view_id]);
        $view_areas = $area_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incident Reports — Sablayan Risk Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css">
    <style>
        body { background: #f8f9fa; }
        .main-content { padding: 20px; }
        .status-ongoing    { background: #dc3545; color: #fff; }
        .status-monitoring { background: #fd7e14; color: #fff; }
        .status-resolved   { background: #198754; color: #fff; }
        #incidentMapPicker { height: 350px; border: 2px solid #dee2e6; border-radius: 6px; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include 'sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <?php include 'navbar.php'; ?>

            <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
                <h4><i class="fas fa-file-medical-alt me-2 text-danger"></i>Incident Reports</h4>
                <div class="d-flex gap-2">
                    <a href="incident_reports.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus me-1"></i> New Incident
                    </a>
                    <a href="incident_list.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-list me-1"></i> Full List
                    </a>
                </div>
            </div>

            <?php if (isset($success)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle me-1"></i> <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-1"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-info">Status updated.</div>
            <?php endif; ?>
            <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-warning">Incident deleted.</div>
            <?php endif; ?>

            <?php if ($view_incident): ?>
            <!-- ─── DETAIL VIEW ─── -->
            <div class="card mb-4">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <strong><i class="fas fa-eye me-1"></i> <?php echo htmlspecialchars($view_incident['title']); ?></strong>
                    <div class="d-flex gap-2 align-items-center">
                        <span class="badge status-<?php echo $view_incident['status']; ?>"><?php echo ucfirst($view_incident['status']); ?></span>
                        <form method="POST" class="d-flex gap-1 align-items-center ms-2">
                            <input type="hidden" name="incident_id" value="<?php echo $view_incident['id']; ?>">
                            <select name="new_status" class="form-select form-select-sm" style="width:120px;">
                                <option value="ongoing"    <?php echo $view_incident['status']==='ongoing'?'selected':''; ?>>Ongoing</option>
                                <option value="monitoring" <?php echo $view_incident['status']==='monitoring'?'selected':''; ?>>Monitoring</option>
                                <option value="resolved"   <?php echo $view_incident['status']==='resolved'?'selected':''; ?>>Resolved</option>
                            </select>
                            <button type="submit" name="update_status" class="btn btn-sm btn-light">Update</button>
                        </form>
                        <a href="incident_reports.php" class="btn btn-sm btn-secondary">Back</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Date:</strong> <?php echo date('F d, Y', strtotime($view_incident['incident_date'])); ?></p>
                            <p><strong>Hazard Type:</strong> <?php echo htmlspecialchars($view_incident['hazard_type_name'] ?? 'N/A'); ?></p>
                            <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($view_incident['description'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <!-- Summary stats -->
                            <div class="row g-2">
                                <?php
                                $stats = [
                                    ['label'=>'Households','val'=>$view_incident['total_affected_households'],'color'=>'primary','icon'=>'home'],
                                    ['label'=>'Population','val'=>$view_incident['total_affected_population'],'color'=>'danger','icon'=>'users'],
                                    ['label'=>'PWD','val'=>$view_incident['total_affected_pwd'],'color'=>'warning','icon'=>'wheelchair'],
                                    ['label'=>'Seniors','val'=>$view_incident['total_affected_seniors'],'color'=>'info','icon'=>'user-clock'],
                                    ['label'=>'Infants','val'=>$view_incident['total_affected_infants'],'color'=>'success','icon'=>'baby'],
                                    ['label'=>'Pregnant','val'=>$view_incident['total_affected_pregnant'],'color'=>'secondary','icon'=>'heart'],
                                    ['label'=>'IP Count','val'=>$view_incident['total_ip_count'],'color'=>'dark','icon'=>'mountain'],
                                ];
                                foreach ($stats as $s): ?>
                                <div class="col-6">
                                    <div class="d-flex align-items-center gap-2 border rounded p-2 mb-1">
                                        <i class="fas fa-<?php echo $s['icon']; ?> text-<?php echo $s['color']; ?>"></i>
                                        <div>
                                            <div class="fw-bold"><?php echo number_format($s['val']); ?></div>
                                            <div class="text-muted small"><?php echo $s['label']; ?></div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Affected polygon map -->
                    <?php if ($view_incident['polygon_geojson']): ?>
                    <div id="viewMap" style="height:300px; border-radius:6px; margin-bottom:16px;"></div>
                    <?php endif; ?>

                    <!-- Barangay breakdown -->
                    <?php if ($view_areas): ?>
                    <h6 class="mt-3">Affected Barangays Breakdown</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-dark">
                                <tr><th>Barangay</th><th>HH</th><th>Population</th><th>PWD</th><th>Seniors</th><th>Infants</th><th>Pregnant</th><th>IP</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($view_areas as $area): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($area['barangay_name']); ?></td>
                                    <td><?php echo number_format($area['affected_households']); ?></td>
                                    <td><?php echo number_format($area['affected_population']); ?></td>
                                    <td><?php echo number_format($area['affected_pwd']); ?></td>
                                    <td><?php echo number_format($area['affected_seniors']); ?></td>
                                    <td><?php echo number_format($area['affected_infants']); ?></td>
                                    <td><?php echo number_format($area['affected_pregnant']); ?></td>
                                    <td><?php echo number_format($area['ip_count']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($view_incident['polygon_geojson']): ?>
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
            <script>
            (function() {
                const map = L.map('viewMap').setView([12.8333, 120.7667], 12);
                const streetTileV = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors', maxZoom: 19
                });
                const satelliteTileV = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                    attribution: 'Tiles &copy; Esri', maxZoom: 19
                });
                satelliteTileV.addTo(map);
                L.control.layers({ 'Satellite': satelliteTileV, 'Street': streetTileV }, {}, { position: 'topright' }).addTo(map);
                const gj = JSON.parse(<?php echo json_encode($view_incident['polygon_geojson']); ?>);
                const layer = L.geoJSON(gj, {style:{color:'#dc3545',weight:2,fillOpacity:0.2}}).addTo(map);
                map.fitBounds(layer.getBounds());
            })();
            </script>
            <?php endif; ?>

            <?php elseif (isset($saved_incident_id)): ?>
            <!-- ─── RESULTS AFTER SAVE ─── -->
            <div class="alert alert-success">
                <h5><i class="fas fa-check-circle me-2"></i>Incident Report Saved — Auto-Computation Results</h5>
                <div class="row mt-2">
                    <div class="col-md-3"><strong>Total Households Affected:</strong> <?php echo number_format($saved_totals['households']); ?></div>
                    <div class="col-md-3"><strong>Total Population Affected:</strong> <?php echo number_format($saved_totals['population']); ?></div>
                    <div class="col-md-3"><strong>PWD:</strong> <?php echo number_format($saved_totals['pwd']); ?></div>
                    <div class="col-md-3"><strong>Seniors:</strong> <?php echo number_format($saved_totals['seniors']); ?></div>
                </div>
                <div class="row mt-1">
                    <div class="col-md-3"><strong>Infants:</strong> <?php echo number_format($saved_totals['infants']); ?></div>
                    <div class="col-md-3"><strong>Pregnant:</strong> <?php echo number_format($saved_totals['pregnant']); ?></div>
                    <div class="col-md-3"><strong>IP Count:</strong> <?php echo number_format($saved_totals['ip']); ?></div>
                    <div class="col-md-3">
                        <a href="incident_reports.php?view_id=<?php echo $saved_incident_id; ?>" class="btn btn-sm btn-light">
                            <i class="fas fa-eye me-1"></i> View Full Report
                        </a>
                    </div>
                </div>
            </div>

            <?php else: ?>
            <!-- ─── ADD INCIDENT FORM ─── -->
            <div class="row">
                <div class="col-md-5">
                    <div class="card">
                        <div class="card-header bg-danger text-white">
                            <strong><i class="fas fa-plus me-1"></i> Report New Incident</strong>
                        </div>
                        <div class="card-body">
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                            <div class="alert alert-secondary small py-2 mb-3">
                                <i class="fas fa-code me-1"></i>
                                <strong>DEVELOPER NOTE:</strong> This system currently supports manual polygon drawing for affected area mapping. This can be upgraded in a future version to support real-time GPS-based incident tracking, where the affected polygon is automatically generated from live field reports. The household GPS infrastructure is already in place to support this upgrade.
                            </div>
                            <?php endif; ?>

                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Incident Title <span class="text-danger">*</span></label>
                                    <input type="text" name="title" class="form-control" required
                                        placeholder="e.g., Typhoon Carina Flooding — July 2024">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Disaster Type <span class="text-danger">*</span></label>
                                    <select name="hazard_type_id" class="form-select" required>
                                        <option value="">Select disaster type</option>
                                        <?php foreach ($hazard_types as $ht): ?>
                                        <option value="<?php echo $ht['id']; ?>"><?php echo htmlspecialchars($ht['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Incident Date <span class="text-danger">*</span></label>
                                    <input type="date" name="incident_date" class="form-control" required
                                        value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="ongoing">Ongoing</option>
                                        <option value="monitoring">Monitoring</option>
                                        <option value="resolved">Resolved</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description / Notes</label>
                                    <textarea name="description" class="form-control" rows="3"
                                        placeholder="Describe the incident, affected areas, response actions..."></textarea>
                                </div>

                                <!-- Polygon GeoJSON — filled by map -->
                                <input type="hidden" name="polygon_geojson" id="incidentPolygonGeoJSON">

                                <div class="d-grid">
                                    <button type="submit" name="save_incident" class="btn btn-danger">
                                        <i class="fas fa-save me-1"></i> Save & Compute Affected Population
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-7">
                    <div class="card">
                        <div class="card-header">
                            <strong><i class="fas fa-draw-polygon me-1 text-danger"></i> Draw Affected Area</strong>
                            <small class="text-muted ms-2">Use the polygon tool to outline the affected zone</small>
                        </div>
                        <div class="card-body p-2">
                            <div id="incidentMapPicker"></div>
                            <div id="polygonStatus" class="mt-2 small text-muted">
                                No polygon drawn yet. Use the polygon tool (<i class="fas fa-draw-polygon"></i>) in the top-left toolbar.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ─── RECENT INCIDENTS LIST ─── -->
            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong><i class="fas fa-list me-1"></i> Recent Incidents</strong>
                    <a href="incident_list.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Title</th>
                                    <th>Type</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Affected HH</th>
                                    <th>Affected Pop</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($incidents, 0, 10) as $inc): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($inc['title']); ?></td>
                                    <td><?php echo htmlspecialchars($inc['hazard_type_name'] ?? '—'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($inc['incident_date'])); ?></td>
                                    <td>
                                        <span class="badge status-<?php echo $inc['status']; ?>">
                                            <?php echo ucfirst($inc['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format($inc['total_affected_households']); ?></td>
                                    <td><?php echo number_format($inc['total_affected_population']); ?></td>
                                    <td>
                                        <a href="?view_id=<?php echo $inc['id']; ?>" class="btn btn-xs btn-sm btn-outline-primary py-0">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($_SESSION['role'] === 'admin'): ?>
                                        <a href="?delete_id=<?php echo $inc['id']; ?>" class="btn btn-xs btn-sm btn-outline-danger py-0"
                                           onclick="return confirm('Delete this incident report?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($incidents)): ?>
                                <tr><td colspan="7" class="text-center text-muted py-3">No incident reports yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
<script>
(function() {
    const mapEl = document.getElementById('incidentMapPicker');
    if (!mapEl) return;

    const map = L.map('incidentMapPicker').setView([12.8333, 120.7667], 12);
    const streetTileI = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors', maxZoom: 19
    });
    const satelliteTileI = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Tiles &copy; Esri', maxZoom: 19
    });
    satelliteTileI.addTo(map);
    L.control.layers({ 'Satellite': satelliteTileI, 'Street': streetTileI }, {}, { position: 'topright' }).addTo(map);

    const drawnItems = new L.FeatureGroup();
    map.addLayer(drawnItems);

    const drawControl = new L.Control.Draw({
        draw: {
            polygon: {
                allowIntersection: false,
                shapeOptions: { color: '#dc3545', weight: 2, fillOpacity: 0.2 }
            },
            polyline: false, rectangle: false, circle: false,
            marker: false, circlemarker: false
        },
        edit: { featureGroup: drawnItems }
    });
    map.addControl(drawControl);

    const polyInput  = document.getElementById('incidentPolygonGeoJSON');
    const statusEl   = document.getElementById('polygonStatus');

    function updatePolygon() {
        if (drawnItems.getLayers().length === 0) {
            polyInput.value = '';
            if (statusEl) statusEl.innerHTML = 'No polygon drawn yet.';
            return;
        }
        const gj = JSON.stringify(drawnItems.toGeoJSON());
        polyInput.value = gj;
        if (statusEl) statusEl.innerHTML =
            '<span class="text-success"><i class="fas fa-check-circle me-1"></i>Polygon drawn. Ready to save.</span>';
    }

    map.on(L.Draw.Event.CREATED, function(e) {
        drawnItems.clearLayers();
        drawnItems.addLayer(e.layer);
        updatePolygon();
    });
    map.on(L.Draw.Event.EDITED,  function() { updatePolygon(); });
    map.on(L.Draw.Event.DELETED, function() { updatePolygon(); });
})();
</script>
</body>
</html>
