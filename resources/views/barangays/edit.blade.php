@extends('layouts.app')

@section('title', $barangay ? 'Edit '.$barangay->name : 'Add Barangay')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="page-title mb-0">
            {{ $barangay ? 'Edit '.$barangay->name : 'Add Barangay' }}
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('barangays.index') }}">Barangays</a></li>
                <li class="breadcrumb-item active">{{ $barangay ? 'Edit' : 'Create' }}</li>
            </ol>
        </nav>
    </div>
    <a href="{{ $barangay ? route('barangays.show', $barangay) : route('barangays.index') }}"
       class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i> Back
    </a>
</div>
@endsection

@section('content')
<form id="brgyForm"
      method="POST"
      action="{{ $barangay ? route('barangays.update', $barangay) : route('barangays.store') }}">
    @csrf
    @if($barangay) @method('PUT') @endif

    {{-- Hidden boundary fields --}}
    <input type="hidden" name="boundary_geojson" id="boundaryGeoJson"
           value="{{ $barangay?->boundary_geojson }}">
    <input type="hidden" name="calculated_area_km2" id="calcAreaInput"
           value="{{ $barangay?->calculated_area_km2 }}">

    <div class="row g-4">

        {{-- LEFT: form --}}
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-info-circle me-2"></i>Barangay Information
                </div>
                <div class="card-body">

                    <div class="mb-3">
                        <label for="name" class="form-label fw-medium">Barangay Name <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $barangay?->name) }}" required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="area_km2" class="form-label fw-medium">Official Area (km²)</label>
                        <input type="number" step="0.01" id="area_km2" name="area_km2"
                               class="form-control @error('area_km2') is-invalid @enderror"
                               value="{{ old('area_km2', $barangay?->area_km2) }}">
                        @error('area_km2') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="coordinates" class="form-label fw-medium">
                            Center Coordinates
                            <small class="text-muted">(lat,lng)</small>
                        </label>
                        <input type="text" id="coordinates" name="coordinates"
                               class="form-control @error('coordinates') is-invalid @enderror"
                               placeholder="12.8472,120.7803"
                               value="{{ old('coordinates', $barangay?->coordinates) }}">
                        @error('coordinates') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    @if(auth()->user()->isAdmin())
                    <div class="mb-3">
                        <label for="staff_user_id" class="form-label fw-medium">Assigned Staff</label>
                        <select id="staff_user_id" name="staff_user_id" class="form-select">
                            <option value="">— Unassigned —</option>
                            @foreach($staffUsers as $staff)
                                <option value="{{ $staff->id }}"
                                    {{ old('staff_user_id', $barangay ? $barangay->users->where('role','barangay_staff')->first()?->id : null) == $staff->id ? 'selected' : '' }}>
                                    {{ $staff->username }}
                                    @if($staff->barangay_id && $staff->barangay_id !== $barangay?->id)
                                        (assigned elsewhere)
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                </div>
            </div>

            {{-- Calculated area display --}}
            <div class="card mb-4" id="calcAreaCard" style="{{ $barangay?->calculated_area_km2 ? '' : 'display:none;' }}">
                <div class="card-header"><i class="fas fa-ruler-combined me-2"></i>Calculated Area</div>
                <div class="card-body text-center">
                    <div class="fs-3 fw-bold text-primary" id="calcAreaDisplay">
                        {{ $barangay?->calculated_area_km2 ? number_format($barangay->calculated_area_km2, 4) : '—' }}
                        <span class="fs-6 fw-normal text-muted">km²</span>
                    </div>
                    <small class="text-muted">Auto-computed from drawn polygon</small>
                </div>
            </div>

            {{-- Boundary status --}}
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-draw-polygon me-2"></i>Boundary Status</div>
                <div class="card-body">
                    <div id="boundaryStatus">
                        @if($barangay?->boundary_geojson)
                            <span class="text-success"><i class="fas fa-check-circle me-1"></i>Boundary drawn</span>
                        @else
                            <span class="text-warning"><i class="fas fa-exclamation-circle me-1"></i>No boundary yet — draw on the map</span>
                        @endif
                    </div>
                    @if($barangay?->boundary_geojson && auth()->user()->isAdmin())
                    <div class="mt-2">
                        <button type="button" class="btn btn-outline-danger btn-sm w-100" id="clearBoundaryBtn">
                            <i class="fas fa-trash me-1"></i>Remove Boundary
                        </button>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Map instructions --}}
            <div class="card bg-light border-0">
                <div class="card-body py-2 px-3">
                    <small class="text-muted">
                        <strong>How to draw:</strong><br>
                        1. Click the polygon tool (<i class="fas fa-draw-polygon"></i>) in the map toolbar.<br>
                        2. Click points to trace the boundary.<br>
                        3. Click the first point again to close.<br>
                        4. Orange dashed = existing boundaries.
                    </small>
                </div>
            </div>
        </div>

        {{-- RIGHT: Leaflet map --}}
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-map me-2"></i>Draw Barangay Boundary</span>
                    <div id="mapAlert" class="alert alert-warning py-1 px-2 mb-0 small" style="display:none;"></div>
                </div>
                <div class="card-body p-0" style="border-radius:0 0 14px 14px; overflow:hidden;">
                    <div id="boundaryMap" style="height:560px;"></div>
                </div>
            </div>
        </div>

    </div>

    {{-- Submit --}}
    <div class="d-flex justify-content-end gap-2 mt-3">
        <a href="{{ $barangay ? route('barangays.show', $barangay) : route('barangays.index') }}"
           class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary px-4">
            <i class="fas fa-save me-1"></i>
            {{ $barangay ? 'Save Changes' : 'Create Barangay' }}
        </button>
    </div>

