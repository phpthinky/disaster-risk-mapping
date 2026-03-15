{{--
  Shared form partial for create and edit.
  Expects: $hazardTypes, $barangays, $riskLevels
  Optional: $hazard (edit mode)
--}}
@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css">
<style>
    #zoneMap { height: 420px; border-radius: 0 0 .5rem .5rem; }
    .type-dot { width: 14px; height: 14px; border-radius: 3px; display: inline-block; flex-shrink: 0; }
</style>
@endpush

<div class="row g-4">
    {{-- Left — Form Fields --}}
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-exclamation-triangle me-2"></i>Zone Details</div>
            <div class="card-body">

                {{-- Hazard Type --}}
                <div class="mb-3">
                    <label class="form-label fw-medium">Hazard Type <span class="text-danger">*</span></label>
                    <select name="hazard_type_id" id="hazardTypeSelect"
                            class="form-select @error('hazard_type_id') is-invalid @enderror" required>
                        <option value="">— Select Type —</option>
                        @foreach($hazardTypes as $ht)
                            <option value="{{ $ht->id }}"
                                    data-color="{{ $ht->color }}"
                                    data-icon="{{ $ht->icon }}"
                                    {{ old('hazard_type_id', $hazard?->hazard_type_id) == $ht->id ? 'selected' : '' }}>
                                {{ $ht->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('hazard_type_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                {{-- Barangay --}}
                <div class="mb-3">
                    <label class="form-label fw-medium">Barangay <span class="text-danger">*</span></label>
                    <select name="barangay_id" id="barangaySelect"
                            class="form-select @error('barangay_id') is-invalid @enderror" required
                            {{ auth()->user()->isBarangayStaff() ? 'disabled' : '' }}>
                        <option value="">— Select Barangay —</option>
                        @foreach($barangays as $b)
                            <option value="{{ $b->id }}"
                                    data-boundary="{{ $b->boundary_geojson }}"
                                    {{ old('barangay_id', $hazard?->barangay_id) == $b->id ? 'selected' : '' }}>
                                {{ $b->name }}
                            </option>
                        @endforeach
                    </select>
                    @if(auth()->user()->isBarangayStaff())
                        <input type="hidden" name="barangay_id" value="{{ auth()->user()->barangay_id }}">
                    @endif
                    @error('barangay_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                {{-- Risk Level --}}
                <div class="mb-3">
                    <label class="form-label fw-medium">Risk Level <span class="text-danger">*</span></label>
                    <select name="risk_level" class="form-select @error('risk_level') is-invalid @enderror" required>
                        <option value="">— Select Level —</option>
                        @foreach($riskLevels as $rl)
                            <option value="{{ $rl }}" {{ old('risk_level', $hazard?->risk_level) === $rl ? 'selected' : '' }}>
                                {{ $rl }}
                            </option>
                        @endforeach
                    </select>
                    @error('risk_level') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                {{-- Area --}}
                <div class="mb-3">
                    <label class="form-label fw-medium">Area (km²)</label>
                    <input type="number" name="area_km2" step="0.01" min="0" max="9999.99"
                           id="areaKm2"
                           class="form-control @error('area_km2') is-invalid @enderror"
                           value="{{ old('area_km2', $hazard?->area_km2) }}"
                           placeholder="0.00">
                    <div class="form-text">Auto-calculated when you draw a polygon on the map.</div>
                    @error('area_km2') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                {{-- Description --}}
                <div class="mb-3">
                    <label class="form-label fw-medium">Description</label>
                    <textarea name="description" rows="4"
                              class="form-control @error('description') is-invalid @enderror"
                              placeholder="Additional notes…">{{ old('description', $hazard?->description) }}</textarea>
                    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                {{-- Coordinates (hidden) --}}
                <input type="hidden" name="coordinates" id="coordinatesInput"
                       value="{{ old('coordinates', $hazard?->coordinates) }}">

                @if($hazard?->coordinates)
                    <div class="alert alert-info py-2 small mb-0">
                        <i class="fas fa-map-marked-alt me-1"></i>
                        Polygon saved. Redraw on the map to update it.
                    </div>
                @else
                    <div class="alert alert-warning py-2 small mb-0" id="noPolygonAlert">
                        <i class="fas fa-draw-polygon me-1"></i>
                        No polygon drawn yet. Use the map tool to draw the hazard area.
                    </div>
                @endif

            </div>
        </div>
    </div>

    {{-- Right — Map --}}
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-map me-2"></i>Draw Hazard Zone</span>
                <button type="button" class="btn btn-sm btn-outline-danger" id="clearPolygonBtn">
                    <i class="fas fa-trash me-1"></i>Clear Polygon
                </button>
            </div>
            <div id="zoneMap"></div>
        </div>
    </div>
</div>
@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>
<script>
(function () {
    var hazardColor = '#e74c3c';

    // ── Base layers ──────────────────────────────────────────────────────────
    var street = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap contributors'
    });
    var satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19, attribution: 'Tiles © Esri'
    });

    var map = L.map('zoneMap', { center: [12.835, 120.82], zoom: 11, layers: [street] });
    L.control.layers({ 'Street': street, 'Satellite': satellite }, {}, { position: 'topright' }).addTo(map);

    // ── Draw layer ───────────────────────────────────────────────────────────
    var drawnItems = new L.FeatureGroup();
    map.addLayer(drawnItems);

    var drawControl = new L.Control.Draw({
        edit: { featureGroup: drawnItems },
        draw: {
            polygon: { shapeOptions: { color: hazardColor, weight: 2, fillOpacity: 0.3 } },
            polyline: false, rectangle: false, circle: false,
            marker: false, circlemarker: false
        }
    });
    map.addControl(drawControl);

    // ── Barangay boundary layer ──────────────────────────────────────────────
    var boundaryLayer = null;

    function loadBoundary(geojsonStr) {
        if (boundaryLayer) { map.removeLayer(boundaryLayer); boundaryLayer = null; }
        if (!geojsonStr) return;
        try {
            var gj = JSON.parse(geojsonStr);
            boundaryLayer = L.geoJSON(gj, {
                style: { color: '#f39c12', weight: 2, fillOpacity: 0.05, dashArray: '5,5' }
            }).addTo(map);
            map.fitBounds(boundaryLayer.getBounds(), { padding: [20, 20] });
        } catch (e) {}
    }

    // ── Load existing polygon (edit mode) ────────────────────────────────────
    var existingCoords = document.getElementById('coordinatesInput').value;
    if (existingCoords) {
        try {
            var geom = JSON.parse(existingCoords);
            var existingLayer = L.geoJSON(geom, {
                style: { color: hazardColor, weight: 2, fillOpacity: 0.3 }
            });
            existingLayer.eachLayer(function (l) { drawnItems.addLayer(l); });
            map.fitBounds(drawnItems.getBounds(), { padding: [30, 30] });
        } catch (e) {}
    }

    // ── Hazard type colour sync ──────────────────────────────────────────────
    document.getElementById('hazardTypeSelect').addEventListener('change', function () {
        var opt = this.options[this.selectedIndex];
        hazardColor = opt.dataset.color || '#e74c3c';
    });

    // ── Barangay select → load boundary ─────────────────────────────────────
    document.getElementById('barangaySelect')?.addEventListener('change', function () {
        var opt = this.options[this.selectedIndex];
        loadBoundary(opt.dataset.boundary || '');
    });

    // Trigger on page load for edit mode
    var bSel = document.getElementById('barangaySelect');
    if (bSel && bSel.value) {
        loadBoundary(bSel.options[bSel.selectedIndex].dataset.boundary || '');
    }

    // ── Draw events ──────────────────────────────────────────────────────────
    function syncCoordinates() {
        var layers = [];
        drawnItems.eachLayer(function (l) { layers.push(l); });
        if (layers.length === 0) {
            document.getElementById('coordinatesInput').value = '';
            if (document.getElementById('noPolygonAlert')) {
                document.getElementById('noPolygonAlert').style.display = '';
            }
            return;
        }
        var geom = layers[layers.length - 1].toGeoJSON().geometry;
        document.getElementById('coordinatesInput').value = JSON.stringify(geom);
        if (document.getElementById('noPolygonAlert')) {
            document.getElementById('noPolygonAlert').style.display = 'none';
        }

        // Auto-calculate area using Leaflet's geometry
        var latlngs = layers[layers.length - 1].getLatLngs()[0];
        if (latlngs && latlngs.length > 2) {
            var areaM2 = L.GeometryUtil ? L.GeometryUtil.geodesicArea(latlngs) : null;
            if (areaM2) {
                document.getElementById('areaKm2').value = (areaM2 / 1e6).toFixed(4);
            }
        }
    }

    map.on(L.Draw.Event.CREATED, function (e) {
        drawnItems.clearLayers();
        drawnItems.addLayer(e.layer);
        syncCoordinates();
    });
    map.on(L.Draw.Event.EDITED, syncCoordinates);
    map.on(L.Draw.Event.DELETED, syncCoordinates);

    // ── Clear button ─────────────────────────────────────────────────────────
    document.getElementById('clearPolygonBtn').addEventListener('click', function () {
        drawnItems.clearLayers();
        document.getElementById('coordinatesInput').value = '';
        document.getElementById('areaKm2').value = '';
        if (document.getElementById('noPolygonAlert')) {
            document.getElementById('noPolygonAlert').style.display = '';
        }
    });
})();
</script>
@endpush
