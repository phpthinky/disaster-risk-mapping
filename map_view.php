<?php
// map_view.php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Ensure boundary_geojson column exists
try { $pdo->exec("ALTER TABLE barangays ADD COLUMN IF NOT EXISTS boundary_geojson LONGTEXT DEFAULT NULL"); } catch(PDOException $e) {}
try { $pdo->exec("ALTER TABLE hazard_zones ADD COLUMN IF NOT EXISTS polygon_geojson LONGTEXT DEFAULT NULL"); } catch(PDOException $e) {}

// Get all barangays with their hazard data and coordinates
$barangays = $pdo->query("
    SELECT
        b.*,
        COALESCE(pd.total_population, b.population) as display_population,
        pd.total_population as actual_population_data,
        b.population as base_population,
        COALESCE(SUM(hz.affected_population), 0) as total_affected,
        COUNT(hz.id) as hazard_count,
        MAX(CASE WHEN hz.risk_level = 'high' THEN 1 ELSE 0 END) as has_high_risk,
        b.boundary_geojson,
        COUNT(DISTINCT hh.id) as household_count
    FROM barangays b
    LEFT JOIN hazard_zones hz ON b.id = hz.barangay_id
    LEFT JOIN (
        SELECT barangay_id, MAX(total_population) as total_population
        FROM population_data
        GROUP BY barangay_id
    ) as pd ON b.id = pd.barangay_id
    LEFT JOIN households hh ON b.id = hh.barangay_id
    GROUP BY b.id, b.name, b.coordinates, b.population, pd.total_population, b.boundary_geojson
")->fetchAll();

// Get households with valid GPS coordinates for map layers
$households_gps = $pdo->query("
    SELECT h.id, h.household_head, h.barangay_id, h.family_members,
           h.pwd_count, h.senior_count, h.infant_count, h.pregnant_count, h.minor_count,
           h.latitude, h.longitude,
           b.name as barangay_name
    FROM households h
    JOIN barangays b ON h.barangay_id = b.id
    WHERE h.latitude BETWEEN 12.50 AND 13.20
      AND h.longitude BETWEEN 120.50 AND 121.20
")->fetchAll();

// Get evacuation centers
try {
    $evacCenters = $pdo->query("
        SELECT ec.*, b.name as barangay_name
        FROM evacuation_centers ec
        LEFT JOIN barangays b ON ec.barangay_id = b.id
        WHERE ec.latitude IS NOT NULL AND ec.longitude IS NOT NULL
    ")->fetchAll();
} catch (PDOException $e) {
    $evacCenters = [];
}

// Get active/ongoing incident reports for map layer
try {
    $incidentPolygons = $pdo->query("
        SELECT ir.id, ir.title, ir.incident_date, ir.status,
               ir.polygon_geojson, ir.total_affected_population,
               ht.name as hazard_type_name, ht.color as hazard_color
        FROM incident_reports ir
        LEFT JOIN hazard_types ht ON ir.hazard_type_id = ht.id
        WHERE ir.polygon_geojson IS NOT NULL
          AND ir.status IN ('ongoing', 'monitoring')
        ORDER BY ir.incident_date DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $incidentPolygons = [];
}

// Get all hazard zones with details
$hazardZones = $pdo->query("
    SELECT 
        hz.*,
        ht.name as hazard_name,
        ht.color,
        ht.icon,
        b.name as barangay_name,
        b.population as barangay_population
    FROM hazard_zones hz
    JOIN hazard_types ht ON hz.hazard_type_id = ht.id
    JOIN barangays b ON hz.barangay_id = b.id
")->fetchAll();

// Get hazard types for legend
$hazardTypes = $pdo->query("SELECT * FROM hazard_types")->fetchAll();

$hazardIcons = [
    'Flood' => [
        'icon' => 'fa-water',
        'color' => '#3498db',
        'element' => 'water'
    ],
    'Landslide' => [
        'icon' => 'fa-mountain',
        'color' => '#e67e22',
        'element' => 'earth'
    ],
    'Coastal Erosion' => [
        'icon' => 'fa-water',
        'color' => '#9b59b6',
        'element' => 'water'
    ],
    'Fire Risk' => [
        'icon' => 'fa-fire',
        'color' => '#e74c3c',
        'element' => 'fire'
    ],
    'Earthquake' => [
        'icon' => 'fa-hill-rockslide',
        'color' => '#c0392b',
        'element' => 'earth'
    ]
];

// Update hazard types with icon information
foreach ($hazardTypes as &$type) {
    $typeName = $type['name'];
    if (isset($hazardIcons[$typeName])) {
        $type['icon_class'] = $hazardIcons[$typeName]['icon'];
        $type['element'] = $hazardIcons[$typeName]['element'];
    } else {
        $type['icon_class'] = 'fa-exclamation-triangle';
        $type['element'] = 'general';
    }
}
unset($type); // Break reference


// Calculate risk statistics for the map
// Calculate risk statistics for the map - FIXED QUERY
$riskStatsStmt = $pdo->query("
    SELECT 
        risk_level,
        COUNT(*) as zone_count,
        SUM(affected_population) as total_affected,
        AVG(area_km2) as avg_area
    FROM hazard_zones 
    GROUP BY risk_level
");
$riskStats = [];
while ($row = $riskStatsStmt->fetch(PDO::FETCH_ASSOC)) {
    $riskStats[$row['risk_level']] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interactive Map - Sablayan Risk Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --success-color: #27ae60;
            --warning-color: #f39c12;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        
        #map {
            height: 600px;
            width: 100%;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid #eee;
            font-weight: 600;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            margin-right: 10px;
            border-radius: 3px;
            border: 1px solid #666;
        }
        
        .risk-high { color: #e74c3c; font-weight: bold; }
        .risk-medium { color: #f39c12; font-weight: bold; }
        .risk-low { color: #27ae60; font-weight: bold; }
        
        .map-controls {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1000;
            background: white;
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .layer-btn {
            margin: 2px;
        }
        
        .hazard-flood { background-color: #3498db; }
        .hazard-landslide { background-color: #e67e22; }
        .hazard-coastal { background-color: #9b59b6; }
        .hazard-fire { background-color: #e74c3c; }
        .hazard-earthquake { background-color: #c0392b; }
        
        .main-content {
            padding: 20px;
            height: calc(100vh - 56px);
            overflow-y: auto;
        }
        
        .btn-group .btn {
            border-radius: 4px !important;
        }
    </style>
    
    <style>
/* Hazard icon styles */
.custom-hazard-icon {
    background: transparent !important;
    border: none !important;
}

.hazard-legend {
    margin-top: 10px;
    padding: 10px;
    background: rgba(255, 255, 255, 0.9);
    border-radius: 5px;
}

.hazard-legend-item {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
    padding: 5px;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.7);
}

.hazard-icon-display {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 10px;
    color: white;
    font-size: 14px;
}

.hazard-tooltip {
    font-weight: bold;
    background: rgba(255, 255, 255, 0.95);
    border: 2px solid;
    border-radius: 5px;
    padding: 5px;
}

/* Element-specific styles */
.water-hazard { border-color: #3498db !important; }
.earth-hazard { border-color: #e67e22 !important; }
.fire-hazard { border-color: #e74c3c !important; }
</style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Interactive Risk Map</h2>
                    <div class="btn-group">
                        <button class="btn btn-outline-primary active" id="riskViewBtn">
                            <i class="fas fa-exclamation-triangle me-1"></i> Risk View
                        </button>
                        <button class="btn btn-outline-primary" id="populationViewBtn">
                            <i class="fas fa-users me-1"></i> Population View
                        </button>
                        <button class="btn btn-outline-primary" id="hazardsViewBtn">
                            <i class="fas fa-layer-group me-1"></i> Hazards View
                        </button>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-body position-relative">
                        <div id="map"></div>
                        <div class="map-controls">
                            <div class="btn-group-vertical">
                                <button class="btn btn-sm btn-light layer-btn" id="searchLocationBtn" title="Search Location">
                                    <i class="fas fa-search-location"></i>
                                </button>
                                <button class="btn btn-sm btn-light layer-btn" id="measureBtn" title="Measure Distance">
                                    <i class="fas fa-ruler-combined"></i>
                                </button>
                                <button class="btn btn-sm btn-light layer-btn" id="printMapBtn" title="Print Map" style="display: none;">
                                    <i class="fas fa-print"></i>
                                </button>
                                <button class="btn btn-sm btn-light layer-btn" id="exportDataBtn" title="Export Data" style="display: none;">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-layer-group me-2"></i> Map Legend & Information
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Risk Levels</h6>
                                        <div class="legend-item">
                                            <div class="legend-color" style="background-color: #e74c3c;"></div>
                                            <span>High Risk Area</span>
                                        </div>
                                        <div class="legend-item">
                                            <div class="legend-color" style="background-color: #f39c12;"></div>
                                            <span>Medium Risk Area</span>
                                        </div>
                                        <div class="legend-item">
                                            <div class="legend-color" style="background-color: #27ae60;"></div>
                                            <span>Low Risk Area</span>
                                        </div>
                                        <div class="legend-item">
                                            <div class="legend-color" style="background-color: #95a5a6;"></div>
                                            <span>No Risk Data</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Hazard Types</h6>
                                        <?php foreach ($hazardTypes as $type): ?>
                                            <div class="legend-item">
                                                <div class="legend-color" style="background-color: <?php echo $type['color']; ?>;"></div>
                                                <span><?php echo htmlspecialchars($type['name']); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <hr>
                                <div class="row mt-3">
                                    <div class="col-md-4">
                                        <small class="text-muted">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            Barangays: <?php echo count($barangays); ?>
                                        </small>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            Hazard Zones: <?php echo count($hazardZones); ?>
                                        </small>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">
                                            <i class="fas fa-users me-1"></i>
                                            Total Affected: <?php echo number_format(array_sum(array_column($hazardZones, 'affected_population'))); ?>
                                        </small>
                                    </div>
                                </div>

                                <div class="row mt-3">
                                    <div class="col-md-12">
                                        <h6>Data Sources</h6>
                                        <div class="legend-item">
                                            <div class="legend-color" style="background-color: #3498db;"></div>
                                            <span>Population Data (Updated)</span>
                                        </div>
                                        <div class="legend-item">
                                            <div class="legend-color" style="background-color: #2980b9;"></div>
                                            <span>Population Data (Base)</span>
                                        </div>
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Updated data comes from recent population surveys, base data from barangay records.
                                        </small>
                                    </div>
                                </div>
                                
<div class="row mt-3">
    <div class="col-md-12">
        <h6>Hazard Icons Guide</h6>
        <div class="hazard-legend">
            <div class="hazard-legend-item">
                <div class="hazard-icon-display" style="background-color: #3498db;">
                    <i class="fas fa-water"></i>
                </div>
                <span>Flooding</span>
            </div>
            <div class="hazard-legend-item">
                <div class="hazard-icon-display" style="background-color: #8e44ad;">
                    <i class="fas fa-water"></i>
                </div>
                <span>Storm Surge</span>
            </div>
            <div class="hazard-legend-item">
                <div class="hazard-icon-display" style="background-color: #2980b9;">
                    <i class="fas fa-water"></i>
                </div>
                <span>Tsunami</span>
            </div>
            <div class="hazard-legend-item">
                <div class="hazard-icon-display" style="background-color: #7f8c8d;">
                    <i class="fas fa-house-crack"></i>
                </div>
                <span>Liquefaction</span>
            </div>
            <div class="hazard-legend-item">
                <div class="hazard-icon-display" style="background-color: #c0392b;">
                    <i class="fas fa-hill-rockslide"></i>
                </div>
                <span>Ground Shaking</span>
            </div>
            <div class="hazard-legend-item">
                <div class="hazard-icon-display" style="background-color: #e67e22;">
                    <i class="fas fa-mountain"></i>
                </div>
                <span>Landslide</span>
            </div>
        </div>
    </div>
</div>

                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-info-circle me-2"></i> Map Tools & Actions
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button class="btn btn-primary" id="addHazardBtn">
                                        <i class="fas fa-plus me-2"></i> Add Hazard Zone
                                    </button>
                                    <button class="btn btn-outline-primary" id="refreshDataBtn">
                                        <i class="fas fa-sync-alt me-2"></i> Refresh Map Data
                                    </button>
                                    <button class="btn btn-outline-success" id="generateReportBtn" style="display: none;">
                                        <i class="fas fa-file-pdf me-2"></i> Generate Risk Report
                                    </button>
                                    <button class="btn btn-outline-info" id="filterBarangaysBtn">
                                        <i class="fas fa-filter me-2"></i> Filter Barangays
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- Leaflet.heat for household density heatmap (Phase 5) -->
    <script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
    <script>
        // Map variables
        let map;
        let currentView = 'risk';
        let barangayMarkers = [];
        let hazardLayers = [];
        let populationLayers = [];
        let searchControl;
        let measureControl;
        let isMeasuring = false;
        let measurePoints = [];

        // Phase 5 layer groups
        let boundaryLayer, householdLayer, heatmapLayer, hazardPolygonLayer, evacLayer;
        let layerControl;

        // Initialize the map
        function initMap() {
            // Coordinates for Sablayan, Occidental Mindoro
            const sablayanCoords = [12.8333, 120.7667];

            // ── Phase 5: Base tile layers with switcher ──
            const osmTile = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            });
            const satelliteTile = L.tileLayer(
                'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Tiles &copy; Esri', maxZoom: 19
            });
            const terrainTile = L.tileLayer(
                'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenTopoMap contributors', maxZoom: 17
            });

            map = L.map('map', { layers: [osmTile] }).setView(sablayanCoords, 11);

            const baseLayers = {
                'Street (OpenStreetMap)': osmTile,
                'Satellite': satelliteTile,
                'Terrain': terrainTile
            };

            // Load data from PHP
            const barangays = <?php echo json_encode($barangays); ?>;
            const hazardZones = <?php echo json_encode($hazardZones); ?>;
            const householdsGPS = <?php echo json_encode($households_gps); ?>;
            const evacCenters = <?php echo json_encode($evacCenters); ?>;

            // ── Layer 2: Barangay boundary layer (always visible) ──
            boundaryLayer = L.layerGroup();
            barangays.forEach(function(b) {
                if (b.boundary_geojson && b.boundary_geojson.trim()) {
                    try {
                        const gj = JSON.parse(b.boundary_geojson);
                        const hazardTypes = [];
                        hazardZones.forEach(hz => { if (hz.barangay_id == b.id) hazardTypes.push(hz.hazard_name); });
                        L.geoJSON(gj, {
                            style: { color: '#555', weight: 1.5, fillOpacity: 0.05 }
                        }).bindPopup(
                            '<strong>' + b.name + '</strong><br>' +
                            'Population: ' + (b.display_population || 'N/A') + '<br>' +
                            'Households: ' + (b.household_count || 0) + '<br>' +
                            (hazardTypes.length ? 'Hazards: ' + [...new Set(hazardTypes)].join(', ') : 'No hazard zones')
                        ).addTo(boundaryLayer);
                    } catch(e) {}
                } else {
                    // No boundary — show grayed center dot
                    let lat, lng;
                    if (b.coordinates) {
                        const p = b.coordinates.split(',');
                        lat = parseFloat(p[0]); lng = parseFloat(p[1]);
                    }
                    if (lat && lng) {
                        L.circleMarker([lat, lng], {
                            radius: 6, color: '#aaa', fillColor: '#ccc', fillOpacity: 0.5, weight: 1
                        }).bindPopup('<strong>' + b.name + '</strong><br><em class="text-muted">No boundary drawn yet</em>')
                          .addTo(boundaryLayer);
                    }
                }
            });
            boundaryLayer.addTo(map);

            // ── Layer 3: Household dots (off by default) ──
            householdLayer = L.layerGroup();
            householdsGPS.forEach(function(hh) {
                const isVulnerable = hh.pwd_count > 0 || hh.senior_count > 0
                                  || hh.infant_count > 0 || hh.pregnant_count > 0;
                const color = isVulnerable ? '#dc3545' : '#0d6efd';
                L.circleMarker([hh.latitude, hh.longitude], {
                    radius: 4, color: color, fillColor: color, fillOpacity: 0.8, weight: 1
                }).bindPopup(
                    '<strong>' + hh.household_head + '</strong><br>' +
                    'Barangay: ' + hh.barangay_name + '<br>' +
                    'Family Members: ' + hh.family_members + '<br>' +
                    (hh.pwd_count > 0 ? 'PWD: ' + hh.pwd_count + '<br>' : '') +
                    (hh.senior_count > 0 ? 'Seniors: ' + hh.senior_count + '<br>' : '') +
                    (hh.infant_count > 0 ? 'Infants: ' + hh.infant_count + '<br>' : '') +
                    (hh.pregnant_count > 0 ? 'Pregnant: ' + hh.pregnant_count + '<br>' : '')
                ).addTo(householdLayer);
            });
            // Not added to map by default — user toggles

            // ── Layer 4: Household density heatmap (off by default) ──
            const heatData = householdsGPS.map(function(hh) {
                return [parseFloat(hh.latitude), parseFloat(hh.longitude), hh.family_members || 1];
            });
            if (typeof L.heatLayer !== 'undefined') {
                heatmapLayer = L.heatLayer(heatData, { radius: 20, blur: 15, maxZoom: 16 });
            } else {
                heatmapLayer = L.layerGroup(); // fallback
            }

            // ── Layer 5: Hazard polygon layer (on by default) ──
            // Already handled by existing initRiskView / initHazardsView — add polygon overlays here
            hazardPolygonLayer = L.layerGroup();
            const riskColors = {
                'High Susceptible': '#dc3545',
                'Moderate Susceptible': '#fd7e14',
                'Low Susceptible': '#ffc107',
                'Prone': '#6f42c1',
                'General Inundation': '#6f42c1',
                'high': '#dc3545',
                'medium': '#fd7e14',
                'low': '#ffc107'
            };
            hazardZones.forEach(function(hz) {
                if (hz.polygon_geojson && hz.polygon_geojson.trim()) {
                    try {
                        const gj = JSON.parse(hz.polygon_geojson);
                        const col = riskColors[hz.risk_level] || '#888';
                        L.geoJSON(gj, {
                            style: { color: col, weight: 2, fillOpacity: 0.3 }
                        }).bindPopup(
                            '<strong>' + hz.hazard_name + '</strong><br>' +
                            'Barangay: ' + hz.barangay_name + '<br>' +
                            'Risk: ' + hz.risk_level + '<br>' +
                            'Affected: ' + (hz.affected_population || 0).toLocaleString() + ' persons'
                        ).addTo(hazardPolygonLayer);
                    } catch(e) {}
                }
            });
            hazardPolygonLayer.addTo(map);

            // ── Layer 7: Evacuation centers ──
            const shelterIcon = L.divIcon({
                html: '<i class="fas fa-house-medical" style="color:#198754;font-size:18px;"></i>',
                className: '',
                iconSize: [22, 22], iconAnchor: [11, 11], popupAnchor: [0, -12]
            });
            evacLayer = L.layerGroup();
            evacCenters.forEach(function(ec) {
                L.marker([ec.latitude, ec.longitude], {icon: shelterIcon})
                    .bindPopup(
                        '<strong>' + ec.name + '</strong><br>' +
                        'Barangay: ' + (ec.barangay_name || 'N/A') + '<br>' +
                        'Capacity: ' + (ec.capacity || 'N/A') + '<br>' +
                        'Occupancy: ' + (ec.current_occupancy || 0) + '<br>' +
                        'Status: ' + (ec.status || 'N/A')
                    ).addTo(evacLayer);
            });
            evacLayer.addTo(map);

            // ── Phase 5 Layer Control (top right) ──
            // ── Layer 6: Incident polygons (ongoing/monitoring — on by default) ──
            const incidentData = <?php echo json_encode($incidentPolygons); ?>;
            const incidentLayer = L.layerGroup();
            incidentData.forEach(function(inc) {
                if (!inc.polygon_geojson) return;
                try {
                    const gj = JSON.parse(inc.polygon_geojson);
                    L.geoJSON(gj, {
                        style: { color: '#dc3545', weight: 3, fillOpacity: 0.25, dashArray: '6,4' }
                    }).bindPopup(
                        '<strong class="text-danger">' + inc.title + '</strong><br>' +
                        'Type: ' + (inc.hazard_type_name || 'N/A') + '<br>' +
                        'Date: ' + inc.incident_date + '<br>' +
                        'Status: <span class="badge bg-danger">' + inc.status + '</span><br>' +
                        'Affected: ' + parseInt(inc.total_affected_population).toLocaleString() + ' persons<br>' +
                        '<a href="incident_reports.php?view_id=' + inc.id + '">View Details</a>'
                    ).addTo(incidentLayer);
                } catch(e) {}
            });
            incidentLayer.addTo(map);

            const overlays = {
                'Barangay Boundaries': boundaryLayer,
                'Household Locations': householdLayer,
                'Population Heatmap': heatmapLayer,
                'Hazard Zone Polygons': hazardPolygonLayer,
                'Active Incidents': incidentLayer,
                'Evacuation Centers': evacLayer
            };
            layerControl = L.control.layers(baseLayers, overlays, { position: 'topright', collapsed: false }).addTo(map);

            // Initialize existing views
            initRiskView(barangays, hazardZones);
            initPopulationView(barangays);
            initHazardsView(hazardZones);

            // Show risk view by default
            showView('risk');
        }

        // Initialize Risk View
        function initRiskView(barangays, hazardZones) {
            barangayMarkers = [];
            
            barangays.forEach(barangay => {
                // Use actual coordinates from database
                let lat, lng;
                if (barangay.coordinates) {
                    const coords = barangay.coordinates.split(',');
                    lat = parseFloat(coords[0].trim());
                    lng = parseFloat(coords[1].trim());
                } else {
                    // Fallback to approximate coordinates if none provided
                    lat = 12.8333 + (Math.random() - 0.5) * 0.1;
                    lng = 120.7667 + (Math.random() - 0.5) * 0.1;
                }
                
                // Determine risk level and color
                let riskLevel = 'none';
                let color = '#95a5a6'; // gray for no data
                
                if (barangay.has_high_risk) {
                    riskLevel = 'high';
                    color = '#e74c3c';
                } else if (barangay.hazard_count > 0) {
                    riskLevel = 'medium';
                    color = '#f39c12';
                } else if (barangay.total_affected > 0) {
                    riskLevel = 'low';
                    color = '#27ae60';
                }
                
                // Create marker
                const marker = L.circleMarker([lat, lng], {
                    radius: 12 + (barangay.total_affected / 200),
                    fillColor: color,
                    color: '#2c3e50',
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.7
                });
                
                // Add popup with barangay information
                const populationSource = barangay.actual_population_data ? 'Updated Data' : 'Base Data';
                marker.bindPopup(`
                    <div class="text-center">
                        <h6><strong>${barangay.name}</strong></h6>
                        <hr class="my-2">
                        <p class="mb-1"><strong>Population:</strong> ${barangay.display_population?.toLocaleString() || 'N/A'}</p>
                        <p class="mb-1"><small class="text-muted">Source: ${populationSource}</small></p>
                        <p class="mb-1"><strong>Hazard Zones:</strong> ${barangay.hazard_count}</p>
                        <p class="mb-1"><strong>Affected Population:</strong> ${barangay.total_affected.toLocaleString()}</p>
                        <p class="mb-0"><strong>Risk Level:</strong> <span class="risk-${riskLevel}">${riskLevel.charAt(0).toUpperCase() + riskLevel.slice(1)}</span></p>
                    </div>
                `);
                
                marker.bindTooltip(`${barangay.name} - ${riskLevel.toUpperCase()} RISK`);
                
                barangayMarkers.push(marker);
            });
        }

        // Initialize Population View
        function initPopulationView(barangays) {
            populationLayers = [];
            
            barangays.forEach(barangay => {
                // Use actual coordinates from database
                let lat, lng;
                let hasValidCoordinates = false;
                
                if (barangay.coordinates) {
                    try {
                        const coords = barangay.coordinates.split(',');
                        lat = parseFloat(coords[0].trim());
                        lng = parseFloat(coords[1].trim());
                        
                        // Validate coordinates
                        if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
                            hasValidCoordinates = true;
                        }
                    } catch (error) {
                        console.error('Error parsing coordinates for barangay:', barangay.name, error);
                    }
                }
                
                if (!hasValidCoordinates) {
                    // Fallback to Sablayan center coordinates
                    lat = 12.8333;
                    lng = 120.7667;
                    console.warn(`Using default coordinates for barangay: ${barangay.name}`);
                }
                
                // Size based on population (with fallback)
                const population = barangay.display_population || 0;
                const radius = Math.max(8, Math.min(30, population / 1000));
                const populationSource = barangay.actual_population_data ? 'Updated Data' : 'Base Data';
                
                const circle = L.circleMarker([lat, lng], {
                    radius: radius,
                    fillColor: '#3498db',
                    color: '#2980b9',
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.5
                });
                
                circle.bindPopup(`
                    <div class="text-center">
                        <h6><strong>${barangay.name}</strong></h6>
                        <hr class="my-2">
                        <p class="mb-1"><strong>Total Population:</strong> ${population.toLocaleString()}</p>
                        <p class="mb-1"><small class="text-muted">Source: ${populationSource}</small></p>
                        <p class="mb-1"><strong>At Risk:</strong> ${barangay.total_affected.toLocaleString()}</p>
                        <p class="mb-0"><strong>Risk Percentage:</strong> ${population ? Math.round((barangay.total_affected / population) * 100) : 0}%</p>
                    </div>
                `);
                
                circle.bindTooltip(`${barangay.name} - Pop: ${population.toLocaleString()} (${populationSource})`);
                
                populationLayers.push(circle);
            });
        }

function initHazardsView(hazardZones) {
    hazardLayers = [];
    
    hazardZones.forEach(hazard => {
        // Use actual coordinates from database
        let lat, lng;
        if (hazard.coordinates) {
            const coords = hazard.coordinates.split(',');
            lat = parseFloat(coords[0].trim());
            lng = parseFloat(coords[1].trim());
        } else {
            // Fallback to barangay coordinates if hazard coordinates not available
            const barangay = <?php echo json_encode($barangays); ?>.find(b => b.name === hazard.barangay_name);
            if (barangay && barangay.coordinates) {
                const barangayCoords = barangay.coordinates.split(',');
                lat = parseFloat(barangayCoords[0].trim());
                lng = parseFloat(barangayCoords[1].trim());
            } else {
                // Final fallback to random coordinates
                lat = 12.8333 + (Math.random() - 0.5) * 0.1;
                lng = 120.7667 + (Math.random() - 0.5) * 0.1;
            }
        }
        
        // Create custom icon based on hazard type
        const hazardIcon = createHazardIcon(hazard.hazard_name, hazard.risk_level);
        
        // Add hazard area circle with semi-transparent fill
        const hazardArea = L.circle([lat, lng], {
            color: hazard.color,
            fillColor: hazard.color,
            fillOpacity: 0.2,
            radius: hazard.area_km2 * 100,
            weight: 2
        });
        
        // Add marker with hazard icon at the center
        const hazardMarker = L.marker([lat, lng], { icon: hazardIcon });
        
        // Bind popup with hazard information
        hazardMarker.bindPopup(`
            <div class="text-center" style="min-width: 200px;">
                <div style="display: flex; align-items: center; justify-content: center; margin-bottom: 10px;">
                    <div style="
                        background: ${hazard.color}; 
                        width: 40px; 
                        height: 40px; 
                        border-radius: 50%; 
                        display: flex; 
                        align-items: center; 
                        justify-content: center;
                        margin-right: 10px;
                        color: white;
                    ">
                        <i class="fas ${hazard.icon || 'fa-exclamation-triangle'}"></i>
                    </div>
                    <div>
                        <h6 style="margin: 0;"><strong>${hazard.hazard_name}</strong></h6>
                    <div class="badge ${
                        hazard.risk_level === 'High Susceptible' || hazard.risk_level === 'high' || hazard.risk_level === 'Prone' ? 'bg-danger' : 
                        hazard.risk_level === 'Moderate Susceptible' || hazard.risk_level === 'medium' || hazard.risk_level === 'General Inundation' ? 'bg-warning text-dark' : 
                        hazard.risk_level === 'Low Susceptible' || hazard.risk_level === 'low' ? 'bg-success' : 
                        hazard.risk_level === 'Generally Susceptible' ? 'bg-info' : 
                        hazard.risk_level.includes('PEIS') ? 'bg-purple' : 
                        'bg-secondary'
                    }">
                        ${hazard.risk_level}
                    </div>
                    </div>
                </div>
                <hr style="margin: 10px 0;">
                <div style="text-align: left;">
                    <p style="margin: 5px 0;"><strong>Barangay:</strong> ${hazard.barangay_name}</p>
                    <p style="margin: 5px 0;"><strong>Area:</strong> ${hazard.area_km2} km²</p>
                    <p style="margin: 5px 0;"><strong>Affected Population:</strong> ${hazard.affected_population.toLocaleString()}</p>
                    <p style="margin: 5px 0;"><strong>Total Population:</strong> ${hazard.barangay_population?.toLocaleString() || 'N/A'}</p>
                    ${hazard.description ? `<p style="margin: 5px 0;"><strong>Description:</strong> ${hazard.description}</p>` : ''}
                </div>
            </div>
        `);
        
        // Bind tooltip
        hazardMarker.bindTooltip(`
            <div style="text-align: center;">
                <strong>${hazard.hazard_name}</strong><br>
                ${hazard.barangay_name}<br>
                <span style="color: ${hazard.color}">●</span> ${hazard.risk_level.toUpperCase()} RISK
            </div>
        `);
        
        // Create a layer group for the hazard area and marker
        const hazardLayer = L.layerGroup([hazardArea, hazardMarker]);
        hazardLayers.push(hazardLayer);
    });
}

function toggleHazardIcons(show) {
    hazardLayers.forEach(layer => {
        if (show) {
            if (currentView === 'hazards') {
                layer.addTo(map);
            }
        } else {
            map.removeLayer(layer);
        }
    });
}


        // Show specific view
function showView(view) {
    currentView = view;
    
    // Clear all layers first
    barangayMarkers.forEach(marker => map.removeLayer(marker));
    populationLayers.forEach(layer => map.removeLayer(layer));
    hazardLayers.forEach(layer => map.removeLayer(layer));
    
    // Add layers for current view
    switch(view) {
        case 'risk':
            barangayMarkers.forEach(marker => marker.addTo(map));
            break;
        case 'population':
            populationLayers.forEach(layer => layer.addTo(map));
            break;
        case 'hazards':
            hazardLayers.forEach(layer => layer.addTo(map));
            break;
    }
    
    // Update button states
    document.getElementById('riskViewBtn').classList.toggle('active', view === 'risk');
    document.getElementById('populationViewBtn').classList.toggle('active', view === 'population');
    document.getElementById('hazardsViewBtn').classList.toggle('active', view === 'hazards');
}


        // SEARCH LOCATION FUNCTIONALITY
        function initSearchLocation() {
            // Remove existing search control if any
            if (searchControl) {
                map.removeControl(searchControl);
            }
            
            // Create custom search control using OpenStreetMap Nominatim
            searchControl = L.Control.extend({
                options: {
                    position: 'topleft',
                    placeholder: 'Search location...',
                    collapsed: true
                },
                
                onAdd: function(map) {
                    const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-control-custom');
                    container.style.backgroundColor = 'white';
                    container.style.padding = '5px';
                    container.style.borderRadius = '4px';
                    container.style.boxShadow = '0 1px 5px rgba(0,0,0,0.4)';
                    
                    const searchForm = `
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" id="searchInput" placeholder="Search location..." style="width: 200px;">
                            <button class="btn btn-primary" id="searchButton" type="button" style="font-size: 12px;">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                        <div id="searchResults" style="display: none; max-height: 200px; overflow-y: auto; margin-top: 5px;"></div>
                    `;
                    
                    container.innerHTML = searchForm;
                    
                    // Prevent map events when interacting with search
                    L.DomEvent.disableClickPropagation(container);
                    L.DomEvent.disableScrollPropagation(container);
                    
                    // Add event listeners
                    const searchInput = container.querySelector('#searchInput');
                    const searchButton = container.querySelector('#searchButton');
                    const searchResults = container.querySelector('#searchResults');
                    
                    const performSearch = () => {
                        const query = searchInput.value.trim();
                        if (query.length < 3) {
                            alert('Please enter at least 3 characters to search.');
                            return;
                        }
                        
                        searchResults.innerHTML = '<div class="text-center p-2"><i class="fas fa-spinner fa-spin"></i> Searching...</div>';
                        searchResults.style.display = 'block';
                        
                        // Use OpenStreetMap Nominatim API
                        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5`)
                            .then(response => response.json())
                            .then(data => {
                                searchResults.innerHTML = '';
                                
                                if (data.length === 0) {
                                    searchResults.innerHTML = '<div class="text-center p-2 text-muted">No results found</div>';
                                    return;
                                }
                                
                                data.forEach(result => {
                                    const resultItem = document.createElement('div');
                                    resultItem.className = 'search-result-item p-2 border-bottom';
                                    resultItem.style.cursor = 'pointer';
                                    resultItem.innerHTML = `
                                        <strong>${result.display_name}</strong>
                                        <br><small class="text-muted">${result.type} • Lat: ${result.lat}, Lon: ${result.lon}</small>
                                    `;
                                    
                                    resultItem.addEventListener('click', () => {
                                        const lat = parseFloat(result.lat);
                                        const lon = parseFloat(result.lon);
                                        
                                        // Pan and zoom to location
                                        map.setView([lat, lon], 15);
                                        
                                        // Add marker
                                        L.marker([lat, lon])
                                            .addTo(map)
                                            .bindPopup(`<strong>${result.display_name}</strong>`)
                                            .openPopup();
                                        
                                        // Hide results
                                        searchResults.style.display = 'none';
                                        searchInput.value = '';
                                    });
                                    
                                    searchResults.appendChild(resultItem);
                                });
                            })
                            .catch(error => {
                                console.error('Search error:', error);
                                searchResults.innerHTML = '<div class="text-center p-2 text-danger">Search failed</div>';
                            });
                    };
                    
                    searchButton.addEventListener('click', performSearch);
                    searchInput.addEventListener('keypress', (e) => {
                        if (e.key === 'Enter') {
                            performSearch();
                        }
                    });
                    
                    // Close results when clicking outside
                    map.on('click', () => {
                        searchResults.style.display = 'none';
                    });
                    
                    return container;
                }
            });
            
            searchControl = new searchControl();
            map.addControl(searchControl);
            
            // Hide initially
            setTimeout(() => {
                const searchContainer = document.querySelector('.leaflet-control-custom');
                if (searchContainer) {
                    searchContainer.style.display = 'none';
                }
            }, 100);
        }

        function toggleSearchLocation() {
            const searchContainer = document.querySelector('.leaflet-control-custom');
            if (searchContainer) {
                const isVisible = searchContainer.style.display !== 'none';
                searchContainer.style.display = isVisible ? 'none' : 'block';
                
                if (!isVisible) {
                    const searchInput = document.querySelector('#searchInput');
                    if (searchInput) {
                        searchInput.focus();
                    }
                }
            }
        }

// MEASURE DISTANCE FUNCTIONALITY - FIXED DISPLAY ISSUE
function initMeasureDistance() {
    // Remove existing measure control if any
    if (window.measureControl) {
        map.removeControl(window.measureControl);
    }
    
    let measureLayer = L.layerGroup().addTo(map);
    let points = [];
    let measureLine = null;
    let totalDistance = 0;
    
    // Create custom measure control
    const MeasureControl = L.Control.extend({
        options: {
            position: 'topright'
        },
        
        onAdd: function(map) {
            const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-control-measure');
            container.style.backgroundColor = 'white';
            container.style.padding = '15px';
            container.style.borderRadius = '8px';
            container.style.boxShadow = '0 2px 10px rgba(0,0,0,0.3)';
            container.style.display = 'none';
            container.style.zIndex = '1000';
            container.style.width = '300px'; // Fixed width
            container.style.minHeight = '200px'; // Minimum height
            
            container.innerHTML = `
                <div class="measure-control">
                    <h5 class="mb-3 text-center" style="color: #2c3e50;">
                        <i class="fas fa-ruler-combined me-2"></i>Measure Distance
                    </h5>
                    
                    <div class="mb-3 text-center">
                        <button class="btn btn-success btn-sm me-1" id="startMeasure">
                            <i class="fas fa-play me-1"></i>Start
                        </button>
                        <button class="btn btn-warning btn-sm me-1" id="clearMeasure">
                            <i class="fas fa-undo me-1"></i>Clear
                        </button>
                        <button class="btn btn-secondary btn-sm" id="closeMeasure">
                            <i class="fas fa-times me-1"></i>Close
                        </button>
                    </div>
                    
                    <div id="measureInfo" class="p-3 mb-3 rounded" style="background: #f8f9fa; border: 1px solid #dee2e6;">
                        <div class="text-center text-muted mb-2">
                            <i class="fas fa-mouse-pointer me-1"></i>Click on map to add points
                        </div>
                        <div id="distanceResult" class="text-center" style="min-height: 60px; display: flex; align-items: center; justify-content: center;">
                            <!-- Distance will appear here -->
                        </div>
                    </div>
                    
                    <div class="text-center text-muted small">
                        <i class="fas fa-lightbulb me-1"></i>Double-click to finish measurement
                    </div>
                </div>
            `;
            
            L.DomEvent.disableClickPropagation(container);
            L.DomEvent.disableScrollPropagation(container);
            
            return container;
        }
    });
    
    window.measureControl = new MeasureControl();
    map.addControl(window.measureControl);
    
    const measureContainer = document.querySelector('.leaflet-control-measure');
    const startBtn = document.querySelector('#startMeasure');
    const clearBtn = document.querySelector('#clearMeasure');
    const closeBtn = document.querySelector('#closeMeasure');
    const measureInfo = document.querySelector('#measureInfo');
    const distanceResult = document.querySelector('#distanceResult');
    
    function startMeasurement() {
        isMeasuring = true;
        points = [];
        totalDistance = 0;
        measureLayer.clearLayers();
        measureLine = null;
        
        // Reset display
        const infoText = measureInfo.querySelector('.text-muted');
        if (infoText) {
            infoText.innerHTML = '<i class="fas fa-mouse-pointer me-1"></i>Click on map to add measurement points';
        }
        
        distanceResult.innerHTML = '';
        distanceResult.style.padding = '10px';
        
        // Change cursor
        map.getContainer().style.cursor = 'crosshair';
        
        // Add click handler for measurement
        map.on('click', addMeasurePoint);
        map.on('dblclick', finishMeasurement);
        map.on('mousemove', updateMeasureLine);
    }
    
    function addMeasurePoint(e) {
        if (!isMeasuring) return;
        
        points.push(e.latlng);
        
        // Add marker
        L.marker(e.latlng, {
            icon: L.divIcon({
                className: 'measure-point',
                html: '<div style="background-color: #e74c3c; width: 10px; height: 10px; border-radius: 50%; border: 2px solid white; box-shadow: 0 0 5px rgba(0,0,0,0.7);"></div>',
                iconSize: [14, 14]
            })
        }).addTo(measureLayer);
        
        // Draw line
        if (points.length > 1) {
            if (measureLine) {
                map.removeLayer(measureLine);
            }
            
            measureLine = L.polyline(points, {
                color: '#e74c3c',
                weight: 4,
                opacity: 0.8
            }).addTo(measureLayer);
            
            // Calculate distance
            totalDistance = 0;
            for (let i = 1; i < points.length; i++) {
                totalDistance += points[i-1].distanceTo(points[i]);
            }
            
            // Display distance - CLEAR AND VISIBLE
            const distanceKm = (totalDistance / 1000).toFixed(2);
            const distanceMiles = (totalDistance / 1609.34).toFixed(2);
            
            distanceResult.innerHTML = `
                <div style="text-align: center;">
                    <div style="font-size: 12px; color: #666; margin-bottom: 5px;">📏 CURRENT DISTANCE:</div>
                    <div style="font-size: 18px; font-weight: bold; color: #e74c3c; margin-bottom: 5px;">
                        ${distanceKm} km
                    </div>
                    <div style="font-size: 14px; color: #7f8c8d;">
                        ${distanceMiles} miles
                    </div>
                </div>
            `;
        } else {
            // First point placed
            distanceResult.innerHTML = `
                <div style="text-align: center; color: #27ae60;">
                    <i class="fas fa-check-circle me-1"></i>
                    First point placed. Click to add more points.
                </div>
            `;
        }
    }
    
    function updateMeasureLine(e) {
        if (!isMeasuring || points.length === 0) return;
        
        // Update temporary line
        const tempPoints = [...points, e.latlng];
        
        if (window.tempMeasureLine) {
            map.removeLayer(window.tempMeasureLine);
        }
        
        window.tempMeasureLine = L.polyline(tempPoints, {
            color: '#3498db',
            weight: 2,
            dashArray: '5, 5',
            opacity: 0.6
        }).addTo(measureLayer);
    }
    
    function finishMeasurement() {
        if (!isMeasuring) return;
        
        isMeasuring = false;
        map.getContainer().style.cursor = '';
        
        map.off('click', addMeasurePoint);
        map.off('dblclick', finishMeasurement);
        map.off('mousemove', updateMeasureLine);
        
        if (window.tempMeasureLine) {
            map.removeLayer(window.tempMeasureLine);
            window.tempMeasureLine = null;
        }
        
        if (points.length > 1) {
            const infoText = measureInfo.querySelector('.text-muted');
            if (infoText) {
                infoText.innerHTML = '<i class="fas fa-check-circle me-1" style="color: #27ae60;"></i>Measurement complete!';
            }
            
            // Show final distance prominently
            const distanceKm = (totalDistance / 1000).toFixed(2);
            const distanceMiles = (totalDistance / 1609.34).toFixed(2);
            
            distanceResult.innerHTML = `
                <div style="text-align: center; background: #e8f5e8; padding: 15px; border-radius: 8px; border: 2px solid #27ae60;">
                    <div style="font-size: 14px; color: #2c3e50; margin-bottom: 8px;">
                        🎯 FINAL MEASUREMENT RESULT
                    </div>
                    <div style="font-size: 24px; font-weight: bold; color: #27ae60; margin-bottom: 5px;">
                        ${distanceKm} km
                    </div>
                    <div style="font-size: 16px; color: #7f8c8d;">
                        ${distanceMiles} miles
                    </div>
                    <div style="font-size: 11px; color: #95a5a6; margin-top: 8px;">
                        ${points.length} points measured
                    </div>
                </div>
            `;
        } else {
            distanceResult.innerHTML = `
                <div style="text-align: center; color: #e74c3c;">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    Need at least 2 points to measure distance
                </div>
            `;
        }
    }
    
    function clearMeasurement() {
        isMeasuring = false;
        points = [];
        totalDistance = 0;
        measureLayer.clearLayers();
        
        if (measureLine) {
            map.removeLayer(measureLine);
            measureLine = null;
        }
        
        if (window.tempMeasureLine) {
            map.removeLayer(window.tempMeasureLine);
            window.tempMeasureLine = null;
        }
        
        map.getContainer().style.cursor = '';
        
        // Reset display
        const infoText = measureInfo.querySelector('.text-muted');
        if (infoText) {
            infoText.innerHTML = '<i class="fas fa-mouse-pointer me-1"></i>Click on map to start measuring';
        }
        
        distanceResult.innerHTML = '';
        distanceResult.style.padding = '0';
        
        map.off('click', addMeasurePoint);
        map.off('dblclick', finishMeasurement);
        map.off('mousemove', updateMeasureLine);
    }
    
    function closeMeasurement() {
        clearMeasurement();
        measureContainer.style.display = 'none';
    }
    
    startBtn.addEventListener('click', startMeasurement);
    clearBtn.addEventListener('click', clearMeasurement);
    closeBtn.addEventListener('click', closeMeasurement);
    
    return {
        show: function() {
            measureContainer.style.display = 'block';
        },
        hide: function() {
            measureContainer.style.display = 'none';
            clearMeasurement();
        }
    };
}

        function toggleMeasureDistance() {
            const measureContainer = document.querySelector('.leaflet-control-measure');
            if (!window.measureTool) {
                window.measureTool = initMeasureDistance();
            }
            
            if (measureContainer.style.display === 'none') {
                window.measureTool.show();
            } else {
                window.measureTool.hide();
            }
        }

        // PRINT MAP FUNCTIONALITY
        function printMap() {
            // Create a print-friendly version of the map
            const printWindow = window.open('', '_blank');
            const mapBounds = map.getBounds();
            const mapCenter = map.getCenter();
            const mapZoom = map.getZoom();
            const barangays = <?php echo json_encode($barangays); ?>;
            const hazardZones = <?php echo json_encode($hazardZones); ?>;
            const hazardTypes = <?php echo json_encode($hazardTypes); ?>;
            
            const printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Sablayan Risk Assessment Map - Printed</title>
                    <style>
                        body { 
                            font-family: Arial, sans-serif; 
                            margin: 20px;
                            color: #333;
                        }
                        .map-header { 
                            text-align: center; 
                            margin-bottom: 20px;
                            border-bottom: 2px solid #2c3e50;
                            padding-bottom: 10px;
                        }
                        .map-info {
                            margin: 20px 0;
                            padding: 15px;
                            background: #f8f9fa;
                            border-radius: 5px;
                        }
                        .legend { 
                            display: flex; 
                            flex-wrap: wrap;
                            gap: 15px;
                            margin: 20px 0;
                        }
                        .legend-item { 
                            display: flex; 
                            align-items: center;
                            margin-right: 15px;
                        }
                        .legend-color { 
                            width: 20px; 
                            height: 20px; 
                            margin-right: 8px;
                            border: 1px solid #666;
                        }
                        .print-date { 
                            text-align: right; 
                            color: #666;
                            font-size: 0.9em;
                        }
                        @media print {
                            body { margin: 0; }
                            .no-print { display: none; }
                        }
                    </style>
                </head>
                <body>
                    <div class="map-header">
                        <h1>Sablayan Risk Assessment Map</h1>
                        <h3>Interactive Risk Mapping System</h3>
                        <div class="print-date">Printed on: ${new Date().toLocaleString()}</div>
                    </div>
                    
                    <div class="map-info">
                        <div><strong>Current View:</strong> ${currentView.charAt(0).toUpperCase() + currentView.slice(1)} View</div>
                        <div><strong>Map Center:</strong> Lat ${mapCenter.lat.toFixed(4)}, Lng ${mapCenter.lng.toFixed(4)}</div>
                        <div><strong>Zoom Level:</strong> ${mapZoom}</div>
                        <div><strong>Barangays:</strong> ${barangays.length}</div>
                        <div><strong>Hazard Zones:</strong> ${hazardZones.length}</div>
                    </div>
                    
                    <div class="legend">
                        <div>
                            <h4>Risk Levels</h4>
                            <div class="legend-item"><div class="legend-color" style="background-color: #e74c3c;"></div>High Risk</div>
                            <div class="legend-item"><div class="legend-color" style="background-color: #f39c12;"></div>Medium Risk</div>
                            <div class="legend-item"><div class="legend-color" style="background-color: #27ae60;"></div>Low Risk</div>
                        </div>
                        <div>
                            <h4>Hazard Types</h4>
                            ${hazardTypes.map(type => 
                                `<div class="legend-item"><div class="legend-color" style="background-color: ${type.color};"></div>${type.name}</div>`
                            ).join('')}
                        </div>
                    </div>
                    
                    <div style="text-align: center; margin: 20px 0;">
                        <em>Map screenshot would be embedded here in a real implementation</em>
                        <br>
                        <small class="text-muted">(Actual map capture requires server-side rendering)</small>
                    </div>
                    
                    <div class="no-print" style="margin-top: 30px; text-align: center;">
                        <button onclick="window.print()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
                            Print This Report
                        </button>
                        <button onclick="window.close()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">
                            Close Window
                        </button>
                    </div>
                </body>
                </html>
            `;
            
            printWindow.document.write(printContent);
            printWindow.document.close();
        }

        // EXPORT DATA FUNCTIONALITY
        function exportData() {
            const barangays = <?php echo json_encode($barangays); ?>;
            const hazardZones = <?php echo json_encode($hazardZones); ?>;
            const hazardTypes = <?php echo json_encode($hazardTypes); ?>;
            
            // Create export options modal
            const exportModal = document.createElement('div');
            exportModal.className = 'modal fade';
            exportModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fas fa-download me-2"></i>Export Map Data</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Export Format:</label>
                                <select class="form-select" id="exportFormat">
                                    <option value="json">JSON Data</option>
                                    <option value="csv">CSV Spreadsheet</option>
                                    <option value="kml">KML (Google Earth)</option>
                                    <option value="geojson">GeoJSON</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Data to Export:</label>
                                <select class="form-select" id="exportDataType">
                                    <option value="current">Current View Data</option>
                                    <option value="barangays">All Barangays</option>
                                    <option value="hazards">All Hazard Zones</option>
                                    <option value="all">Complete Dataset</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">File Name:</label>
                                <input type="text" class="form-control" id="exportFileName" value="sablayan_risk_data">
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="includeMetadata" checked>
                                <label class="form-check-label" for="includeMetadata">
                                    Include metadata and timestamp
                                </label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="confirmExport">Export Data</button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(exportModal);
            
            // Initialize Bootstrap modal
            const modal = new bootstrap.Modal(exportModal);
            modal.show();
            
            // Handle export confirmation
            document.getElementById('confirmExport').addEventListener('click', function() {
                const format = document.getElementById('exportFormat').value;
                const dataType = document.getElementById('exportDataType').value;
                const fileName = document.getElementById('exportFileName').value;
                const includeMetadata = document.getElementById('includeMetadata').checked;
                
                performExport(format, dataType, fileName, includeMetadata, barangays, hazardZones, hazardTypes);
                modal.hide();
                
                // Clean up
                setTimeout(() => {
                    document.body.removeChild(exportModal);
                }, 500);
            });
            
            // Clean up on modal hide
            exportModal.addEventListener('hidden.bs.modal', function() {
                document.body.removeChild(exportModal);
            });
        }

        function performExport(format, dataType, fileName, includeMetadata, barangays, hazardZones, hazardTypes) {
            let data;
            let mimeType;
            let fileExtension;
            
            // Prepare data based on selection
            switch(dataType) {
                case 'current':
                    data = prepareCurrentViewData(barangays, hazardZones);
                    break;
                case 'barangays':
                    data = barangays;
                    break;
                case 'hazards':
                    data = hazardZones;
                    break;
                case 'all':
                    data = {
                        barangays: barangays,
                        hazardZones: hazardZones,
                        hazardTypes: hazardTypes
                    };
                    break;
            }
            
            // Add metadata if requested
            if (includeMetadata) {
                const metadata = {
                    exported: new Date().toISOString(),
                    view: currentView,
                    mapCenter: map.getCenter(),
                    zoom: map.getZoom(),
                    totalBarangays: barangays.length,
                    totalHazards: hazardZones.length
                };
                
                if (typeof data === 'object' && !Array.isArray(data)) {
                    data.metadata = metadata;
                } else {
                    data = {
                        data: data,
                        metadata: metadata
                    };
                }
            }
            
            // Convert to requested format
            let content;
            switch(format) {
                case 'json':
                    content = JSON.stringify(data, null, 2);
                    mimeType = 'application/json';
                    fileExtension = 'json';
                    break;
                    
                case 'csv':
                    content = convertToCSV(data);
                    mimeType = 'text/csv';
                    fileExtension = 'csv';
                    break;
                    
                case 'kml':
                    content = convertToKML(data);
                    mimeType = 'application/vnd.google-earth.kml+xml';
                    fileExtension = 'kml';
                    break;
                    
                case 'geojson':
                    content = convertToGeoJSON(data);
                    mimeType = 'application/geo+json';
                    fileExtension = 'geojson';
                    break;
            }
            
            // Download file
            downloadFile(content, `${fileName}.${fileExtension}`, mimeType);
            
            // Show success message
            alert(`Data exported successfully as ${fileExtension.toUpperCase()}!`);
        }

        function convertToCSV(data) {
            // Simple CSV conversion - in real implementation, handle nested objects
            if (!Array.isArray(data)) return '';
            
            const headers = Object.keys(data[0]);
            const csvRows = [headers.join(',')];
            
            for (const row of data) {
                const values = headers.map(header => {
                    const value = row[header];
                    return typeof value === 'string' && value.includes(',') ? `"${value}"` : value;
                });
                csvRows.push(values.join(','));
            }
            
            return csvRows.join('\n');
        }

        function convertToKML(data) {
            // Basic KML structure - in real implementation, add proper coordinates
            const items = Array.isArray(data) ? data : data.data || [];
            return `<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
<Document>
    <name>Sablayan Risk Assessment Data</name>
    <description>Exported on ${new Date().toLocaleString()}</description>
    ${items.map(item => `
    <Placemark>
        <name>${item.name || 'Location'}</name>
        <description>${item.description || 'No description'}</description>
        <Point>
            <coordinates>${item.coordinates || '120.7667,12.8333,0'}</coordinates>
        </Point>
    </Placemark>
    `).join('')}
</Document>
</kml>`;
        }

        function convertToGeoJSON(data) {
            const items = Array.isArray(data) ? data : data.data || [];
            // Basic GeoJSON structure
            return JSON.stringify({
                type: "FeatureCollection",
                features: items.map(item => ({
                    type: "Feature",
                    properties: item,
                    geometry: {
                        type: "Point",
                        coordinates: item.coordinates ? 
                            item.coordinates.split(',').reverse().map(coord => parseFloat(coord.trim())) : 
                            [120.7667, 12.8333]
                    }
                }))
            }, null, 2);
        }

        function downloadFile(content, fileName, mimeType) {
            const blob = new Blob([content], { type: mimeType });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }

        function prepareCurrentViewData(barangays, hazardZones) {
            // Prepare data based on current view
            switch(currentView) {
                case 'risk':
                    return barangays.map(barangay => ({
                        name: barangay.name,
                        population: barangay.display_population,
                        hazard_count: barangay.hazard_count,
                        affected_population: barangay.total_affected,
                        risk_level: barangay.has_high_risk ? 'high' : (barangay.hazard_count > 0 ? 'medium' : 'low'),
                        coordinates: barangay.coordinates
                    }));
                case 'population':
                    return barangays.map(barangay => ({
                        name: barangay.name,
                        population: barangay.display_population,
                        base_population: barangay.base_population,
                        actual_population: barangay.actual_population_data,
                        affected_population: barangay.total_affected,
                        risk_percentage: barangay.display_population ? 
                            Math.round((barangay.total_affected / barangay.display_population) * 100) : 0,
                        coordinates: barangay.coordinates
                    }));
                case 'hazards':
                    return hazardZones.map(hazard => ({
                        hazard_name: hazard.hazard_name,
                        barangay: hazard.barangay_name,
                        risk_level: hazard.risk_level,
                        area_km2: hazard.area_km2,
                        affected_population: hazard.affected_population,
                        description: hazard.description,
                        color: hazard.color,
                        coordinates: hazard.coordinates
                    }));
            }
        }
        
function createHazardIcon(hazardType, riskLevel) {
    // Icon colors based on hazard type - UPDATED
    const hazardColors = {
        'Flooding': '#3498db',
        'Storm Surge': '#8e44ad',
        'Tsunami': '#2980b9',
        'Liquefaction': '#7f8c8d',
        'Ground Shaking': '#c0392b',
        'Landslide': '#e67e22'
    };
    
    // Icon classes based on hazard type - UPDATED
    const hazardIcons = {
        'Flooding': 'fa-water',
        'Storm Surge': 'fa-wind',
        'Tsunami': 'fa-wave-square',
        'Liquefaction': 'fa-house-crack',
        'Ground Shaking': 'fa-road',
        'Landslide': 'fa-mountain'
    };
    
    // Risk level colors - UPDATED to match new risk levels
    const riskColors = {
        'High Susceptible': '#e74c3c',
        'Moderate Susceptible': '#f39c12',
        'Low Susceptible': '#27ae60',
        'Not Susceptible': '#95a5a6',
        'Prone': '#e74c3c',
        'Generally Susceptible': '#3498db',
        'PEIS VIII - Very destructive to devastating ground shaking': '#8e44ad',
        'PEIS VII - Destructive ground shaking': '#9b59b6',
        'General Inundation': '#3498db'
    };
    
    // Map old risk levels to new ones for backward compatibility
    let mappedRiskLevel = riskLevel;
    if (riskLevel === 'high') mappedRiskLevel = 'High Susceptible';
    if (riskLevel === 'medium') mappedRiskLevel = 'Moderate Susceptible';
    if (riskLevel === 'low') mappedRiskLevel = 'Low Susceptible';
    
    const iconClass = hazardIcons[hazardType] || 'fa-exclamation-triangle';
    const color = hazardColors[hazardType] || '#95a5a6';
    const borderColor = riskColors[mappedRiskLevel] || riskColors[riskLevel] || '#2c3e50';
    
    // Create a custom div icon
    return L.divIcon({
        className: 'custom-hazard-icon',
        html: `
            <div style="
                background: ${color};
                width: 30px;
                height: 30px;
                border-radius: 50%;
                border: 3px solid ${borderColor};
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 2px 5px rgba(0,0,0,0.3);
                position: relative;
                color: white;
                font-size: 14px;
            ">
                <i class="fas ${iconClass}"></i>
            </div>
        `,
        iconSize: [30, 30],
        iconAnchor: [15, 15],
        popupAnchor: [0, -15]
    });
}


        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initMap();
            
            // Initialize search and measure tools
            initSearchLocation();
            initMeasureDistance();
            
            // View buttons
            document.getElementById('riskViewBtn').addEventListener('click', () => showView('risk'));
            document.getElementById('populationViewBtn').addEventListener('click', () => showView('population'));
            document.getElementById('hazardsViewBtn').addEventListener('click', () => showView('hazards'));
            
            // Tool buttons - UPDATED WITH NEW FUNCTIONALITY
            document.getElementById('searchLocationBtn').addEventListener('click', toggleSearchLocation);
            document.getElementById('measureBtn').addEventListener('click', toggleMeasureDistance);
            document.getElementById('printMapBtn').addEventListener('click', printMap);
            document.getElementById('exportDataBtn').addEventListener('click', exportData);
            
            // Action buttons
            document.getElementById('addHazardBtn').addEventListener('click', function() {
                window.location.href = 'hazard_data.php';
            });
            
            document.getElementById('refreshDataBtn').addEventListener('click', function() {
                location.reload();
            });
            
            document.getElementById('generateReportBtn').addEventListener('click', function() {
                alert('Generating risk report... This would create a PDF report of the current risk assessment.');
            });
            
            document.getElementById('filterBarangaysBtn').addEventListener('click', function() {
                const filter = prompt('Filter barangays by name or risk level:');
                if (filter) {
                    alert(`Filtering barangays by: ${filter}\nThis would filter the map markers in a real application.`);
                }
            });
        });
    </script>
</body>
</html>