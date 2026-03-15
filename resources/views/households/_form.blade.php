<form method="POST"
      action="{{ $household ? route('households.update', $household) : route('households.store') }}">
    @csrf
    @if($household) @method('PUT') @endif

    {{-- Hidden GPS fields --}}
    <input type="hidden" name="latitude"  id="latInput"  value="{{ old('latitude',  $household?->latitude) }}">
    <input type="hidden" name="longitude" id="lngInput"  value="{{ old('longitude', $household?->longitude) }}">

    <div class="row g-4">

        {{-- LEFT: Form fields --}}
        <div class="col-lg-5">

            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-house-chimney-user me-2"></i>Household Information
                </div>
                <div class="card-body">

                    <div class="mb-3">
                        <label class="form-label fw-medium">Barangay <span class="text-danger">*</span></label>
                        <select name="barangay_id" class="form-select @error('barangay_id') is-invalid @enderror"
                                {{ auth()->user()->isBarangayStaff() ? 'disabled' : '' }}>
                            <option value="">— Select Barangay —</option>
                            @foreach($barangays as $b)
                                <option value="{{ $b->id }}"
                                    {{ old('barangay_id', $household?->barangay_id) == $b->id ? 'selected' : '' }}>
                                    {{ $b->name }}
                                </option>
                            @endforeach
                        </select>
                        @if(auth()->user()->isBarangayStaff())
                            <input type="hidden" name="barangay_id" value="{{ auth()->user()->barangay_id }}">
                        @endif
                        @error('barangay_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-medium">Household Head <span class="text-danger">*</span></label>
                        <input type="text" name="household_head"
                               class="form-control @error('household_head') is-invalid @enderror"
                               value="{{ old('household_head', $household?->household_head) }}" required>
                        @error('household_head') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-medium">Sex <span class="text-danger">*</span></label>
                            <select name="sex" class="form-select @error('sex') is-invalid @enderror" required>
                                <option value="">—</option>
                                <option {{ old('sex', $household?->sex) === 'Male'   ? 'selected' : '' }}>Male</option>
                                <option {{ old('sex', $household?->sex) === 'Female' ? 'selected' : '' }}>Female</option>
                            </select>
                            @error('sex') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-medium">Age <span class="text-danger">*</span></label>
                            <input type="number" name="age" id="hhAge" min="0" max="130"
                                   class="form-control @error('age') is-invalid @enderror"
                                   value="{{ old('age', $household?->age) }}" required>
                            @error('age') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-medium">Birthday</label>
                        <input type="date" name="birthday" id="hhBirthday"
                               class="form-control @error('birthday') is-invalid @enderror"
                               value="{{ old('birthday', $household?->birthday?->format('Y-m-d')) }}">
                        <div class="form-text text-muted">
                            Setting a birthday auto-calculates age. Changing age only fills birthday when it is blank.
                        </div>
                        @error('birthday') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-medium">Gender</label>
                        <select name="gender" class="form-select @error('gender') is-invalid @enderror" required>
                            <option value="">—</option>
                            <option {{ old('gender', $household?->gender) === 'Male'   ? 'selected' : '' }}>Male</option>
                            <option {{ old('gender', $household?->gender) === 'Female' ? 'selected' : '' }}>Female</option>
                            <option {{ old('gender', $household?->gender) === 'Other'  ? 'selected' : '' }}>Other</option>
                        </select>
                        @error('gender') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-medium">House Type</label>
                        <select name="house_type" class="form-select">
                            <option value="">—</option>
                            @foreach(['Concrete','Semi-Concrete','Wood','Light Materials','Makeshift'] as $ht)
                                <option {{ old('house_type', $household?->house_type) === $ht ? 'selected' : '' }}>{{ $ht }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-medium">Zone / Sitio / Purok</label>
                        <input type="text" name="sitio_purok_zone"
                               class="form-control"
                               value="{{ old('sitio_purok_zone', $household?->sitio_purok_zone) }}">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-medium">HH Reference ID</label>
                            <input type="text" name="hh_id" class="form-control"
                                   value="{{ old('hh_id', $household?->hh_id) }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-medium">IP / Non-IP</label>
                            <select name="ip_non_ip" class="form-select">
                                <option value="">—</option>
                                <option {{ old('ip_non_ip', $household?->ip_non_ip) === 'IP'     ? 'selected' : '' }}>IP</option>
                                <option {{ old('ip_non_ip', $household?->ip_non_ip) === 'Non-IP' ? 'selected' : '' }}>Non-IP</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-medium">Educational Attainment</label>
                        <select name="educational_attainment" class="form-select">
                            <option value="">—</option>
                            @foreach(['No Formal Education','Elementary','High School','Senior High School','College','Vocational','Post-Graduate'] as $ea)
                                <option {{ old('educational_attainment', $household?->educational_attainment) === $ea ? 'selected' : '' }}>{{ $ea }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-1">
                        <div class="form-check">
                            <input type="checkbox" name="preparedness_kit" id="prepKit" class="form-check-input"
                                   value="1" {{ old('preparedness_kit', $household?->preparedness_kit) ? 'checked' : '' }}>
                            <label for="prepKit" class="form-check-label fw-medium">Has Preparedness Kit</label>
                        </div>
                    </div>

                </div>
            </div>

            {{-- GPS coordinates card --}}
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-map-marker-alt me-2"></i>GPS Coordinates
                    <span class="text-danger ms-1">*</span>
                </div>
                <div class="card-body">
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small">Latitude</label>
                            <input type="text" id="latDisplay"
                                   class="form-control @error('latitude') is-invalid @enderror"
                                   placeholder="12.8xxx"
                                   value="{{ old('latitude', $household?->latitude) }}"
                                   oninput="syncGps(this,'lat')">
                            @error('latitude') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Longitude</label>
                            <input type="text" id="lngDisplay"
                                   class="form-control @error('longitude') is-invalid @enderror"
                                   placeholder="120.8xxx"
                                   value="{{ old('longitude', $household?->longitude) }}"
                                   oninput="syncGps(this,'lng')">
                            @error('longitude') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Click on the map to auto-fill coordinates. Valid range: Lat 12.50–13.20, Lng 120.50–121.20.
                    </small>
                </div>
            </div>

        </div>

        {{-- RIGHT: GPS picker map --}}
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-map me-2"></i>Pick GPS Location</span>
                    <span id="gpsStatus" class="badge
                        @if($household?->hasValidGps()) bg-success @else bg-warning text-dark @endif">
                        @if($household?->hasValidGps()) GPS Set @else Click map to set GPS @endif
                    </span>
                </div>
                <div class="card-body p-0" style="border-radius:0 0 14px 14px; overflow:hidden;">
                    <div id="gpsMap" style="height:480px;"></div>
                </div>
            </div>
        </div>

    </div>

    {{-- Submit --}}
    <div class="d-flex justify-content-end gap-2 mt-3">
        <a href="{{ $household ? route('households.show', $household) : route('households.index') }}"
           class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary px-4">
            <i class="fas fa-save me-1"></i>
            {{ $household ? 'Save Changes' : 'Add Household' }}
        </button>
    </div>

</form>

@push('scripts')
<script>
(function () {
  /*
    var map = L.map('gpsMap').setView([12.835, 120.82], 11);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18, attribution: '© OpenStreetMap contributors'
    }).addTo(map);
*/
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
    var map = L.map('gpsMap', {
        center: [12.835, 120.82],
        zoom: 11,
        layers: [street]
    });

    L.control.layers(
        { 'Street': street, 'Satellite': satellite, 'Hybrid': hybrid },
        {},
        { position: 'topright', collapsed: false }
    ).addTo(map);

    var marker = null;
    var initLat = parseFloat(document.getElementById('latInput').value);
    var initLng = parseFloat(document.getElementById('lngInput').value);

    if (initLat && initLng) {
        marker = L.marker([initLat, initLng]).addTo(map);
        map.setView([initLat, initLng], 15);
    }

    map.on('click', function (e) {
        var lat = parseFloat(e.latlng.lat.toFixed(8));
        var lng = parseFloat(e.latlng.lng.toFixed(8));

        if (lat < 12.50 || lat > 13.20 || lng < 120.50 || lng > 121.20) {
            document.getElementById('gpsStatus').className = 'badge bg-danger';
            document.getElementById('gpsStatus').textContent = 'Outside valid area!';
            return;
        }

        if (marker) { marker.setLatLng([lat, lng]); }
        else { marker = L.marker([lat, lng]).addTo(map); }

        document.getElementById('latInput').value   = lat;
        document.getElementById('lngInput').value   = lng;
        document.getElementById('latDisplay').value = lat;
        document.getElementById('lngDisplay').value = lng;
        document.getElementById('gpsStatus').className   = 'badge bg-success';
        document.getElementById('gpsStatus').textContent = 'GPS Set';
    });
})();

// ── Age ↔ Birthday sync (household head) ─────────────────────────────────────
(function () {
    var ageEl  = document.getElementById('hhAge');
    var bdayEl = document.getElementById('hhBirthday');

    function calcAgeFromDate(dateStr) {
        var d = new Date(dateStr);
        if (isNaN(d)) return null;
        var today = new Date();
        var age = today.getFullYear() - d.getFullYear();
        if (today.getMonth() < d.getMonth() ||
            (today.getMonth() === d.getMonth() && today.getDate() < d.getDate())) {
            age--;
        }
        return Math.max(0, age);
    }

    bdayEl.addEventListener('change', function () {
        if (!this.value) return;
        var age = calcAgeFromDate(this.value);
        if (age !== null) ageEl.value = age;
    });

    ageEl.addEventListener('change', function () {
        // If birthday is already set, do not overwrite it
        if (bdayEl.value) return;
        var age = parseInt(this.value);
        if (isNaN(age) || age < 0) return;
        var year = new Date().getFullYear() - age;
        bdayEl.value = year + '-01-01';
    });
})();

function syncGps(input, field) {
    var val = parseFloat(input.value);
    if (isNaN(val)) return;
    if (field === 'lat') document.getElementById('latInput').value = val;
    if (field === 'lng') document.getElementById('lngInput').value = val;
}
</script>
@endpush
