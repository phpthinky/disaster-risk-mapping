{{--
  Shared form partial for create and edit.
  Expects: $barangays
  Optional: $evacuation (edit mode)
--}}
@push('styles')
<style>
    #pinMap { height: 400px; border-radius: 0 0 .5rem .5rem; }
</style>
@endpush

<div class="row g-4">
    {{-- Left — Form Fields --}}
    <div class="col-lg-5">

        {{-- Basic Info --}}
        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-info-circle me-2"></i>Basic Information</div>
            <div class="card-body">

                {{-- Name --}}
                <div class="mb-3">
                    <label class="form-label fw-medium">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name"
                           class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $evacuation?->name) }}"
                           placeholder="e.g. Brgy. Hall - Sabang" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
                                {{ old('barangay_id', $evacuation?->barangay_id) == $b->id ? 'selected' : '' }}>
                                {{ $b->name }}
                            </option>
                        @endforeach
                    </select>
                    @if(auth()->user()->isBarangayStaff())
                        <input type="hidden" name="barangay_id" value="{{ auth()->user()->barangay_id }}">
                    @endif
                    @error('barangay_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                {{-- Status --}}
                <div class="mb-3">
                    <label class="form-label fw-medium">Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                        @foreach(['operational' => 'Operational', 'maintenance' => 'Under Maintenance', 'closed' => 'Closed'] as $val => $label)
                        <option value="{{ $val }}" {{ old('status', $evacuation?->status ?? 'operational') === $val ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                        @endforeach
                    </select>
                    @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                {{-- Facilities --}}
                <div class="mb-3">
                    <label class="form-label fw-medium">Facilities / Notes</label>
                    <textarea name="facilities" rows="3"
                              class="form-control @error('facilities') is-invalid @enderror"
                              placeholder="e.g. Restrooms, Kitchen, Generator…">{{ old('facilities', $evacuation?->facilities) }}</textarea>
                    @error('facilities') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        {{-- Capacity --}}
        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-users me-2"></i>Capacity</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label fw-medium">Total Capacity <span class="text-danger">*</span></label>
                        <input type="number" name="capacity" min="1"
                               class="form-control @error('capacity') is-invalid @enderror"
                               value="{{ old('capacity', $evacuation?->capacity) }}"
                               placeholder="e.g. 500" required>
                        @error('capacity') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-medium">Current Occupancy <span class="text-danger">*</span></label>
                        <input type="number" name="current_occupancy" min="0"
                               class="form-control @error('current_occupancy') is-invalid @enderror"
                               value="{{ old('current_occupancy', $evacuation?->current_occupancy ?? 0) }}"
                               placeholder="0" required>
                        @error('current_occupancy') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Contact --}}
        <div class="card">
            <div class="card-header"><i class="fas fa-phone me-2"></i>Contact Information</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-medium">Contact Person</label>
                    <input type="text" name="contact_person"
                           class="form-control @error('contact_person') is-invalid @enderror"
                           value="{{ old('contact_person', $evacuation?->contact_person) }}"
                           placeholder="e.g. Juan dela Cruz">
                    @error('contact_person') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-0">
                    <label class="form-label fw-medium">Contact Number</label>
                    <input type="text" name="contact_number" maxlength="20"
                           class="form-control @error('contact_number') is-invalid @enderror"
                           value="{{ old('contact_number', $evacuation?->contact_number) }}"
                           placeholder="e.g. 09123456789">
                    @error('contact_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

    </div>

    {{-- Right — Map Pin --}}
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-map-marker-alt me-2"></i>Pin Location on Map</span>
                <button type="button" class="btn btn-sm btn-outline-danger" id="clearPinBtn">
                    <i class="fas fa-times me-1"></i>Clear Pin
                </button>
            </div>
            <div id="pinMap"></div>
            <div class="card-footer">
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small text-muted mb-1">Latitude</label>
                        <input type="number" name="latitude" id="latInput" step="any"
                               class="form-control form-control-sm @error('latitude') is-invalid @enderror"
                               value="{{ old('latitude', $evacuation?->latitude) }}"
                               placeholder="12.835…">
                        @error('latitude') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-6">
                        <label class="form-label small text-muted mb-1">Longitude</label>
                        <input type="number" name="longitude" id="lngInput" step="any"
                               class="form-control form-control-sm @error('longitude') is-invalid @enderror"
                               value="{{ old('longitude', $evacuation?->longitude) }}"
                               placeholder="120.82…">
                        @error('longitude') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="form-text mt-1"><i class="fas fa-info-circle me-1"></i>Click the map to place a pin, or type coordinates manually.</div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var street = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '© OpenStreetMap contributors'
    });
    var satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19, attribution: 'Tiles © Esri'
    });

    var map = L.map('pinMap', { center: [12.835, 120.82], zoom: 11, layers: [street] });
    L.control.layers({ 'Street': street, 'Satellite': satellite }, {}, { position: 'topright' }).addTo(map);

    var pin = null;

    var pinIcon = L.divIcon({
        className: '',
        html: '<div style="background:#0d6efd;color:#fff;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;box-shadow:0 3px 8px rgba(0,0,0,.4);"><i class="fas fa-house-medical-flag" style="font-size:14px;"></i></div>',
        iconSize: [32, 32], iconAnchor: [16, 16], popupAnchor: [0, -18]
    });

    function setPin(lat, lng) {
        if (pin) map.removeLayer(pin);
        pin = L.marker([lat, lng], { icon: pinIcon, draggable: true }).addTo(map);
        pin.on('dragend', function () {
            var pos = pin.getLatLng();
            document.getElementById('latInput').value = pos.lat.toFixed(7);
            document.getElementById('lngInput').value = pos.lng.toFixed(7);
        });
        document.getElementById('latInput').value = parseFloat(lat).toFixed(7);
        document.getElementById('lngInput').value = parseFloat(lng).toFixed(7);
    }

    // Load existing pin (edit mode)
    var initLat = document.getElementById('latInput').value;
    var initLng = document.getElementById('lngInput').value;
    if (initLat && initLng) {
        setPin(parseFloat(initLat), parseFloat(initLng));
        map.setView([parseFloat(initLat), parseFloat(initLng)], 15);
    }

    // Click to place pin
    map.on('click', function (e) {
        setPin(e.latlng.lat, e.latlng.lng);
    });

    // Manual coordinate input → move pin
    function onCoordInput() {
        var lat = parseFloat(document.getElementById('latInput').value);
        var lng = parseFloat(document.getElementById('lngInput').value);
        if (!isNaN(lat) && !isNaN(lng)) {
            setPin(lat, lng);
            map.setView([lat, lng], 15);
        }
    }
    document.getElementById('latInput').addEventListener('change', onCoordInput);
    document.getElementById('lngInput').addEventListener('change', onCoordInput);

    // Clear pin
    document.getElementById('clearPinBtn').addEventListener('click', function () {
        if (pin) { map.removeLayer(pin); pin = null; }
        document.getElementById('latInput').value = '';
        document.getElementById('lngInput').value = '';
    });
})();
</script>
@endpush
