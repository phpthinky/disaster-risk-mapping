@extends('layouts.app')

@section('title', $incident->title)

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    #showMap { height: 380px; border-radius: .5rem; }
</style>
@endpush

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="page-title mb-0">{{ $incident->title }}</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('incidents.index') }}">Incident Reports</a></li>
                <li class="breadcrumb-item active">{{ $incident->title }}</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @php
            $statusClass = match($incident->status) {
                'ongoing'    => 'danger',
                'monitoring' => 'warning',
                'resolved'   => 'success',
                default      => 'secondary',
            };
        @endphp
        <span class="badge bg-{{ $statusClass }} fs-6 px-3 py-2">{{ ucfirst($incident->status) }}</span>
        @if(!auth()->user()->isDivisionChief())
        <a href="{{ route('incidents.edit', $incident) }}" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-edit me-1"></i>Edit
        </a>
        @endif
        <a href="{{ route('incidents.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Back
        </a>
    </div>
</div>
@endsection

@section('content')

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">
    <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="row g-4 mb-4">

    {{-- Left: Incident meta --}}
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-circle-info me-2"></i>Incident Info</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-5 text-muted small">Disaster Type</dt>
                    <dd class="col-7">
                        @if($incident->hazardType)
                        <span style="color:{{ $incident->hazardType->color ?? '#333' }}">
                            <i class="fas {{ $incident->hazardType->icon ?? 'fa-exclamation-triangle' }} me-1"></i>
                            {{ $incident->hazardType->name }}
                        </span>
                        @else — @endif
                    </dd>

                    <dt class="col-5 text-muted small">Date</dt>
                    <dd class="col-7">{{ $incident->incident_date->format('F j, Y') }}</dd>

                    <dt class="col-5 text-muted small">Reported By</dt>
                    <dd class="col-7">{{ $incident->reporter?->username ?? '—' }}</dd>

                    <dt class="col-5 text-muted small">Reported On</dt>
                    <dd class="col-7 small">{{ $incident->created_at->format('M j, Y g:i A') }}</dd>

                    @if($incident->description)
                    <dt class="col-5 text-muted small">Description</dt>
                    <dd class="col-7 small">{{ $incident->description }}</dd>
                    @endif
                </dl>

                <hr>

                {{-- Summary stats --}}
                <div class="row g-2 text-center">
                    <div class="col-6">
                        <div class="bg-light rounded p-2">
                            <div class="fw-bold fs-5 text-danger">{{ number_format($totals['affected_population']) }}</div>
                            <div class="small text-muted">People Affected</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="bg-light rounded p-2">
                            <div class="fw-bold fs-5">{{ number_format($totals['affected_households']) }}</div>
                            <div class="small text-muted">Households</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="bg-light rounded p-2">
                            <div class="fw-semibold">{{ number_format($totals['affected_pwd']) }}</div>
                            <div class="small text-muted">PWD</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="bg-light rounded p-2">
                            <div class="fw-semibold">{{ number_format($totals['affected_seniors']) }}</div>
                            <div class="small text-muted">Elderly</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="bg-light rounded p-2">
                            <div class="fw-semibold">{{ number_format($totals['affected_infants']) }}</div>
                            <div class="small text-muted">Infants</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="bg-light rounded p-2">
                            <div class="fw-semibold">{{ number_format($totals['affected_minors']) }}</div>
                            <div class="small text-muted">Children</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="bg-light rounded p-2">
                            <div class="fw-semibold">{{ number_format($totals['affected_pregnant']) }}</div>
                            <div class="small text-muted">Pregnant</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="bg-light rounded p-2">
                            <div class="fw-semibold">{{ number_format($totals['ip_count']) }}</div>
                            <div class="small text-muted">IPs</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Right: Map --}}
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-map-location-dot me-2"></i>Affected Area Map</div>
            <div class="card-body p-2">
                @if($incident->affected_polygon)
                <div id="showMap"></div>
                @else
                <div class="d-flex align-items-center justify-content-center h-100 text-muted py-5">
                    <div class="text-center">
                        <i class="fas fa-draw-polygon fa-2x mb-2 opacity-50 d-block"></i>
                        No affected area polygon drawn for this incident.
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Affected areas per barangay --}}
@if($incident->affectedAreas->isNotEmpty())
<div class="card">
    <div class="card-header">
        <i class="fas fa-table me-2"></i>Affected Areas by Barangay
        <span class="badge bg-secondary ms-1">{{ $incident->affectedAreas->count() }} barangay(s)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Barangay</th>
                        <th class="text-end">Households</th>
                        <th class="text-end">Population</th>
                        <th class="text-end">PWD</th>
                        <th class="text-end">Elderly</th>
                        <th class="text-end">Infants</th>
                        <th class="text-end">Children</th>
                        <th class="text-end">Pregnant</th>
                        <th class="text-end">IPs</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($incident->affectedAreas->sortByDesc('affected_population') as $area)
                    <tr>
                        <td class="fw-medium">{{ $area->barangay?->name ?? '—' }}</td>
                        <td class="text-end">{{ number_format($area->affected_households) }}</td>
                        <td class="text-end fw-medium text-danger">{{ number_format($area->affected_population) }}</td>
                        <td class="text-end">{{ number_format($area->affected_pwd) }}</td>
                        <td class="text-end">{{ number_format($area->affected_seniors) }}</td>
                        <td class="text-end">{{ number_format($area->affected_infants) }}</td>
                        <td class="text-end">{{ number_format($area->affected_minors) }}</td>
                        <td class="text-end">{{ number_format($area->affected_pregnant) }}</td>
                        <td class="text-end">{{ number_format($area->ip_count) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="table-dark fw-bold">
                    <tr>
                        <td>Total</td>
                        <td class="text-end">{{ number_format($totals['affected_households']) }}</td>
                        <td class="text-end">{{ number_format($totals['affected_population']) }}</td>
                        <td class="text-end">{{ number_format($totals['affected_pwd']) }}</td>
                        <td class="text-end">{{ number_format($totals['affected_seniors']) }}</td>
                        <td class="text-end">{{ number_format($totals['affected_infants']) }}</td>
                        <td class="text-end">{{ number_format($totals['affected_minors']) }}</td>
                        <td class="text-end">{{ number_format($totals['affected_pregnant']) }}</td>
                        <td class="text-end">{{ number_format($totals['ip_count']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@else
<div class="alert alert-secondary">
    <i class="fas fa-info-circle me-2"></i>
    No affected area data — no polygon was drawn or no households were found inside the polygon.
</div>
@endif

@endsection

@push('scripts')
@if($incident->affected_polygon)
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    var satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19 });
    var osm       = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 });

    var map = L.map('showMap', { layers: [satellite] });
    L.control.layers({ 'Satellite': satellite, 'OpenStreetMap': osm }).addTo(map);

    // Draw the stored polygon
    var geo = {!! $incident->affected_polygon !!};
    var layer = L.geoJSON(geo, {
        style: { color: '#dc3545', weight: 2, dashArray: '5,5', fillOpacity: 0.15 }
    }).addTo(map);
    map.fitBounds(layer.getBounds(), { padding: [40, 40] });

    // Overlay household dots inside the polygon bounds
    fetch('{{ route("api.incidents.map-data") }}')
        .then(function (r) { return r.json(); })
        .then(function (data) {
            data.boundaries.forEach(function (b) {
                try {
                    var bg = JSON.parse(b.boundary_geojson);
                    L.geoJSON(bg, { style: { color: '#6c757d', weight: 1, fillOpacity: 0.04 } })
                        .bindTooltip(b.name, {
                            permanent: true, direction: 'center',
                            className: 'bg-transparent border-0 text-dark fw-bold shadow-none small'
                        })
                        .addTo(map);
                } catch (e) {}
            });
            data.households.forEach(function (h) {
                L.circleMarker([h.latitude, h.longitude], {
                    radius: 3,
                    fillColor: h.has_vulnerable ? '#dc3545' : '#0d6efd',
                    color: '#fff', weight: 1, fillOpacity: 0.85
                })
                .bindPopup('<strong>' + h.household_head + '</strong><br>' + h.barangay_name + '<br>Members: ' + h.family_members)
                .addTo(map);
            });
        });
})();
</script>
@endif
@endpush
