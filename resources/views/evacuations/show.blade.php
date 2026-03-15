@extends('layouts.app')

@section('title', $evacuation->name)

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="page-title mb-0">{{ $evacuation->name }}</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('evacuations.index') }}">Evacuation Centers</a></li>
                <li class="breadcrumb-item active">{{ $evacuation->name }}</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2">
        @if(!auth()->user()->isDivisionChief())
        <a href="{{ route('evacuations.edit', $evacuation) }}" class="btn btn-primary btn-sm">
            <i class="fas fa-edit me-1"></i> Edit
        </a>
        @endif
        <a href="{{ route('evacuations.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>
</div>
@endsection

@section('content')

@php
    $rate = $evacuation->capacity > 0
        ? ($evacuation->current_occupancy / $evacuation->capacity) * 100
        : 0;
    $barColor = $rate >= 80 ? 'danger' : ($rate >= 50 ? 'warning' : 'success');
    $statusColor = match($evacuation->status) {
        'operational' => 'success',
        'maintenance' => 'warning',
        'closed'      => 'danger',
        default       => 'secondary',
    };
@endphp

<div class="row g-4">

    {{-- Details Column --}}
    <div class="col-lg-5">

        {{-- Status & Capacity Card --}}
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-house-medical-flag me-2"></i>Center Details</span>
                <span class="badge bg-{{ $statusColor }} fs-6">{{ ucfirst($evacuation->status) }}</span>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5 text-muted">Barangay</dt>
                    <dd class="col-7">
                        <a href="{{ route('barangays.show', $evacuation->barangay) }}">
                            {{ $evacuation->barangay->name ?? '—' }}
                        </a>
                    </dd>

                    <dt class="col-5 text-muted">Total Capacity</dt>
                    <dd class="col-7">{{ number_format($evacuation->capacity) }} persons</dd>

                    <dt class="col-5 text-muted">Current Occupancy</dt>
                    <dd class="col-7">
                        <div>{{ number_format($evacuation->current_occupancy) }} persons
                            <span class="text-muted">({{ number_format($rate, 1) }}%)</span>
                        </div>
                        <div class="progress mt-1" style="height:6px;">
                            <div class="progress-bar bg-{{ $barColor }}" style="width:{{ min(100, $rate) }}%"></div>
                        </div>
                    </dd>

                    <dt class="col-5 text-muted">Available Slots</dt>
                    <dd class="col-7">
                        <span class="fw-semibold text-{{ $evacuation->availability > 0 ? 'success' : 'danger' }}">
                            {{ number_format($evacuation->availability) }}
                        </span>
                    </dd>
                </dl>
            </div>
        </div>

        {{-- Facilities --}}
        @if($evacuation->facilities)
        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-list-check me-2"></i>Facilities</div>
            <div class="card-body">
                <p class="mb-0">{{ $evacuation->facilities }}</p>
            </div>
        </div>
        @endif

        {{-- Contact --}}
        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-phone me-2"></i>Contact Information</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5 text-muted">Contact Person</dt>
                    <dd class="col-7">{{ $evacuation->contact_person ?: '—' }}</dd>

                    <dt class="col-5 text-muted">Contact Number</dt>
                    <dd class="col-7">{{ $evacuation->contact_number ?: '—' }}</dd>
                </dl>
            </div>
        </div>

        {{-- GPS Coordinates --}}
        <div class="card">
            <div class="card-header"><i class="fas fa-map-marker-alt me-2"></i>GPS Coordinates</div>
            <div class="card-body">
                @if($evacuation->latitude && $evacuation->longitude)
                    <dl class="row mb-0">
                        <dt class="col-4 text-muted">Latitude</dt>
                        <dd class="col-8 font-monospace">{{ $evacuation->latitude }}</dd>
                        <dt class="col-4 text-muted">Longitude</dt>
                        <dd class="col-8 font-monospace">{{ $evacuation->longitude }}</dd>
                    </dl>
                @else
                    <p class="text-muted mb-0"><i class="fas fa-info-circle me-1"></i>No GPS coordinates set.</p>
                @endif
            </div>
        </div>

    </div>

    {{-- Map Column --}}
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-map me-2"></i>Location</div>
            @if($evacuation->latitude && $evacuation->longitude)
                <div id="centerMap" style="height:450px; border-radius: 0 0 .5rem .5rem;"></div>
            @else
                <div class="card-body d-flex align-items-center justify-content-center text-muted" style="min-height:200px;">
                    <div class="text-center">
                        <i class="fas fa-map-marker-alt fa-3x mb-2 opacity-25"></i>
                        <p class="mb-0">No location set.<br>
                            @if(!auth()->user()->isDivisionChief())
                            <a href="{{ route('evacuations.edit', $evacuation) }}">Add GPS coordinates</a>
                            @endif
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>

</div>

{{-- Delete --}}
@if(auth()->user()->isAdmin())
<div class="mt-4 pt-3 border-top">
    <button class="btn btn-outline-danger btn-sm"
        onclick="confirmDelete({{ $evacuation->id }}, '{{ addslashes($evacuation->name) }}')">
        <i class="fas fa-trash me-1"></i>Delete this Center
    </button>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h6 class="modal-title"><i class="fas fa-trash me-2"></i>Delete Center</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">Delete this evacuation center?</p>
                <p class="fw-semibold" id="deleteCenterName"></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger btn-sm">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

@endsection

@push('scripts')
@if($evacuation->latitude && $evacuation->longitude)
<script>
(function () {
    var lat = {{ $evacuation->latitude }};
    var lng = {{ $evacuation->longitude }};
    var street = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '© OpenStreetMap contributors'
    });
    var satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19, attribution: 'Tiles © Esri'
    });
    var map = L.map('centerMap', { center: [lat, lng], zoom: 15, layers: [street] });
    L.control.layers({ 'Street': street, 'Satellite': satellite }, {}, { position: 'topright' }).addTo(map);

    var icon = L.divIcon({
        className: '',
        html: '<div style="background:#0d6efd;color:#fff;border-radius:50%;width:36px;height:36px;display:flex;align-items:center;justify-content:center;box-shadow:0 3px 10px rgba(0,0,0,.4);"><i class="fas fa-house-medical-flag" style="font-size:16px;"></i></div>',
        iconSize: [36, 36], iconAnchor: [18, 18], popupAnchor: [0, -20]
    });
    L.marker([lat, lng], { icon: icon })
        .bindPopup('<strong>{{ addslashes($evacuation->name) }}</strong><br>{{ addslashes($evacuation->barangay->name ?? "") }}')
        .addTo(map)
        .openPopup();
})();
</script>
@endif

@if(auth()->user()->isAdmin())
<script>
function confirmDelete(id, name) {
    document.getElementById('deleteCenterName').textContent = name;
    document.getElementById('deleteForm').action = '/evacuations/' + id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
@endif
@endpush
