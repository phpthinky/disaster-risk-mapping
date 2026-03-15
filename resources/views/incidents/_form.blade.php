{{--
    Shared form partial for incident create/edit.
    Expects: $incident (model), $hazardTypes (collection), $action (route URL), $method ('POST'|'PUT')
--}}

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css" />
<style>
    #incidentMap { height: 450px; border-radius: .5rem; }
    .legend-dot { display:inline-block; width:10px; height:10px; border-radius:50%; vertical-align:middle; }
</style>
@endpush

<form method="POST" action="{{ $action }}">
    @csrf
    @if($method === 'PUT') @method('PUT') @endif

    {{-- Basic fields --}}
    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-info-circle me-2"></i>Incident Details</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                           value="{{ old('title', $incident->title) }}" required>
                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Disaster Type <span class="text-danger">*</span></label>
                    <select name="hazard_type_id" class="form-select @error('hazard_type_id') is-invalid @enderror" required>
                        <option value="">— Select —</option>
                        @foreach($hazardTypes as $ht)
                        <option value="{{ $ht->id }}"
                            {{ old('hazard_type_id', $incident->hazard_type_id) == $ht->id ? 'selected' : '' }}>
                            {{ $ht->name }}
                        </option>
                        @endforeach
                    </select>
                    @error('hazard_type_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Incident Date <span class="text-danger">*</span></label>
                    <input type="date" name="incident_date"
                           class="form-control @error('incident_date') is-invalid @enderror"
                           value="{{ old('incident_date', $incident->incident_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required>
                    @error('incident_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select @error('status') is-invalid @enderror">
                        @foreach(['ongoing'=>'Ongoing','monitoring'=>'Monitoring','resolved'=>'Resolved'] as $val=>$label)
                        <option value="{{ $val }}"
                            {{ old('status', $incident->status ?? 'ongoing') === $val ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                        @endforeach
                    </select>
                    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-9">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2">{{ old('description', $incident->description) }}</textarea>
                </div>
            </div>
        </div>
    </div>

    {{-- Map / polygon --}}
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><i class="fas fa-draw-polygon me-2"></i>Draw Affected Area</span>
            <div class="d-flex gap-3 align-items-center small">
                <span><span class="legend-dot bg-primary"></span> Household</span>
                <span><span class="legend-dot bg-danger"></span> Has vulnerable members</span>
            </div>
        </div>
        <div class="card-body">
            <div class="alert alert-info small py-2 mb-3">
                <i class="fas fa-info-circle me-1"></i>
                Use the <strong>polygon tool</strong> (top-right of map) to draw the affected area.
                Household dots and barangay boundaries are loaded for reference.
                Each household dot inside the polygon will be counted in the affected area computation.
            </div>
            <div id="incidentMap" class="mb-2"></div>
            <input type="hidden" name="affected_polygon" id="affectedPolygon"
                   value="{{ old('affected_polygon', $incident->affected_polygon) }}">
            <div id="polygonStatus" class="small text-muted mt-1">
                @if($incident->affected_polygon)
                    <i class="fas fa-check-circle text-success me-1"></i>Polygon loaded — redraw to replace.
                @else
                    No polygon drawn yet.
                @endif
            </div>
        </div>
    </div>

    {{-- Actions --}}
    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('incidents.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-1"></i>Save &amp; Compute Affected Areas
        </button>
    </div>
</form>

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>
<script>
(function () {
    // Tile layers
    var osm       = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 });
    var satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19 });

    var map = L.map('incidentMap', { layers: [satellite], center: [12.84, 120.87], zoom: 11 });

    L.control.layers({ 'Satellite': satellite, 'OpenStreetMap': osm }).addTo(map);

    // Drawn items layer
    var drawnItems = new L.FeatureGroup();
    map.addLayer(drawnItems);

    // Restore existing polygon from hidden input
    var existingGeo = document.getElementById('affectedPolygon').value;
    if (existingGeo) {
        try {
            var parsed = JSON.parse(existingGeo);
            var restored = L.geoJSON(parsed, {
                style: { color: '#dc3545', weight: 2, dashArray: '5,5', fillOpacity: 0.15 }
            });
            restored.eachLayer(function (layer) { drawnItems.addLayer(layer); });
            if (drawnItems.getLayers().length) {
                map.fitBounds(drawnItems.getBounds(), { padding: [40, 40] });
            }
        } catch (e) { /* ignore malformed JSON */ }
    }

    // Draw controls
    var drawControl = new L.Control.Draw({
        position: 'topright',
        draw: {
            polygon: {
                allowIntersection: false,
                shapeOptions: { color: '#dc3545', weight: 2, dashArray: '5,5', fillOpacity: 0.15 }
            },
            polyline: false, rectangle: false, circle: false, circlemarker: false, marker: false
        },
        edit: { featureGroup: drawnItems }
    });
    map.addControl(drawControl);

    map.on(L.Draw.Event.CREATED, function (e) {
        drawnItems.clearLayers();
        drawnItems.addLayer(e.layer);
        savePolygon();
    });

    map.on(L.Draw.Event.EDITED, savePolygon);
    map.on(L.Draw.Event.DELETED, function () {
        document.getElementById('affectedPolygon').value = '';
        document.getElementById('polygonStatus').innerHTML = 'Polygon removed.';
    });

    function savePolygon() {
        var layers = drawnItems.getLayers();
        if (layers.length) {
            var geo = layers[0].toGeoJSON().geometry;
            document.getElementById('affectedPolygon').value = JSON.stringify(geo);
            document.getElementById('polygonStatus').innerHTML =
                '<i class="fas fa-check-circle text-success me-1"></i>Polygon drawn — will be saved on submit.';
        }
    }

    // Load map data (households + boundaries) from API
    fetch('{{ route("api.incidents.map-data") }}')
        .then(function (r) { return r.json(); })
        .then(function (data) {
            // Barangay boundaries
            data.boundaries.forEach(function (b) {
                try {
                    var geo = JSON.parse(b.boundary_geojson);
                    L.geoJSON(geo, { style: { color: '#6c757d', weight: 1, fillOpacity: 0.05 } })
                        .bindTooltip(b.name, {
                            permanent: true, direction: 'center',
                            className: 'bg-transparent border-0 text-dark fw-bold shadow-none small'
                        })
                        .addTo(map);
                } catch (e) {}
            });

            // Household dots
            data.households.forEach(function (h) {
                L.circleMarker([h.latitude, h.longitude], {
                    radius: 3,
                    fillColor: h.has_vulnerable ? '#dc3545' : '#0d6efd',
                    color: '#fff',
                    weight: 1,
                    fillOpacity: 0.85
                })
                .bindPopup(
                    '<strong>' + escHtml(h.household_head) + '</strong><br>' +
                    escHtml(h.barangay_name) + '<br>Members: ' + h.family_members
                )
                .addTo(map);
            });
        })
        .catch(function (err) { console.warn('map-data fetch failed:', err); });

    function escHtml(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }
})();
</script>
@endpush