</form>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css">
<style>
    #boundaryMap .leaflet-draw-toolbar { margin-top: 10px; }
    #boundaryMap .leaflet-control-layers { font-size: .85rem; }
    .leaflet-control-fullscreen a {
        display: flex !important;
        align-items: center;
        justify-content: center;
        width: 30px !important;
        height: 30px !important;
        font-size: 14px;
        color: #333 !important;
        text-decoration: none !important;
    }
    .leaflet-control-fullscreen a:hover { background: #f4f4f4 !important; }
    #boundaryMap:fullscreen          { width: 100% !important; height: 100% !important; }
    #boundaryMap:-webkit-full-screen { width: 100% !important; height: 100% !important; }
    #boundaryMap:-moz-full-screen    { width: 100% !important; height: 100% !important; }
</style>
@endpush

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>
<script>
(function () {
    // ── Base tile layers ─────────────────────────────────────────────────────
    var street = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    });

    var satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19,
        attribution: 'Tiles © Esri — Source: Esri, USGS, NOAA'
    });

    var hybrid = L.layerGroup([
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            maxZoom: 19,
            attribution: 'Tiles © Esri'
        }),
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
            maxZoom: 19,
            opacity: 1
        })
    ]);

    // ── Init map ─────────────────────────────────────────────────────────────
    var map = L.map('boundaryMap', {
        center: [12.835, 120.82],
        zoom: 11,
        layers: [street]
    });

    L.control.layers(
        { 'Street': street, 'Satellite': satellite, 'Hybrid': hybrid },
        {},
        { position: 'topright', collapsed: false }
    ).addTo(map);

    // ── Custom fullscreen control (Font Awesome icons) ────────────────────────
    var FullscreenControl = L.Control.extend({
        options: { position: 'topleft' },
        onAdd: function (m) {
            var container = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-control-fullscreen');
            var btn = L.DomUtil.create('a', '', container);
            btn.href = '#';
            btn.title = 'Full Screen';
            btn.innerHTML = '<i class="fas fa-expand"></i>';

            L.DomEvent.on(btn, 'click', function (e) {
                L.DomEvent.stopPropagation(e);
                L.DomEvent.preventDefault(e);
                var el = m.getContainer();
                if (!document.fullscreenElement) {
                    el.requestFullscreen && el.requestFullscreen();
                    btn.innerHTML = '<i class="fas fa-compress"></i>';
                    btn.title = 'Exit Full Screen';
                } else {
                    document.exitFullscreen && document.exitFullscreen();
                    btn.innerHTML = '<i class="fas fa-expand"></i>';
                    btn.title = 'Full Screen';
                }
            });

            document.addEventListener('fullscreenchange', function () {
                if (!document.fullscreenElement) {
                    btn.innerHTML = '<i class="fas fa-expand"></i>';
                    btn.title = 'Full Screen';
                    m.invalidateSize();
                }
            });

            return container;
        }
    });
    new FullscreenControl().addTo(map);

    // ── Draw layer ──────────────────────────────────────────────────────────
    var drawnItems = new L.FeatureGroup();
    map.addLayer(drawnItems);

    var drawControl = new L.Control.Draw({
        edit: { featureGroup: drawnItems },
        draw: {
            polygon:   { shapeOptions: { color: '#2563eb', fillOpacity: 0.2 } },
            polyline:  false,
            rectangle: false,
            circle:    false,
            circlemarker: false,
            marker:    false,
        }
    });
    map.addControl(drawControl);

    // ── Load existing boundary ───────────────────────────────────────────────
    var existingGeoJson = @json($barangay?->boundary_geojson ? json_decode($barangay->boundary_geojson) : null);

    if (existingGeoJson) {
        var existingLayer = L.geoJSON(existingGeoJson, {
            style: { color: '#2563eb', weight: 2, fillOpacity: 0.15 }
        });
        existingLayer.eachLayer(function (layer) { drawnItems.addLayer(layer); });
        map.fitBounds(drawnItems.getBounds(), { padding: [20, 20] });
    }

    // ── Load all other boundaries as reference overlays ──────────────────────
    var currentId = {{ $barangay?->id ?? 'null' }};

    fetch('/api/barangays/boundaries')
        .then(function (r) { return r.json(); })
        .then(function (rows) {
            rows.forEach(function (row) {
                if (row.id === currentId) return; // skip own
                try {
                    var geo = typeof row.boundary_geojson === 'string'
                        ? JSON.parse(row.boundary_geojson)
                        : row.boundary_geojson;

                    L.geoJSON(geo, {
                        style: { color: '#e67e22', weight: 2, dashArray: '6,4', fillOpacity: 0.08, fillColor: '#e67e22' }
                    }).bindTooltip(row.name, { permanent: false, direction: 'center', className: 'bg-white border px-2 py-1 rounded small' })
                      .addTo(map);
                } catch (e) {}
            });
        });

    // ── Area calculation (Shoelace / Haversine approximation) ────────────────
    function calcAreaKm2(latlngs) {
        var R = 6371; // km
        var n = latlngs.length;
        if (n < 3) return 0;
        var area = 0;
        for (var i = 0; i < n; i++) {
            var j = (i + 1) % n;
            var lat1 = latlngs[i].lat * Math.PI / 180;
            var lat2 = latlngs[j].lat * Math.PI / 180;
            var dLng = (latlngs[j].lng - latlngs[i].lng) * Math.PI / 180;
            area += (dLng) * (2 + Math.sin(lat1) + Math.sin(lat2));
        }
        return Math.abs(area * R * R / 2);
    }

    function toGeoJson(layer) {
        var latlngs = layer.getLatLngs()[0];
        var coords = latlngs.map(function (ll) { return [ll.lng, ll.lat]; });
        coords.push(coords[0]); // close ring
        return { type: 'Polygon', coordinates: [coords] };
    }

    function updateBoundaryFields(layer) {
        var geo   = toGeoJson(layer);
        var area  = calcAreaKm2(layer.getLatLngs()[0]);
        document.getElementById('boundaryGeoJson').value  = JSON.stringify(geo);
        document.getElementById('calcAreaInput').value    = area.toFixed(6);
        document.getElementById('calcAreaDisplay').innerHTML = area.toFixed(4) + ' <span class="fs-6 fw-normal text-muted">km²</span>';
        document.getElementById('calcAreaCard').style.display = '';
        document.getElementById('boundaryStatus').innerHTML  =
            '<span class="text-success"><i class="fas fa-check-circle me-1"></i>Boundary drawn — save form to persist.</span>';
        document.getElementById('mapAlert').style.display = 'none';
    }

    // ── Draw events ──────────────────────────────────────────────────────────
    map.on(L.Draw.Event.CREATED, function (e) {
        drawnItems.clearLayers();
        drawnItems.addLayer(e.layer);
        updateBoundaryFields(e.layer);
    });

    map.on(L.Draw.Event.EDITED, function (e) {
        e.layers.eachLayer(function (layer) { updateBoundaryFields(layer); });
    });

    map.on(L.Draw.Event.DELETED, function () {
        document.getElementById('boundaryGeoJson').value  = '';
        document.getElementById('calcAreaInput').value    = '';
        document.getElementById('calcAreaCard').style.display = 'none';
        document.getElementById('boundaryStatus').innerHTML  =
            '<span class="text-warning"><i class="fas fa-exclamation-circle me-1"></i>Boundary removed.</span>';
    });

    // ── Remove boundary button ─────────────────────────────────────────────
    var clearBtn = document.getElementById('clearBoundaryBtn');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            if (!confirm('Remove the saved boundary for this barangay?')) return;
            fetch('/barangays/{{ $barangay?->id }}/boundary', {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.ok) {
                    drawnItems.clearLayers();
                    document.getElementById('boundaryGeoJson').value = '';
                    document.getElementById('calcAreaInput').value   = '';
                    document.getElementById('calcAreaCard').style.display = 'none';
                    document.getElementById('boundaryStatus').innerHTML =
                        '<span class="text-warning"><i class="fas fa-exclamation-circle me-1"></i>Boundary removed.</span>';
                    clearBtn.parentElement.style.display = 'none';
                } else {
                    alert(res.msg);
                }
            });
        });
    }

    // ── Center-coords auto-fill when existing boundary present ───────────────
    if (existingGeoJson && !document.getElementById('coordinates').value) {
        var bounds = drawnItems.getBounds();
        var center = bounds.getCenter();
        document.getElementById('coordinates').value =
            center.lat.toFixed(6) + ',' + center.lng.toFixed(6);
    }

})();
</script>
@endpush
