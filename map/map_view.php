<?php
/**
 * map/map_view.php
 * Full-screen interactive disaster risk map.
 * Requires login. Custom full-screen layout (no header.php).
 */

require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$userRole = $_SESSION['role'] ?? 'user';

// ─── 1. All barangays (boundary_geojson may be null) ──────────────────────────
$stmtBar = $pdo->query("
    SELECT id, name, population, household_count, pwd_count, senior_count,
           children_count, infant_count, pregnant_count, ip_count,
           coordinates, boundary_geojson
    FROM barangays
    ORDER BY name
");
$barangays = $stmtBar->fetchAll(PDO::FETCH_ASSOC);

// ─── 2. Households with valid GPS ─────────────────────────────────────────────
$stmtHH = $pdo->prepare("
    SELECT id, household_head, barangay_id, family_members,
           pwd_count, senior_count, infant_count, pregnant_count,
           latitude, longitude, zone
    FROM households
    WHERE latitude  BETWEEN :lat_min AND :lat_max
      AND longitude BETWEEN :lng_min AND :lng_max
");
$stmtHH->execute([
    ':lat_min' => GPS_LAT_MIN, ':lat_max' => GPS_LAT_MAX,
    ':lng_min' => GPS_LNG_MIN, ':lng_max' => GPS_LNG_MAX,
]);
$households_gps = $stmtHH->fetchAll(PDO::FETCH_ASSOC);

// Cast numeric fields to proper types for JS
foreach ($households_gps as &$hh) {
    $hh['latitude']      = (float)  $hh['latitude'];
    $hh['longitude']     = (float)  $hh['longitude'];
    $hh['family_members']= (int)    $hh['family_members'];
    $hh['pwd_count']     = (int)    $hh['pwd_count'];
    $hh['senior_count']  = (int)    $hh['senior_count'];
    $hh['infant_count']  = (int)    $hh['infant_count'];
    $hh['pregnant_count']= (int)    $hh['pregnant_count'];
}
unset($hh);

// ─── 3. Hazard zones with hazard_type name/color and barangay name ─────────────
$stmtHz = $pdo->query("
    SELECT hz.id, hz.barangay_id, hz.hazard_type_id, hz.risk_level,
           hz.area_km2, hz.affected_population, hz.coordinates,
           hz.polygon_geojson, hz.description,
           ht.name  AS hazard_type,
           ht.color AS hazard_color,
           b.name   AS barangay_name,
           b.coordinates AS barangay_coordinates,
           b.boundary_geojson AS barangay_boundary
    FROM hazard_zones hz
    LEFT JOIN hazard_types ht ON hz.hazard_type_id = ht.id
    LEFT JOIN barangays    b  ON hz.barangay_id    = b.id
    ORDER BY hz.id
");
$hazardZones = $stmtHz->fetchAll(PDO::FETCH_ASSOC);

foreach ($hazardZones as &$hz) {
    $hz['area_km2']           = $hz['area_km2']           !== null ? (float)$hz['area_km2']           : null;
    $hz['affected_population']= $hz['affected_population']!== null ? (int)  $hz['affected_population']: null;
}
unset($hz);

// ─── 4. Active / monitoring incident reports with polygon ─────────────────────
$stmtInc = $pdo->query("
    SELECT ir.id, ir.title, ir.hazard_type_id, ir.incident_date, ir.status,
           ir.polygon_geojson, ir.total_affected_population,
           ht.name  AS hazard_type,
           ht.color AS hazard_color
    FROM incident_reports ir
    LEFT JOIN hazard_types ht ON ir.hazard_type_id = ht.id
    WHERE ir.status IN ('ongoing','monitoring')
      AND ir.polygon_geojson IS NOT NULL
    ORDER BY ir.incident_date DESC
");
$incidentPolygons = $stmtInc->fetchAll(PDO::FETCH_ASSOC);

// ─── 5. Evacuation centers with valid lat/lng ─────────────────────────────────
$stmtEvac = $pdo->query("
    SELECT ec.id, ec.name, ec.barangay_id, ec.latitude, ec.longitude,
           ec.capacity, ec.current_occupancy, ec.facilities,
           ec.contact_person, ec.contact_number, ec.status,
           b.name AS barangay_name
    FROM evacuation_centers ec
    LEFT JOIN barangays b ON ec.barangay_id = b.id
    WHERE ec.latitude  IS NOT NULL
      AND ec.longitude IS NOT NULL
      AND ec.latitude  != 0
      AND ec.longitude != 0
    ORDER BY ec.name
");
$evacCenters = $stmtEvac->fetchAll(PDO::FETCH_ASSOC);

foreach ($evacCenters as &$ec) {
    $ec['latitude']          = (float) $ec['latitude'];
    $ec['longitude']         = (float) $ec['longitude'];
    $ec['capacity']          = (int)   $ec['capacity'];
    $ec['current_occupancy'] = (int)   $ec['current_occupancy'];
}
unset($ec);

// ─── 6. Sidebar stats ─────────────────────────────────────────────────────────
$statBarangays  = $pdo->query("SELECT COUNT(*) FROM barangays")->fetchColumn();
$statHazardZones= $pdo->query("SELECT COUNT(*) FROM hazard_zones")->fetchColumn();
$statAffectedPop= $pdo->query("SELECT COALESCE(SUM(affected_population),0) FROM hazard_zones")->fetchColumn();
$statHouseholds = count($households_gps);

// ─── 7. Hazard types for legend ───────────────────────────────────────────────
$hazardTypes = $pdo->query("SELECT id, name, color FROM hazard_types ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// ─── 8. Build barangay → active hazard types lookup ──────────────────────────
$barangayHazards = [];
foreach ($hazardZones as $hz) {
    $bid = (int)$hz['barangay_id'];
    if (!isset($barangayHazards[$bid])) $barangayHazards[$bid] = [];
    if ($hz['hazard_type'] && !in_array($hz['hazard_type'], $barangayHazards[$bid])) {
        $barangayHazards[$bid][] = $hz['hazard_type'];
    }
}

// Attach active hazard list to each barangay
foreach ($barangays as &$b) {
    $bid = (int)$b['id'];
    $b['active_hazards']  = $barangayHazards[$bid] ?? [];
    $b['population']      = $b['population']      !== null ? (int)$b['population']      : null;
    $b['household_count'] = $b['household_count'] !== null ? (int)$b['household_count'] : null;
    $b['pwd_count']       = $b['pwd_count']       !== null ? (int)$b['pwd_count']       : null;
    $b['senior_count']    = $b['senior_count']    !== null ? (int)$b['senior_count']    : null;
    $b['ip_count']        = $b['ip_count']        !== null ? (int)$b['ip_count']        : null;
}
unset($b);

// ─── Encode for JS ────────────────────────────────────────────────────────────
$barangaysJson        = json_encode($barangays,        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$householdsJson       = json_encode($households_gps,   JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$hazardZonesJson      = json_encode($hazardZones,      JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$incidentPolygonsJson = json_encode($incidentPolygons, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$evacCentersJson      = json_encode($evacCenters,      JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$hazardTypesJson      = json_encode($hazardTypes,      JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Interactive Risk Map — DRMS</title>

  <!-- Bootstrap 5.3 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <!-- Font Awesome 6 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

  <style>
    *, *::before, *::after { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; overflow: hidden; height: 100%; font-family: 'Segoe UI', system-ui, sans-serif; }

    /* ── Layout ────────────────────────────────── */
    #appWrapper { display: flex; height: 100vh; }

    /* ── Sidebar ───────────────────────────────── */
    #mapSidebar {
      width: 280px; min-width: 280px;
      background: #1a1f2e;
      overflow-y: auto; overflow-x: hidden;
      display: flex; flex-direction: column;
      color: #cdd5e0;
      scrollbar-width: thin;
      scrollbar-color: #2d3548 #1a1f2e;
      z-index: 1000;
    }
    #mapSidebar::-webkit-scrollbar { width: 5px; }
    #mapSidebar::-webkit-scrollbar-track { background: #1a1f2e; }
    #mapSidebar::-webkit-scrollbar-thumb { background: #2d3548; border-radius: 3px; }

    /* Logo bar */
    .sb-logo-bar {
      display: flex; align-items: center; gap: 10px;
      padding: 16px 14px 12px;
      border-bottom: 1px solid #2d3548;
    }
    .sb-logo-bar img { width: 36px; height: 36px; border-radius: 8px; object-fit: cover; }
    .sb-logo-bar .sb-title { font-size: 0.82rem; font-weight: 700; color: #fff; line-height: 1.2; }
    .sb-logo-bar .sb-sub   { font-size: 0.7rem; color: #6b7a90; }

    /* Section headers */
    .sb-section-label {
      font-size: 0.65rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: 1.2px; color: #546e7a;
      padding: 14px 14px 4px;
    }

    /* Stat cards */
    .sb-stats { padding: 0 10px 4px; display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
    .sb-stat-card {
      background: #242b3d; border-radius: 8px; padding: 10px 10px 8px;
      border: 1px solid #2d3548;
    }
    .sb-stat-card .val { font-size: 1.25rem; font-weight: 700; color: #fff; line-height: 1; }
    .sb-stat-card .lbl { font-size: 0.65rem; color: #6b7a90; margin-top: 3px; line-height: 1.2; }
    .sb-stat-card .ico { font-size: 0.9rem; margin-bottom: 4px; }

    /* Legend items */
    .sb-legend { padding: 0 14px 6px; }
    .legend-item {
      display: flex; align-items: center; gap: 8px;
      font-size: 0.75rem; color: #b0bec5; padding: 2px 0;
    }
    .legend-swatch {
      width: 14px; height: 14px; border-radius: 3px; flex-shrink: 0;
      border: 1px solid rgba(255,255,255,0.12);
    }
    .legend-dot {
      width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0;
    }
    .legend-icon { width: 16px; text-align: center; font-size: 0.75rem; flex-shrink: 0; }
    .legend-dashed {
      width: 24px; height: 12px; border: 2px dashed #e67e22; border-radius: 2px; flex-shrink: 0;
    }

    /* Layer toggles */
    .sb-toggles { padding: 0 10px 6px; }
    .sb-toggle-row {
      display: flex; align-items: center; gap: 8px;
      padding: 5px 4px; border-radius: 6px; cursor: pointer;
      transition: background 0.15s;
    }
    .sb-toggle-row:hover { background: #242b3d; }
    .sb-toggle-row label { font-size: 0.78rem; color: #b0bec5; cursor: pointer; flex: 1; }
    .sb-toggle-row input[type=checkbox] { accent-color: #0d6efd; width: 14px; height: 14px; cursor: pointer; flex-shrink: 0; }
    .sb-always-on {
      font-size: 0.72rem; color: #546e7a; padding: 5px 4px;
      display: flex; align-items: center; gap: 8px;
    }
    .sb-always-on i { color: #3498db; }

    /* Nav links at bottom */
    .sb-nav { padding: 8px 10px 16px; margin-top: auto; border-top: 1px solid #2d3548; }
    .sb-nav a {
      display: flex; align-items: center; gap: 8px;
      padding: 7px 10px; border-radius: 6px;
      font-size: 0.78rem; color: #b0bec5; text-decoration: none;
      transition: background 0.15s, color 0.15s;
    }
    .sb-nav a:hover { background: #242b3d; color: #fff; }
    .sb-nav a.active { background: #0d6efd; color: #fff; }

    /* ── Map ────────────────────────────────────── */
    #map { flex: 1; background: #1a1f2e; }

    /* Leaflet popup overrides */
    .leaflet-popup-content-wrapper {
      border-radius: 8px !important; box-shadow: 0 4px 20px rgba(0,0,0,0.25) !important;
      font-size: 0.82rem !important;
    }
    .leaflet-popup-content { margin: 10px 14px !important; line-height: 1.5 !important; }
    .popup-title { font-weight: 700; font-size: 0.9rem; margin-bottom: 6px; color: #1a1f2e; }
    .popup-row { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 2px; }
    .popup-row .pk { color: #546e7a; font-size: 0.75rem; }
    .popup-row .pv { font-weight: 600; color: #1a1f2e; font-size: 0.78rem; }
    .popup-badge {
      display: inline-block; padding: 1px 7px; border-radius: 10px;
      font-size: 0.68rem; font-weight: 700;
    }
    .popup-divider { border: none; border-top: 1px solid #eee; margin: 6px 0; }
    .popup-progress { height: 6px; background: #e9ecef; border-radius: 3px; overflow: hidden; margin-top: 3px; }
    .popup-progress-fill { height: 100%; border-radius: 3px; transition: width 0.3s; }

    /* Leaflet layer control */
    .leaflet-control-layers { background: rgba(26,31,46,0.92) !important; border: 1px solid #2d3548 !important; border-radius: 8px !important; }
    .leaflet-control-layers-base label,
    .leaflet-control-layers-overlays label { color: #cdd5e0 !important; font-size: 0.8rem !important; }
    .leaflet-control-layers-separator { border-top: 1px solid #2d3548 !important; }
    .leaflet-control-layers-toggle { background-color: #1a1f2e !important; }

    /* Barangay tooltip */
    .barangay-label {
      background: transparent !important; border: none !important; box-shadow: none !important;
      font-size: 0.68rem; font-weight: 700; color: #fff;
      text-shadow: 0 1px 3px rgba(0,0,0,0.9), 0 0 6px rgba(0,0,0,0.7);
      white-space: nowrap; pointer-events: none;
    }

    /* Hazard icon markers */
    .hazard-div-icon {
      display: flex; align-items: center; justify-content: center;
      width: 26px !important; height: 26px !important;
      border-radius: 50%;
      background: rgba(26,31,46,0.85);
      border: 2px solid rgba(255,255,255,0.3);
      box-shadow: 0 2px 6px rgba(0,0,0,0.4);
      font-size: 12px;
    }

    /* Evac icon markers */
    .evac-div-icon {
      display: flex; align-items: center; justify-content: center;
      width: 30px !important; height: 30px !important;
      border-radius: 6px;
      background: rgba(26,31,46,0.9);
      border: 2px solid rgba(255,255,255,0.25);
      box-shadow: 0 2px 8px rgba(0,0,0,0.5);
      font-size: 15px;
    }

    /* Map attribution dark */
    .leaflet-control-attribution { background: rgba(26,31,46,0.7) !important; color: #6b7a90 !important; font-size: 0.6rem !important; }
    .leaflet-control-attribution a { color: #4a90d9 !important; }

    /* Loading overlay */
    #loadingOverlay {
      position: fixed; inset: 0; background: rgba(14,18,28,0.92);
      display: flex; align-items: center; justify-content: center; z-index: 9999;
      backdrop-filter: blur(4px);
    }
    .loader-card {
      background: linear-gradient(145deg, #1a3a5f, #0d1f33);
      border-radius: 12px; padding: 32px 36px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.6);
      border: 1px solid rgba(64,156,255,0.25);
      text-align: center; max-width: 380px;
    }
    .loader-card h4 { color: #fff; font-weight: 700; margin-bottom: 6px; }
    .loader-card p  { color: #7fb3d3; font-size: 0.85rem; margin-bottom: 18px; }
    .loader-bar { height: 5px; background: rgba(255,255,255,0.1); border-radius: 3px; overflow: hidden; }
    .loader-fill { height: 100%; background: linear-gradient(90deg,#409cff,#6bd0ff); border-radius: 3px; animation: barFill 1.8s ease-in-out infinite; }
    @keyframes barFill { 0%{width:0%} 60%{width:75%} 100%{width:100%} }
  </style>
</head>
<body>

<!-- Loading Overlay -->
<div id="loadingOverlay">
  <div class="loader-card">
    <div style="font-size:2rem;color:#409cff;margin-bottom:12px;"><i class="fas fa-map-location-dot"></i></div>
    <h4>Loading Risk Map</h4>
    <p>Fetching barangay boundaries, hazard zones, and incident data…</p>
    <div class="loader-bar"><div class="loader-fill"></div></div>
  </div>
</div>

<div id="appWrapper">

  <!-- ══════════════════ SIDEBAR ══════════════════ -->
  <div id="mapSidebar">

    <!-- Logo -->
    <div class="sb-logo-bar">
      <img src="<?= BASE_URL ?>logo.png" alt="DRMS" onerror="this.style.display='none'">
      <div>
        <div class="sb-title">Disaster Risk<br>Management System</div>
        <div class="sb-sub">Interactive Risk Map</div>
      </div>
    </div>

    <!-- Stats -->
    <div class="sb-section-label"><i class="fas fa-chart-bar me-1"></i>Overview</div>
    <div class="sb-stats">
      <div class="sb-stat-card">
        <div class="ico" style="color:#4a90d9;"><i class="fas fa-map"></i></div>
        <div class="val"><?= number_format((int)$statBarangays) ?></div>
        <div class="lbl">Barangays</div>
      </div>
      <div class="sb-stat-card">
        <div class="ico" style="color:#e74c3c;"><i class="fas fa-triangle-exclamation"></i></div>
        <div class="val"><?= number_format((int)$statHazardZones) ?></div>
        <div class="lbl">Hazard Zones</div>
      </div>
      <div class="sb-stat-card">
        <div class="ico" style="color:#e67e22;"><i class="fas fa-users"></i></div>
        <div class="val"><?= number_format((int)$statAffectedPop) ?></div>
        <div class="lbl">Affected Pop.</div>
      </div>
      <div class="sb-stat-card">
        <div class="ico" style="color:#27ae60;"><i class="fas fa-house"></i></div>
        <div class="val"><?= number_format((int)$statHouseholds) ?></div>
        <div class="lbl">Households (GPS)</div>
      </div>
    </div>

    <!-- Layer Toggles -->
    <div class="sb-section-label" style="margin-top:6px;"><i class="fas fa-layer-group me-1"></i>Layers</div>
    <div class="sb-toggles">
      <div class="sb-always-on">
        <i class="fas fa-map-location-dot fa-fw"></i>
        <span>Barangay Boundaries <span style="color:#3498db;">(Always visible)</span></span>
      </div>
      <div class="sb-toggle-row" onclick="toggleLayer('hazard', this)">
        <input type="checkbox" id="chk-hazard" checked>
        <label for="chk-hazard"><i class="fas fa-triangle-exclamation fa-fw" style="color:#e74c3c;margin-right:4px;"></i>Hazard Zones</label>
      </div>
      <div class="sb-toggle-row" onclick="toggleLayer('household', this)">
        <input type="checkbox" id="chk-household">
        <label for="chk-household"><i class="fas fa-house fa-fw" style="color:#3498db;margin-right:4px;"></i>Household Locations</label>
      </div>
      <div class="sb-toggle-row" onclick="toggleLayer('heatmap', this)">
        <input type="checkbox" id="chk-heatmap">
        <label for="chk-heatmap"><i class="fas fa-fire fa-fw" style="color:#fd7e14;margin-right:4px;"></i>Population Heatmap</label>
      </div>
      <div class="sb-toggle-row" onclick="toggleLayer('incident', this)">
        <input type="checkbox" id="chk-incident" checked>
        <label for="chk-incident"><i class="fas fa-circle-exclamation fa-fw" style="color:#e67e22;margin-right:4px;"></i>Active Incidents</label>
      </div>
      <div class="sb-toggle-row" onclick="toggleLayer('evac', this)">
        <input type="checkbox" id="chk-evac" checked>
        <label for="chk-evac"><i class="fas fa-house-medical fa-fw" style="color:#27ae60;margin-right:4px;"></i>Evacuation Centers</label>
      </div>
    </div>

    <!-- Legend: Risk Levels -->
    <div class="sb-section-label" style="margin-top:4px;"><i class="fas fa-circle-info me-1"></i>Risk Levels</div>
    <div class="sb-legend">
      <div class="legend-item"><span class="legend-swatch" style="background:#e74c3c;"></span>High Susceptible</div>
      <div class="legend-item"><span class="legend-swatch" style="background:#e67e22;"></span>Moderate Susceptible</div>
      <div class="legend-item"><span class="legend-swatch" style="background:#f1c40f;"></span>Low Susceptible</div>
      <div class="legend-item"><span class="legend-swatch" style="background:#9b59b6;"></span>Prone</div>
      <div class="legend-item"><span class="legend-swatch" style="background:#3498db;"></span>Generally Susceptible</div>
      <div class="legend-item"><span class="legend-swatch" style="background:#922b21;"></span>PEIS VIII Very destructive</div>
      <div class="legend-item"><span class="legend-swatch" style="background:#cb4335;"></span>PEIS VII Destructive</div>
      <div class="legend-item"><span class="legend-swatch" style="background:#1abc9c;"></span>General Inundation</div>
      <div class="legend-item"><span class="legend-swatch" style="background:#27ae60;"></span>Not Susceptible</div>
    </div>

    <!-- Legend: Hazard Types -->
    <div class="sb-section-label" style="margin-top:4px;"><i class="fas fa-bolt me-1"></i>Hazard Types</div>
    <div class="sb-legend">
      <?php foreach ($hazardTypes as $ht): ?>
      <div class="legend-item">
        <span class="legend-dot" style="background:<?= htmlspecialchars($ht['color'] ?: '#adb5bd') ?>;"></span>
        <?= htmlspecialchars($ht['name']) ?>
      </div>
      <?php endforeach; ?>
      <?php if (empty($hazardTypes)): ?>
        <div style="font-size:0.72rem;color:#546e7a;font-style:italic;">No hazard types defined</div>
      <?php endif; ?>
    </div>

    <!-- Legend: Other Markers -->
    <div class="sb-section-label" style="margin-top:4px;"><i class="fas fa-map-pin me-1"></i>Other Markers</div>
    <div class="sb-legend">
      <div class="legend-item"><span class="legend-dot" style="background:#e74c3c;border:1px solid #c0392b;"></span>Vulnerable Household (PWD/Senior/Infant/Pregnant)</div>
      <div class="legend-item"><span class="legend-dot" style="background:#3498db;border:1px solid #2980b9;"></span>Regular Household</div>
      <div class="legend-item"><span class="legend-icon" style="color:#27ae60;"><i class="fas fa-house-medical"></i></span>Evacuation Center (Operational)</div>
      <div class="legend-item"><span class="legend-icon" style="color:#f1c40f;"><i class="fas fa-house-medical"></i></span>Evacuation Center (Maintenance)</div>
      <div class="legend-item"><span class="legend-icon" style="color:#e74c3c;"><i class="fas fa-house-medical"></i></span>Evacuation Center (Closed)</div>
      <div class="legend-item"><span class="legend-dashed"></span>Active Incident Area</div>
    </div>

    <!-- Bottom Nav -->
    <div class="sb-nav">
      <a href="<?= BASE_URL ?>dashboard.php">
        <i class="fas fa-gauge fa-fw"></i> Back to Dashboard
      </a>
      <a href="<?= BASE_URL ?>households.php">
        <i class="fas fa-house-user fa-fw"></i> Manage Households
      </a>
      <a href="<?= BASE_URL ?>incident_list.php">
        <i class="fas fa-clipboard-list fa-fw"></i> View Incidents
      </a>
    </div>

  </div><!-- /sidebar -->

  <!-- ══════════════════ MAP ══════════════════ -->
  <div id="map"></div>

</div><!-- /appWrapper -->

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>

<script>
// ════════════════════════════════════════════════════════════════
//  PHP DATA
// ════════════════════════════════════════════════════════════════
const barangaysData        = <?= $barangaysJson ?>;
const householdsData       = <?= $householdsJson ?>;
const hazardZonesData      = <?= $hazardZonesJson ?>;
const incidentPolygonsData = <?= $incidentPolygonsJson ?>;
const evacCentersData      = <?= $evacCentersJson ?>;

// ════════════════════════════════════════════════════════════════
//  HELPERS
// ════════════════════════════════════════════════════════════════
const RISK_COLORS = {
  'High Susceptible':           '#e74c3c',
  'Moderate Susceptible':       '#e67e22',
  'Low Susceptible':            '#f1c40f',
  'Prone':                      '#9b59b6',
  'Generally Susceptible':      '#3498db',
  'PEIS VIII Very destructive': '#922b21',
  'PEIS VII Destructive':       '#cb4335',
  'General Inundation':         '#1abc9c',
  'Not Susceptible':            '#27ae60'
};

function getRiskColor(riskLevel) {
  return RISK_COLORS[riskLevel] || '#adb5bd';
}

const HAZARD_ICONS = {
  'flood':          { icon: 'fa-water',              color: '#3498db' },
  'flooding':       { icon: 'fa-water',              color: '#3498db' },
  'storm surge':    { icon: 'fa-wind',               color: '#17a2b8' },
  'tsunami':        { icon: 'fa-water',              color: '#0dcaf0' },
  'landslide':      { icon: 'fa-mountain',           color: '#8B4513' },
  'earthquake':     { icon: 'fa-circle-exclamation', color: '#e74c3c' },
  'ground shaking': { icon: 'fa-circle-exclamation', color: '#e74c3c' },
  'volcanic':       { icon: 'fa-fire',               color: '#fd7e14' }
};

function getHazardIconCfg(hazardTypeName) {
  if (!hazardTypeName) return { icon: 'fa-triangle-exclamation', color: '#ffc107' };
  const key = hazardTypeName.toLowerCase();
  for (const [k, v] of Object.entries(HAZARD_ICONS)) {
    if (key.includes(k)) return v;
  }
  return { icon: 'fa-triangle-exclamation', color: '#ffc107' };
}

function parseCoordinates(coordStr) {
  if (!coordStr) return null;
  const parts = coordStr.split(',');
  if (parts.length < 2) return null;
  const lat = parseFloat(parts[0].trim());
  const lng = parseFloat(parts[1].trim());
  if (isNaN(lat) || isNaN(lng)) return null;
  return [lat, lng];
}

function safeParseGeoJSON(jsonStr) {
  if (!jsonStr) return null;
  try {
    const parsed = (typeof jsonStr === 'string') ? JSON.parse(jsonStr) : jsonStr;
    return parsed;
  } catch (e) {
    return null;
  }
}

function getStatusBadgeStyle(status) {
  const s = (status || '').toLowerCase();
  if (s === 'ongoing')     return 'background:#dc3545;color:#fff;';
  if (s === 'monitoring')  return 'background:#fd7e14;color:#fff;';
  if (s === 'resolved')    return 'background:#198754;color:#fff;';
  return 'background:#6c757d;color:#fff;';
}

function getEvacStatusColor(status) {
  const s = (status || '').toLowerCase();
  if (s === 'operational') return '#27ae60';
  if (s === 'maintenance') return '#f1c40f';
  if (s === 'closed')      return '#e74c3c';
  return '#adb5bd';
}

function formatNumber(n) {
  if (n === null || n === undefined || n === '') return '—';
  return Number(n).toLocaleString();
}

// ════════════════════════════════════════════════════════════════
//  MAP INIT
// ════════════════════════════════════════════════════════════════
const map = L.map('map', {
  center: [12.84, 120.77],
  zoom: 12,
  zoomControl: true
});

// ── Tile Layers ──────────────────────────────────────────────────
const tileLayers = {
  'Street (OSM)': L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 19
  }),
  'Satellite': L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
    attribution: '© Esri — Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP',
    maxZoom: 19
  }),
  'Terrain': L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://opentopomap.org">OpenTopoMap</a>',
    maxZoom: 17
  }),
  'Hybrid': L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}', {
    attribution: '© Esri',
    maxZoom: 19
  })
};

tileLayers['Street (OSM)'].addTo(map);

// ════════════════════════════════════════════════════════════════
//  LAYER 1 — BARANGAY BOUNDARIES (always on, not in overlays)
// ════════════════════════════════════════════════════════════════
const barangayLayer = L.layerGroup().addTo(map);

barangaysData.forEach(function(b) {
  const geoJSON = safeParseGeoJSON(b.boundary_geojson);
  const coords  = parseCoordinates(b.coordinates);

  const activeHazardsList = (b.active_hazards && b.active_hazards.length > 0)
    ? b.active_hazards.map(h => '<li style="margin:0;">' + h + '</li>').join('')
    : '<li style="margin:0;color:#999;">None recorded</li>';

  const popupHTML = `
    <div style="min-width:190px;">
      <div class="popup-title"><i class="fas fa-map-location-dot" style="color:#4a90d9;"></i> ${b.name || '—'}</div>
      <hr class="popup-divider">
      <div class="popup-row"><span class="pk">Population</span><span class="pv">${formatNumber(b.population)}</span></div>
      <div class="popup-row"><span class="pk">Households</span><span class="pv">${formatNumber(b.household_count)}</span></div>
      <div class="popup-row"><span class="pk">PWD</span><span class="pv">${formatNumber(b.pwd_count)}</span></div>
      <div class="popup-row"><span class="pk">Seniors</span><span class="pv">${formatNumber(b.senior_count)}</span></div>
      <div class="popup-row"><span class="pk">IPs</span><span class="pv">${formatNumber(b.ip_count)}</span></div>
      <hr class="popup-divider">
      <div style="font-size:0.73rem;color:#546e7a;font-weight:700;margin-bottom:2px;">Active Hazards</div>
      <ul style="margin:0;padding-left:16px;font-size:0.76rem;color:#333;">${activeHazardsList}</ul>
    </div>`;

  if (geoJSON) {
    const poly = L.geoJSON(geoJSON, {
      style: {
        color: '#4a90d9',
        weight: 2,
        fillColor: 'rgba(100,150,200,0.1)',
        fillOpacity: 0.12,
        opacity: 0.9
      }
    });
    poly.bindPopup(popupHTML, { maxWidth: 260 });
    poly.addTo(barangayLayer);

    // Permanent label at centroid
    try {
      const bounds  = poly.getBounds();
      const center  = bounds.getCenter();
      L.tooltip({ permanent: true, direction: 'center', className: 'barangay-label', interactive: false })
        .setContent(b.name || '')
        .setLatLng(center)
        .addTo(barangayLayer);
    } catch(e) {}

  } else if (coords) {
    // No boundary: show gray dot
    const marker = L.circleMarker(coords, {
      radius: 5, color: '#6b7a90', fillColor: '#adb5bd',
      fillOpacity: 0.8, weight: 1
    });
    marker.bindPopup(`<div style="min-width:180px;">${popupHTML}<hr class="popup-divider"><div style="font-size:0.72rem;color:#999;font-style:italic;">(No boundary drawn)</div></div>`, { maxWidth: 260 });
    marker.addTo(barangayLayer);
  }
});

// ════════════════════════════════════════════════════════════════
//  LAYER 2 — HAZARD ZONES (toggle ON)
// ════════════════════════════════════════════════════════════════
const hazardLayer = L.layerGroup();

hazardZonesData.forEach(function(hz) {
  // Use the barangay's boundary_geojson — skip if none
  const geoJSON = safeParseGeoJSON(hz.barangay_boundary);
  if (!geoJSON) return;

  const color = getRiskColor(hz.risk_level);

  const poly = L.geoJSON(geoJSON, {
    style: {
      color:       color,
      weight:      2,
      fillColor:   color,
      fillOpacity: 0.4,
      opacity:     0.9
    }
  });

  const riskBadgeStyle = `background:${color};color:${(color === '#f1c40f') ? '#333' : '#fff'};`;

  const popupHTML = `
    <div style="min-width:200px;">
      <div class="popup-title">
        <i class="fas fa-triangle-exclamation" style="color:${hz.hazard_color || '#e74c3c'};"></i>
        ${hz.hazard_type || '—'}
      </div>
      <hr class="popup-divider">
      <div class="popup-row">
        <span class="pk">Risk Level</span>
        <span><span class="popup-badge" style="${riskBadgeStyle}">${hz.risk_level || '—'}</span></span>
      </div>
      <div class="popup-row"><span class="pk">Barangay</span><span class="pv">${hz.barangay_name || '—'}</span></div>
      <div class="popup-row"><span class="pk">Affected Pop.</span><span class="pv">${formatNumber(hz.affected_population)}</span></div>
      <div class="popup-row"><span class="pk">Area (km²)</span><span class="pv">${hz.area_km2 !== null ? Number(hz.area_km2).toFixed(2) : '—'}</span></div>
      ${hz.description ? `<hr class="popup-divider"><div style="font-size:0.76rem;color:#555;">${hz.description}</div>` : ''}
    </div>`;

  poly.bindPopup(popupHTML, { maxWidth: 280 });
  poly.addTo(hazardLayer);

  // Hazard type icon at barangay center
  const center = parseCoordinates(hz.barangay_coordinates);
  if (center) {
    const cfg = getHazardIconCfg(hz.hazard_type);
    const iconHtml = `<div class="hazard-div-icon"><i class="fas ${cfg.icon}" style="color:${cfg.color};"></i></div>`;
    const marker = L.marker(center, {
      icon: L.divIcon({ html: iconHtml, className: '', iconSize: [26, 26], iconAnchor: [13, 13] })
    });
    marker.bindPopup(popupHTML, { maxWidth: 280 });
    marker.addTo(hazardLayer);
  }
});

hazardLayer.addTo(map);

// ════════════════════════════════════════════════════════════════
//  LAYER 3 — HOUSEHOLD LOCATIONS (toggle OFF)
// ════════════════════════════════════════════════════════════════
const householdLayer = L.layerGroup();

householdsData.forEach(function(hh) {
  const isVulnerable = (hh.pwd_count > 0 || hh.senior_count > 0 ||
                        hh.infant_count > 0 || hh.pregnant_count > 0);
  const fc = isVulnerable ? '#e74c3c' : '#3498db';

  const vulnParts = [];
  if (hh.pwd_count     > 0) vulnParts.push(`<i class="fas fa-wheelchair" style="color:#e74c3c;"></i> PWD: ${hh.pwd_count}`);
  if (hh.senior_count  > 0) vulnParts.push(`<i class="fas fa-person-cane" style="color:#e67e22;"></i> Senior: ${hh.senior_count}`);
  if (hh.infant_count  > 0) vulnParts.push(`<i class="fas fa-baby" style="color:#9b59b6;"></i> Infant: ${hh.infant_count}`);
  if (hh.pregnant_count> 0) vulnParts.push(`<i class="fas fa-person-pregnant" style="color:#3498db;"></i> Pregnant: ${hh.pregnant_count}`);

  const popupHTML = `
    <div style="min-width:185px;">
      <div class="popup-title"><i class="fas fa-house" style="color:${fc};"></i> ${hh.household_head || '—'}</div>
      <hr class="popup-divider">
      <div class="popup-row"><span class="pk">Family Members</span><span class="pv">${formatNumber(hh.family_members)}</span></div>
      <div class="popup-row"><span class="pk">Zone</span><span class="pv">${hh.zone || '—'}</span></div>
      ${vulnParts.length > 0 ? `
      <hr class="popup-divider">
      <div style="font-size:0.72rem;color:#546e7a;font-weight:700;margin-bottom:3px;">Vulnerabilities</div>
      <div style="font-size:0.77rem;display:flex;flex-direction:column;gap:2px;">${vulnParts.join('')}</div>` : ''}
    </div>`;

  const marker = L.circleMarker([hh.latitude, hh.longitude], {
    radius: 5, weight: 1,
    color: isVulnerable ? '#c0392b' : '#2980b9',
    fillColor: fc, fillOpacity: 0.8
  });
  marker.bindPopup(popupHTML, { maxWidth: 240 });
  marker.addTo(householdLayer);
});

// ════════════════════════════════════════════════════════════════
//  LAYER 4 — POPULATION HEATMAP (toggle OFF)
// ════════════════════════════════════════════════════════════════
const heatPoints = householdsData.map(h => [h.latitude, h.longitude, h.family_members || 1]);
const heatmapLayer = L.layerGroup();
if (heatPoints.length > 0) {
  const heat = L.heatLayer(heatPoints, { radius: 25, blur: 15, maxZoom: 17 });
  heat.addTo(heatmapLayer);
}

// ════════════════════════════════════════════════════════════════
//  LAYER 5 — ACTIVE INCIDENTS (toggle ON)
// ════════════════════════════════════════════════════════════════
const incidentLayer = L.layerGroup();

incidentPolygonsData.forEach(function(inc) {
  const geoJSON = safeParseGeoJSON(inc.polygon_geojson);
  if (!geoJSON) return;

  const color = inc.hazard_color || '#e67e22';
  const date  = inc.incident_date ? inc.incident_date.substring(0, 10) : '—';

  const popupHTML = `
    <div style="min-width:200px;">
      <div class="popup-title"><i class="fas fa-circle-exclamation" style="color:${color};"></i> ${inc.title || '—'}</div>
      <hr class="popup-divider">
      <div class="popup-row"><span class="pk">Disaster Type</span><span class="pv">${inc.hazard_type || '—'}</span></div>
      <div class="popup-row"><span class="pk">Date</span><span class="pv">${date}</span></div>
      <div class="popup-row">
        <span class="pk">Status</span>
        <span><span class="popup-badge" style="${getStatusBadgeStyle(inc.status)}">${inc.status || '—'}</span></span>
      </div>
      <div class="popup-row"><span class="pk">Total Affected</span><span class="pv">${formatNumber(inc.total_affected_population)}</span></div>
      <hr class="popup-divider">
      <a href="<?= BASE_URL ?>incident_reports.php?id=${inc.id}" style="font-size:0.78rem;color:#0d6efd;text-decoration:none;">
        <i class="fas fa-arrow-up-right-from-square"></i> View Details
      </a>
    </div>`;

  const poly = L.geoJSON(geoJSON, {
    style: {
      color:       color,
      weight:      2.5,
      fillColor:   color,
      fillOpacity: 0.3,
      dashArray:   '8,4',
      opacity:     0.9
    }
  });
  poly.bindPopup(popupHTML, { maxWidth: 280 });
  poly.addTo(incidentLayer);
});

incidentLayer.addTo(map);

// ════════════════════════════════════════════════════════════════
//  LAYER 6 — EVACUATION CENTERS (toggle ON)
// ════════════════════════════════════════════════════════════════
const evacCenterLayer = L.layerGroup();

evacCentersData.forEach(function(ec) {
  const color = getEvacStatusColor(ec.status);
  const pct   = ec.capacity > 0 ? Math.round((ec.current_occupancy / ec.capacity) * 100) : 0;
  const pctClamped = Math.min(pct, 100);
  const barColor = pctClamped >= 90 ? '#e74c3c' : pctClamped >= 60 ? '#f1c40f' : '#27ae60';

  const iconHtml = `<div class="evac-div-icon"><i class="fas fa-house-medical" style="color:${color};"></i></div>`;

  const popupHTML = `
    <div style="min-width:210px;">
      <div class="popup-title"><i class="fas fa-house-medical" style="color:${color};"></i> ${ec.name || '—'}</div>
      <hr class="popup-divider">
      <div class="popup-row">
        <span class="pk">Status</span>
        <span><span class="popup-badge" style="background:${color};color:${color === '#f1c40f' ? '#333' : '#fff'};">${ec.status || '—'}</span></span>
      </div>
      <div class="popup-row"><span class="pk">Barangay</span><span class="pv">${ec.barangay_name || '—'}</span></div>
      <div class="popup-row"><span class="pk">Occupancy</span><span class="pv">${formatNumber(ec.current_occupancy)} / ${formatNumber(ec.capacity)}</span></div>
      <div class="popup-progress"><div class="popup-progress-fill" style="width:${pctClamped}%;background:${barColor};"></div></div>
      <div style="font-size:0.7rem;color:#888;text-align:right;margin-top:1px;">${pctClamped}% full</div>
      ${ec.facilities ? `<div class="popup-row" style="margin-top:4px;"><span class="pk">Facilities</span><span class="pv" style="max-width:140px;text-align:right;">${ec.facilities}</span></div>` : ''}
      ${ec.contact_person ? `<div class="popup-row"><span class="pk">Contact</span><span class="pv">${ec.contact_person}</span></div>` : ''}
      ${ec.contact_number ? `<div class="popup-row"><span class="pk">Number</span><span class="pv">${ec.contact_number}</span></div>` : ''}
    </div>`;

  const marker = L.marker([ec.latitude, ec.longitude], {
    icon: L.divIcon({ html: iconHtml, className: '', iconSize: [30, 30], iconAnchor: [15, 15] })
  });
  marker.bindPopup(popupHTML, { maxWidth: 270 });
  marker.addTo(evacCenterLayer);
});

evacCenterLayer.addTo(map);

// ════════════════════════════════════════════════════════════════
//  OVERLAYS + LEAFLET LAYER CONTROL
// ════════════════════════════════════════════════════════════════
const overlays = {
  'Hazard Zones':        hazardLayer,
  'Household Locations': householdLayer,
  'Population Heatmap':  heatmapLayer,
  'Active Incidents':    incidentLayer,
  'Evacuation Centers':  evacCenterLayer
};

L.control.layers(tileLayers, overlays, { position: 'topright', collapsed: true }).addTo(map);

// ════════════════════════════════════════════════════════════════
//  SIDEBAR LAYER TOGGLE SYNC
// ════════════════════════════════════════════════════════════════
const layerMap = {
  'hazard':    hazardLayer,
  'household': householdLayer,
  'heatmap':   heatmapLayer,
  'incident':  incidentLayer,
  'evac':      evacCenterLayer
};

function toggleLayer(key, rowEl) {
  // If click came from the row div, find the checkbox within it
  const chk = rowEl.querySelector ? rowEl.querySelector('input[type=checkbox]') : document.getElementById('chk-' + key);
  if (!chk) return;
  // Toggle checkbox state (click on row toggles it)
  // But if the click came from the checkbox itself, the checkbox already toggled
  // We need to handle this carefully — attach to the row's onclick, prevent double-toggle
  const layer = layerMap[key];
  if (!layer) return;
  if (chk.checked) {
    if (!map.hasLayer(layer)) map.addLayer(layer);
  } else {
    if (map.hasLayer(layer)) map.removeLayer(layer);
  }
}

// Attach direct checkbox listeners (for when user clicks the checkbox input directly)
Object.keys(layerMap).forEach(function(key) {
  const chk = document.getElementById('chk-' + key);
  if (!chk) return;
  chk.addEventListener('change', function() {
    const layer = layerMap[key];
    if (!layer) return;
    if (this.checked) {
      if (!map.hasLayer(layer)) map.addLayer(layer);
    } else {
      if (map.hasLayer(layer)) map.removeLayer(layer);
    }
  });
});

// Fix row onclick — only toggle when clicking the row background (not the label/checkbox directly)
document.querySelectorAll('.sb-toggle-row').forEach(function(row) {
  row.addEventListener('click', function(e) {
    // If click was on the checkbox itself, the change event handles it — do not double-toggle
    if (e.target.type === 'checkbox') return;
    // Otherwise toggle the checkbox
    const chk = row.querySelector('input[type=checkbox]');
    if (chk) {
      chk.checked = !chk.checked;
      chk.dispatchEvent(new Event('change'));
    }
  });
});

// Sync Leaflet's built-in control back to sidebar checkboxes
map.on('overlayadd', function(e) {
  const keyMap = {
    'Hazard Zones':        'hazard',
    'Household Locations': 'household',
    'Population Heatmap':  'heatmap',
    'Active Incidents':    'incident',
    'Evacuation Centers':  'evac'
  };
  const key = keyMap[e.name];
  if (key) { const chk = document.getElementById('chk-' + key); if (chk) chk.checked = true; }
});
map.on('overlayremove', function(e) {
  const keyMap = {
    'Hazard Zones':        'hazard',
    'Household Locations': 'household',
    'Population Heatmap':  'heatmap',
    'Active Incidents':    'incident',
    'Evacuation Centers':  'evac'
  };
  const key = keyMap[e.name];
  if (key) { const chk = document.getElementById('chk-' + key); if (chk) chk.checked = false; }
});

// ════════════════════════════════════════════════════════════════
//  HIDE LOADING OVERLAY
// ════════════════════════════════════════════════════════════════
map.whenReady(function() {
  setTimeout(function() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
      overlay.style.transition = 'opacity 0.4s ease';
      overlay.style.opacity = '0';
      setTimeout(function() { overlay.style.display = 'none'; }, 420);
    }
  }, 400);
});
</script>
</body>
</html>
