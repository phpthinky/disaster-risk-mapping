@extends('layouts.map')

@section('title', 'Interactive Map')

@push('styles')
<style>
/* ── Layout ───────────────────────────────────────────── */
.map-wrapper  { display:flex; height:100vh; }
.map-sidebar  {
    width:290px; min-width:290px;
    background:#fff; overflow-y:auto;
    box-shadow:2px 0 10px rgba(0,0,0,.12); z-index:500;
    display:flex; flex-direction:column; font-size:.83rem;
}
#map          { flex:1; height:100vh; }

/* ── Sidebar sections ─────────────────────────────────── */
.sidebar-header { background:#212529; color:#fff; padding:.75rem 1rem; flex-shrink:0; }
.sidebar-header a { color:#adb5bd; text-decoration:none; }
.sidebar-header a:hover { color:#fff; }

.sidebar-section { padding:.6rem 1rem; border-bottom:1px solid #f0f0f0; }
.sidebar-section h6 {
    font-size:.7rem; text-transform:uppercase; letter-spacing:.06em;
    color:#6c757d; margin-bottom:.5rem; font-weight:600;
}

/* ── Stats row ─────────────────────────────────────────── */
.stat-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:4px; }
.stat-cell { background:#f8f9fa; border-radius:6px; padding:6px 4px; text-align:center; }
.stat-cell .val { font-weight:700; font-size:1rem; line-height:1.1; }
.stat-cell .lbl { font-size:.65rem; color:#6c757d; margin-top:1px; }

/* ── Layer toggles ─────────────────────────────────────── */
.layer-toggle { display:flex; align-items:center; gap:8px; margin-bottom:6px; cursor:pointer; }
.layer-toggle input[type=checkbox] { width:16px; height:16px; cursor:pointer; }
.layer-dot { width:14px; height:14px; border-radius:3px; flex-shrink:0; }

/* ── Legends ───────────────────────────────────────────── */
.legend-item  { display:flex; align-items:center; gap:7px; margin-bottom:4px; }
.legend-swatch { width:14px; height:14px; border-radius:3px; flex-shrink:0; }
.legend-circle { width:14px; height:14px; border-radius:50%; flex-shrink:0; }
.legend-icon-box {
    width:22px; height:22px; border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    color:#fff; font-size:.55rem; flex-shrink:0;
}

/* ── Responsive: phones ────────────────────────────────── */
@media(max-width:768px){
    .map-wrapper  { flex-direction:column; }
    .map-sidebar  { width:100%; height:auto; max-height:38vh; min-width:unset; }
    #map          { flex:1; height:62vh; }
}

/* ── Leaflet tooltip overrides ─────────────────────────── */
.leaflet-tooltip.brgy-label {
    background:transparent; border:none; box-shadow:none;
    color:#212529; font-weight:600; font-size:.7rem;
    white-space:nowrap; pointer-events:none;
}
</style>
@endpush

@section('content')
<div class="map-wrapper">

    {{-- ══════════════════════════════════════════════════ SIDEBAR ══ --}}
    <div class="map-sidebar">

        {{-- Header --}}
        <div class="sidebar-header">
            <div class="d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fas fa-map-location-dot me-2"></i>Risk Assessment Map</span>
                <a href="{{ route('dashboard') }}" title="Back to Dashboard">
                    <i class="fas fa-arrow-left"></i>
                </a>
            </div>
        </div>

        {{-- Stats --}}
        <div class="sidebar-section">
            <h6><i class="fas fa-chart-bar me-1"></i>Overview</h6>
            <div class="stat-grid">
                <div class="stat-cell">
                    <div class="val text-primary">{{ $stats['barangays'] }}</div>
                    <div class="lbl">Barangays</div>
                </div>
                <div class="stat-cell">
                    <div class="val text-warning">{{ $stats['hazard_zones'] }}</div>
                    <div class="lbl">Hazard Zones</div>
                </div>
                <div class="stat-cell">
                    <div class="val text-secondary">{{ number_format($stats['households']) }}</div>
                    <div class="lbl">Households</div>
                </div>
                <div class="stat-cell">
                    <div class="val text-danger">{{ number_format($stats['at_risk']) }}</div>
                    <div class="lbl">At Risk</div>
                </div>
                <div class="stat-cell">
                    <div class="val text-danger">{{ $stats['incidents'] }}</div>
                    <div class="lbl">Active Inc.</div>
                </div>
                <div class="stat-cell">
                    <div class="val text-success">{{ $stats['evac_centers'] }}</div>
                    <div class="lbl">Evac Open</div>
                </div>
            </div>
        </div>

        {{-- Layer toggles --}}
        <div class="sidebar-section">
            <h6><i class="fas fa-layer-group me-1"></i>Layers</h6>
            <label class="layer-toggle">
                <input type="checkbox" id="layerBoundaries" checked>
                <span class="layer-dot" style="background:#495057;opacity:.6;border:2px solid #495057;"></span>
                Barangay Boundaries
            </label>
            <label class="layer-toggle">
                <input type="checkbox" id="layerHazards" checked>
                <span class="layer-dot" style="background:#e74c3c;"></span>
                Hazard Zones
            </label>
            <label class="layer-toggle">
                <input type="checkbox" id="layerHouseholds">
                <span class="layer-dot" style="background:#3498db;border-radius:50%;"></span>
                Household Locations
            </label>
            <label class="layer-toggle">
                <input type="checkbox" id="layerHeatmap">
                <span class="layer-dot" style="background:linear-gradient(90deg,#3498db,#e74c3c);"></span>
                Population Heatmap
            </label>
            <label class="layer-toggle">
                <input type="checkbox" id="layerIncidents" checked>
                <span class="layer-dot" style="background:#dc3545;border:2px dashed #dc3545;background-color:rgba(220,53,69,.15);"></span>
                Active Incidents
            </label>
            <label class="layer-toggle">
                <input type="checkbox" id="layerEvac" checked>
                <span class="layer-dot" style="background:#198754;border-radius:4px;"></span>
                Evacuation Centers
            </label>
        </div>

        {{-- Risk level legend --}}
        <div class="sidebar-section">
            <h6><i class="fas fa-shield-halved me-1"></i>Risk Levels</h6>
            @php
                $riskColors = [
                    'High Susceptible'     => '#e74c3c',
                    'Moderate Susceptible' => '#e67e22',
                    'Low Susceptible'      => '#f1c40f',
                    'Prone'                => '#9b59b6',
                    'Generally Susceptible'=> '#3498db',
                    'General Inundation'   => '#1abc9c',
                    'PEIS VIII'            => '#922b21',
                    'PEIS VII'             => '#cb4335',
                    'Not Susceptible'      => '#27ae60',
                ];
            @endphp
            @foreach($riskColors as $label => $color)
            <div class="legend-item">
                <span class="legend-swatch" style="background:{{ $color }};"></span>
                <span>{{ $label }}</span>
            </div>
            @endforeach
        </div>

        {{-- Hazard types legend --}}
        <div class="sidebar-section">
            <h6><i class="fas fa-triangle-exclamation me-1"></i>Hazard Types</h6>
            @foreach($hazardTypes as $ht)
            <div class="legend-item">
                <span class="legend-icon-box" style="background:{{ $ht->color ?? '#6c757d' }};">
                    <i class="fas {{ $ht->icon ?? 'fa-exclamation-triangle' }}"></i>
                </span>
                <span>{{ $ht->name }}</span>
            </div>
            @endforeach
        </div>

        {{-- Household legend --}}
        <div class="sidebar-section">
            <h6><i class="fas fa-house me-1"></i>Households</h6>
            <div class="legend-item">
                <span class="legend-circle" style="background:#dc3545;"></span>
                <span>Has vulnerable member (PWD / Elderly / Infant / Pregnant)</span>
            </div>
            <div class="legend-item">
                <span class="legend-circle" style="background:#3498db;"></span>
                <span>Regular household</span>
            </div>
        </div>

        {{-- Evacuation legend --}}
        <div class="sidebar-section">
            <h6><i class="fas fa-building-shield me-1"></i>Evacuation Centers</h6>
            <div class="legend-item">
                <span class="legend-icon-box" style="background:#198754;border-radius:4px;">
                    <i class="fas fa-building-shield"></i>
                </span>
                <span>Operational</span>
            </div>
            <div class="legend-item">
                <span class="legend-icon-box" style="background:#ffc107;border-radius:4px;">
                    <i class="fas fa-building-shield" style="color:#212529;"></i>
                </span>
                <span>Maintenance</span>
            </div>
            <div class="legend-item">
                <span class="legend-icon-box" style="background:#dc3545;border-radius:4px;">
                    <i class="fas fa-building-shield"></i>
                </span>
                <span>Closed</span>
            </div>
        </div>

        {{-- Quick actions --}}
        <div class="sidebar-section mt-auto">
            <h6><i class="fas fa-bolt me-1"></i>Quick Actions</h6>
            <a href="{{ route('hazards.index') }}" class="btn btn-sm btn-outline-warning w-100 mb-1">
                <i class="fas fa-triangle-exclamation me-1"></i>Hazard Zones
            </a>
            <a href="{{ route('incidents.index') }}" class="btn btn-sm btn-outline-danger w-100 mb-1">
                <i class="fas fa-file-circle-exclamation me-1"></i>Incident Reports
            </a>
            <a href="{{ route('population.index') }}" class="btn btn-sm btn-outline-primary w-100 mb-1">
                <i class="fas fa-users me-1"></i>Population Data
            </a>
            <button class="btn btn-sm btn-outline-secondary w-100" id="btnRefresh">
                <i class="fas fa-sync-alt me-1"></i>Refresh All Layers
            </button>
        </div>

    </div>{{-- /.map-sidebar --}}

    {{-- ══════════════════════════════════════════════════════ MAP ══ --}}
    <div id="map"></div>

</div>{{-- /.map-wrapper --}}
@endsection

@push('scripts')
<script>
(function () {
'use strict';

// ── Risk level → fill colour mapping ──────────────────────────────────────
var RISK_COLORS = {
    'High Susceptible':      '#e74c3c',
    'Moderate Susceptible':  '#e67e22',
    'Low Susceptible':       '#f1c40f',
    'Prone':                 '#9b59b6',
    'Generally Susceptible': '#3498db',
    'General Inundation':    '#1abc9c',
    'PEIS VIII - Very destructive to devastating ground shaking': '#922b21',
    'PEIS VII - Destructive ground shaking':                      '#cb4335',
    'Not Susceptible':       '#27ae60',
};

// ── Tile layers ────────────────────────────────────────────────────────────
var osm       = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',        { maxZoom:19 });
var satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom:19 });
var terrain   = L.tileLayer('https://tile.opentopomap.org/{z}/{x}/{y}.png',          { maxZoom:17 });

// ── Map init ───────────────────────────────────────────────────────────────
var map = L.map('map', {
    layers: [osm],
    center: [12.8333, 120.7667],
    zoom: 11,
    zoomControl: true,
});

L.control.layers({
    'OpenStreetMap': osm,
    'Satellite':     satellite,
    'Terrain':       terrain,
}, null, { position: 'topright' }).addTo(map);

// ── Overlay layer groups ───────────────────────────────────────────────────
var layerBoundaries = L.layerGroup().addTo(map);
var layerHazards    = L.layerGroup().addTo(map);
var layerHouseholds = L.layerGroup();              // off by default
var layerHeatmap    = L.layerGroup();              // off by default
var layerIncidents  = L.layerGroup().addTo(map);
var layerEvac       = L.layerGroup().addTo(map);

// ── Checkbox wiring ────────────────────────────────────────────────────────
function wire(checkboxId, layerGroup) {
    var cb = document.getElementById(checkboxId);
    if (!cb) return;
    cb.addEventListener('change', function () {
        if (this.checked) map.addLayer(layerGroup);
        else              map.removeLayer(layerGroup);
    });
}
wire('layerBoundaries', layerBoundaries);
wire('layerHazards',    layerHazards);
wire('layerHouseholds', layerHouseholds);
wire('layerHeatmap',    layerHeatmap);
wire('layerIncidents',  layerIncidents);
wire('layerEvac',       layerEvac);

// ── Refresh button ─────────────────────────────────────────────────────────
document.getElementById('btnRefresh').addEventListener('click', function () {
    [layerBoundaries, layerHazards, layerHouseholds, layerHeatmap, layerIncidents, layerEvac]
        .forEach(function (lg) { lg.clearLayers(); });
    loadAll();
});

// ── Popup helper ──────────────────────────────────────────────────────────
function esc(s) {
    if (!s) return '';
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

// ── Data loading ───────────────────────────────────────────────────────────
function loadAll() {
    loadBoundaries();
    loadHazards();
    loadHouseholds();
    loadIncidents();
    loadEvac();
}

// Layer 1 — Barangay boundaries
function loadBoundaries() {
    fetch('{{ route("api.map.barangays") }}')
        .then(function (r) { return r.json(); })
        .then(function (rows) {
            rows.forEach(function (b) {
                if (!b.boundary_geojson) return;
                try {
                    var geo = JSON.parse(b.boundary_geojson);
                    var pct = b.population > 0
                        ? ' (' + (b.at_risk_count / b.population * 100).toFixed(1) + '% at risk)'
                        : '';
                    var layer = L.geoJSON(geo, {
                        style: { color: '#495057', weight: 1.5, fillColor: '#dee2e6', fillOpacity: 0.07 },
                    });
                    layer.bindTooltip(b.name, {
                        permanent: true, direction: 'center',
                        className: 'brgy-label',
                    });
                    layer.bindPopup(
                        '<div style="min-width:210px">' +
                        '<h6 class="mb-2">' + esc(b.name) + '</h6>' +
                        '<table class="table table-sm table-borderless mb-0" style="font-size:.8rem">' +
                        '<tr><td>Population</td><td class="text-end fw-bold">' + b.population.toLocaleString() + '</td></tr>' +
                        '<tr><td>Households</td><td class="text-end fw-bold">' + b.household_count.toLocaleString() + '</td></tr>' +
                        '<tr><td class="text-danger">At Risk</td><td class="text-end fw-bold text-danger">' + b.at_risk_count.toLocaleString() + pct + '</td></tr>' +
                        '<tr><td>PWD</td><td class="text-end">' + b.pwd_count + '</td></tr>' +
                        '<tr><td>Elderly</td><td class="text-end">' + b.senior_count + '</td></tr>' +
                        '<tr><td>Infants</td><td class="text-end">' + b.infant_count + '</td></tr>' +
                        '<tr><td>Pregnant</td><td class="text-end">' + b.pregnant_count + '</td></tr>' +
                        '<tr><td>IPs</td><td class="text-end">' + b.ip_count + '</td></tr>' +
                        '<tr><td>Hazard Zones</td><td class="text-end">' + b.hazard_zones_count + '</td></tr>' +
                        '</table></div>'
                    );
                    layerBoundaries.addLayer(layer);
                } catch (e) { console.warn('boundary parse error:', b.name, e); }
            });
        });
}

// Layer 2 — Hazard zones
function loadHazards() {
    fetch('{{ route("api.map.hazard-zones") }}')
        .then(function (r) { return r.json(); })
        .then(function (rows) {
            rows.forEach(function (hz) {
                var riskColor = RISK_COLORS[hz.risk_level] || '#6c757d';

                // Polygon from `coordinates` field (GeoJSON string)
                if (hz.coordinates) {
                    try {
                        var geo = JSON.parse(hz.coordinates);
                        var poly = L.geoJSON(geo, {
                            style: {
                                color:       riskColor,
                                weight:      1.5,
                                fillColor:   riskColor,
                                fillOpacity: 0.30,
                            },
                        });
                        poly.bindPopup(hazardPopup(hz, riskColor));
                        layerHazards.addLayer(poly);
                    } catch (e) {}
                }
            });
        });
}

function hazardPopup(hz, riskColor) {
    return '<div style="min-width:210px">' +
        '<h6 style="color:' + esc(hz.hazard_color) + '">' +
        '<i class="fas ' + esc(hz.hazard_icon) + ' me-1"></i>' + esc(hz.hazard_name) +
        '</h6>' +
        '<table class="table table-sm table-borderless mb-0" style="font-size:.8rem">' +
        '<tr><td>Barangay</td><td class="fw-bold">' + esc(hz.barangay_name) + '</td></tr>' +
        '<tr><td>Risk Level</td><td style="color:' + riskColor + ';font-weight:600">' + esc(hz.risk_level) + '</td></tr>' +
        '<tr><td>Affected Pop.</td><td class="fw-bold">' + hz.affected_population.toLocaleString() + '</td></tr>' +
        (hz.area_km2 ? '<tr><td>Area</td><td>' + hz.area_km2 + ' km²</td></tr>' : '') +
        (hz.description ? '<tr><td colspan="2" class="text-muted">' + esc(hz.description) + '</td></tr>' : '') +
        '</table></div>';
}

// Layer 3 (dots) + Layer 4 (heatmap) — Households
function loadHouseholds() {
    fetch('{{ route("api.map.households") }}')
        .then(function (r) { return r.json(); })
        .then(function (rows) {
            var heatData = [];

            rows.forEach(function (h) {
                var color = h.has_vulnerable ? '#dc3545' : '#3498db';

                var dot = L.circleMarker([h.latitude, h.longitude], {
                    radius: 4, fillColor: color, color: '#fff',
                    weight: 1, fillOpacity: 0.85,
                });

                var vulnStr = h.vuln_flags && h.vuln_flags.length
                    ? '<div class="mt-1">Vulnerability: <strong>' + h.vuln_flags.join(', ') + '</strong></div>'
                    : '';
                dot.bindPopup(
                    '<div style="min-width:180px">' +
                    '<h6 class="mb-1">' + esc(h.household_head) + '</h6>' +
                    '<div style="font-size:.8rem">' +
                    '<div>Barangay: ' + esc(h.barangay_name) + '</div>' +
                    (h.sitio ? '<div>Sitio/Zone: ' + esc(h.sitio) + '</div>' : '') +
                    '<div>Members: <strong>' + h.family_members + '</strong></div>' +
                    vulnStr +
                    (h.is_ip ? '<div class="badge bg-secondary mt-1">IP Household</div>' : '') +
                    '</div></div>'
                );
                layerHouseholds.addLayer(dot);

                heatData.push([h.latitude, h.longitude, h.family_members || 1]);
            });

            if (heatData.length > 0) {
                layerHeatmap.addLayer(
                    L.heatLayer(heatData, { radius: 25, blur: 15, maxZoom: 16, max: 20 })
                );
            }
        });
}

// Layer 5 — Active/monitoring incidents
function loadIncidents() {
    fetch('{{ route("api.map.incidents") }}')
        .then(function (r) { return r.json(); })
        .then(function (rows) {
            rows.forEach(function (inc) {
                try {
                    var geo   = JSON.parse(inc.affected_polygon);
                    var color = inc.hazard_color || '#dc3545';
                    var layer = L.geoJSON(geo, {
                        style: {
                            color: color, weight: 2, dashArray: '8,4',
                            fillColor: color, fillOpacity: 0.18,
                        },
                    });
                    var statusBadge = inc.status === 'ongoing'
                        ? '<span class="badge bg-danger">Ongoing</span>'
                        : '<span class="badge bg-warning text-dark">Monitoring</span>';
                    layer.bindPopup(
                        '<div style="min-width:220px">' +
                        '<h6 style="color:' + esc(color) + '">' +
                        '<i class="fas ' + esc(inc.hazard_icon) + ' me-1"></i>' + esc(inc.title) +
                        '</h6>' +
                        '<table class="table table-sm table-borderless mb-0" style="font-size:.8rem">' +
                        '<tr><td>Type</td><td>' + esc(inc.hazard_name || '—') + '</td></tr>' +
                        '<tr><td>Date</td><td>' + esc(inc.incident_date) + '</td></tr>' +
                        '<tr><td>Status</td><td>' + statusBadge + '</td></tr>' +
                        '<tr><td>Affected Pop.</td><td class="fw-bold">' + inc.total_affected.toLocaleString() + '</td></tr>' +
                        '</table>' +
                        '<a href="/incidents/' + inc.id + '" class="btn btn-sm btn-outline-secondary w-100 mt-2" target="_blank">View Report</a>' +
                        '</div>'
                    );
                    layerIncidents.addLayer(layer);
                } catch (e) {}
            });
        });
}

// Layer 6 — Evacuation centers
function loadEvac() {
    fetch('{{ route("api.map.evacuation-centers") }}')
        .then(function (r) { return r.json(); })
        .then(function (rows) {
            rows.forEach(function (ec) {
                var statusColors = { operational: '#198754', maintenance: '#ffc107', closed: '#dc3545' };
                var color      = statusColors[ec.status] || '#6c757d';
                var textColor  = ec.status === 'maintenance' ? '#212529' : '#fff';
                var iconHtml   =
                    '<div style="background:' + color + ';width:30px;height:30px;border-radius:6px;' +
                    'display:flex;align-items:center;justify-content:center;color:' + textColor + ';' +
                    'font-size:14px;border:2px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.25);">' +
                    '<i class="fas fa-building-shield"></i></div>';

                var marker = L.marker([ec.latitude, ec.longitude], {
                    icon: L.divIcon({ html: iconHtml, className: '', iconSize: [30, 30], iconAnchor: [15, 15] }),
                });

                var pct      = ec.capacity > 0 ? Math.round(ec.current_occupancy / ec.capacity * 100) : 0;
                var barColor = pct >= 80 ? 'danger' : pct >= 50 ? 'warning' : 'success';

                marker.bindPopup(
                    '<div style="min-width:220px">' +
                    '<h6><i class="fas fa-building-shield me-1"></i>' + esc(ec.name) + '</h6>' +
                    '<table class="table table-sm table-borderless mb-0" style="font-size:.8rem">' +
                    '<tr><td>Barangay</td><td>' + esc(ec.barangay_name) + '</td></tr>' +
                    '<tr><td>Status</td><td style="color:' + color + ';font-weight:600">' + esc(ec.status) + '</td></tr>' +
                    '<tr><td>Occupancy</td><td>' + ec.current_occupancy + ' / ' + ec.capacity + '</td></tr>' +
                    '<tr><td colspan="2">' +
                    '<div class="progress" style="height:6px">' +
                    '<div class="progress-bar bg-' + barColor + '" style="width:' + pct + '%"></div>' +
                    '</div></td></tr>' +
                    '<tr><td>Available</td><td class="fw-bold text-success">' + ec.availability + ' slots</td></tr>' +
                    (ec.contact_person ? '<tr><td>Contact</td><td>' + esc(ec.contact_person) + '</td></tr>' : '') +
                    (ec.contact_number ? '<tr><td>Phone</td><td>' + esc(ec.contact_number) + '</td></tr>' : '') +
                    (ec.facilities ? '<tr><td>Facilities</td><td>' + esc(ec.facilities) + '</td></tr>' : '') +
                    '</table></div>'
                );
                layerEvac.addLayer(marker);
            });
        });
}

// ── Boot ───────────────────────────────────────────────────────────────────
loadAll();

})();
</script>
@endpush
