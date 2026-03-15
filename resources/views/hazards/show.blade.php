@extends('layouts.app')

@section('title', ($hazard->hazardType->name ?? 'Hazard') . ' — ' . ($hazard->barangay->name ?? ''))

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="page-title mb-0">
            <i class="fas {{ $hazard->hazardType->icon ?? 'fa-exclamation-triangle' }} me-2"
               style="color:{{ $hazard->hazardType->color ?? '#e74c3c' }}"></i>
            {{ $hazard->hazardType->name ?? 'Hazard Zone' }}
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('hazards.index') }}">Hazard Zones</a></li>
                <li class="breadcrumb-item active">{{ $hazard->hazardType->name ?? '#'.$hazard->id }}</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2">
        @if(!auth()->user()->isDivisionChief())
        <a href="{{ route('hazards.edit', $hazard) }}" class="btn btn-primary btn-sm">
            <i class="fas fa-edit me-1"></i> Edit
        </a>
        @endif
        <a href="{{ route('hazards.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>
</div>
@endsection

@section('content')
<div class="row g-4">

    {{-- Left: Info card --}}
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header" style="background:{{ $hazard->hazardType->color ?? '#e74c3c' }}20; border-left: 4px solid {{ $hazard->hazardType->color ?? '#e74c3c' }};">
                <i class="fas {{ $hazard->hazardType->icon ?? 'fa-exclamation-triangle' }} me-2"
                   style="color:{{ $hazard->hazardType->color ?? '#e74c3c' }}"></i>
                Zone Information
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td class="text-muted">Hazard Type</td>
                        <td class="fw-semibold">{{ $hazard->hazardType->name ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Barangay</td>
                        <td class="fw-semibold">{{ $hazard->barangay->name ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Risk Level</td>
                        <td>
                            <span class="badge bg-{{ \App\Models\HazardZone::riskBadgeClass($hazard->risk_level ?? '') }}">
                                {{ $hazard->risk_level ?? '—' }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Area</td>
                        <td>{{ $hazard->area_km2 ? number_format($hazard->area_km2, 4) . ' km²' : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Affected Pop.</td>
                        <td>{{ $hazard->affected_population ? number_format($hazard->affected_population) : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Polygon</td>
                        <td>
                            @if($hazard->coordinates)
                                <span class="badge bg-success"><i class="fas fa-map-marked-alt me-1"></i>Mapped</span>
                            @else
                                <span class="badge bg-secondary">Not mapped</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Added</td>
                        <td class="small">{{ $hazard->created_at->format('M j, Y') }}</td>
                    </tr>
                </table>
            </div>
        </div>

        @if($hazard->description)
        <div class="card">
            <div class="card-header"><i class="fas fa-align-left me-2"></i>Description</div>
            <div class="card-body">
                <p class="mb-0 small">{{ $hazard->description }}</p>
            </div>
        </div>
        @endif
    </div>

    {{-- Right: Map --}}
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="fas fa-map me-2"></i>Zone Map</div>
            <div class="card-body p-0" style="border-radius: 0 0 14px 14px; overflow: hidden;">
                <div id="showMap" style="height: 480px;"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var hazardColor = '{{ $hazard->hazardType->color ?? "#e74c3c" }}';

    var street = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '© OpenStreetMap contributors'
    });
    var satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19, attribution: 'Tiles © Esri'
    });

    var map = L.map('showMap', { center: [12.835, 120.82], zoom: 11, layers: [street] });
    L.control.layers({ 'Street': street, 'Satellite': satellite }, {}, { position: 'topright' }).addTo(map);

    var boundsToFit = [];

    @if($hazard->barangay?->boundary_geojson)
    try {
        var boundaryGj = {!! $hazard->barangay->boundary_geojson !!};
        var boundaryLayer = L.geoJSON(boundaryGj, {
            style: { color: '#f39c12', weight: 2, fillOpacity: 0.05, dashArray: '5,5' }
        }).addTo(map);
        boundsToFit.push(boundaryLayer.getBounds());
    } catch (e) {}
    @endif

    @if($hazard->coordinates)
    try {
        var zoneGj = {!! $hazard->coordinates !!};
        var zoneLayer = L.geoJSON(zoneGj, {
            style: { color: hazardColor, weight: 2, fillOpacity: 0.35 }
        }).addTo(map);
        zoneLayer.bindPopup(
            '<strong>{{ addslashes($hazard->hazardType->name ?? '') }}</strong><br>' +
            '<span class="badge bg-{{ \App\Models\HazardZone::riskBadgeClass($hazard->risk_level ?? '') }}">' +
            '{{ addslashes($hazard->risk_level ?? "") }}</span><br>' +
            '{{ addslashes($hazard->barangay->name ?? "") }}'
        ).openPopup();
        boundsToFit.push(zoneLayer.getBounds());
    } catch (e) {}
    @endif

    if (boundsToFit.length) {
        var combined = boundsToFit[0];
        boundsToFit.forEach(function (b) { combined.extend(b); });
        map.fitBounds(combined, { padding: [30, 30] });
    }
})();
</script>
@endpush
