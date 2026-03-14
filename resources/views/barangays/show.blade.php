@extends('layouts.app')

@section('title', $barangay->name . ' — Barangay Profile')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="page-title mb-0">{{ $barangay->name }}</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                @if(!auth()->user()->isBarangayStaff())
                    <li class="breadcrumb-item"><a href="{{ route('barangays.index') }}">Barangays</a></li>
                @endif
                <li class="breadcrumb-item active">{{ $barangay->name }}</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2">
        @can('update', $barangay)
            <a href="{{ route('barangays.edit', $barangay) }}" class="btn btn-primary btn-sm">
                <i class="fas fa-edit me-1"></i> Edit / Draw Boundary
            </a>
        @endcan
        @if(!auth()->user()->isBarangayStaff())
            <a href="{{ route('barangays.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        @endif
    </div>
</div>
@endsection

@section('content')
<div class="row g-4">

    {{-- LEFT COLUMN --}}
    <div class="col-lg-4">

        {{-- Population stats --}}
        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-users me-2"></i>Population Overview</div>
            <div class="card-body">
                <div class="row g-3 text-center">
                    <div class="col-6">
                        <div class="fw-bold fs-4 text-primary">{{ number_format($barangay->population) }}</div>
                        <div class="text-muted small">Total Population</div>
                    </div>
                    <div class="col-6">
                        <div class="fw-bold fs-4 text-success">{{ number_format($barangay->household_count) }}</div>
                        <div class="text-muted small">Households</div>
                    </div>
                    <div class="col-6">
                        <div class="fw-bold fs-5 text-info">{{ number_format($barangay->senior_count) }}</div>
                        <div class="text-muted small">Seniors (60+)</div>
                    </div>
                    <div class="col-6">
                        <div class="fw-bold fs-5 text-warning">{{ number_format($barangay->children_count) }}</div>
                        <div class="text-muted small">Children</div>
                    </div>
                    <div class="col-6">
                        <div class="fw-bold fs-5 text-danger">{{ number_format($barangay->pwd_count) }}</div>
                        <div class="text-muted small">PWD</div>
                    </div>
                    <div class="col-6">
                        <div class="fw-bold fs-5" style="color:#9b59b6;">{{ number_format($barangay->pregnant_count) }}</div>
                        <div class="text-muted small">Pregnant</div>
                    </div>
                    <div class="col-6">
                        <div class="fw-bold fs-5 text-secondary">{{ number_format($barangay->infant_count) }}</div>
                        <div class="text-muted small">Infants (0–2)</div>
                    </div>
                    <div class="col-6">
                        <div class="fw-bold fs-5" style="color:#e67e22;">{{ number_format($barangay->ip_count) }}</div>
                        <div class="text-muted small">IP Members</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Barangay info --}}
        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-info-circle me-2"></i>Barangay Info</div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td class="text-muted">Official Area</td>
                        <td class="fw-semibold">{{ $barangay->area_km2 ? number_format($barangay->area_km2, 2).' km²' : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Calculated Area</td>
                        <td class="fw-semibold text-primary">
                            {{ $barangay->calculated_area_km2 ? number_format($barangay->calculated_area_km2, 4).' km²' : '—' }}
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Coordinates</td>
                        <td class="fw-semibold" style="font-size:.82rem;">{{ $barangay->coordinates ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Boundary</td>
                        <td>
                            @if($barangay->boundary_geojson)
                                <span class="badge bg-success-subtle text-success">
                                    <i class="fas fa-check-circle me-1"></i>Drawn
                                </span>
                            @else
                                <span class="badge bg-danger-subtle text-danger">
                                    <i class="fas fa-exclamation-circle me-1"></i>Not drawn
                                </span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Assigned Staff</td>
                        <td>
                            @if($barangay->users->isNotEmpty())
                                <span class="fw-semibold">{{ $barangay->users->first()->username }}</span>
                            @else
                                <span class="text-muted">Unassigned</span>
                            @endif
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        {{-- Evacuation centres --}}
        <div class="card">
            <div class="card-header"><i class="fas fa-house-medical-flag me-2"></i>Evacuation Centers
                <span class="badge bg-secondary ms-1">{{ $barangay->evacuationCenters->count() }}</span>
            </div>
            <ul class="list-group list-group-flush">
                @forelse($barangay->evacuationCenters as $ec)
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                        <div>
                            <div class="fw-semibold small">{{ $ec->name }}</div>
                            <div class="text-muted" style="font-size:.75rem;">
                                Cap: {{ $ec->capacity }} &bull; Occ: {{ $ec->current_occupancy }}
                            </div>
                        </div>
                        <span class="badge {{ $ec->status === 'operational' ? 'bg-success' : ($ec->status === 'maintenance' ? 'bg-warning text-dark' : 'bg-secondary') }}">
                            {{ ucfirst($ec->status) }}
                        </span>
                    </li>
                @empty
                    <li class="list-group-item text-muted small py-2">No evacuation centers recorded.</li>
                @endforelse
            </ul>
        </div>

    </div>{{-- /col-lg-4 --}}

    {{-- RIGHT COLUMN --}}
    <div class="col-lg-8">

        {{-- Boundary mini-map --}}
        @if($barangay->boundary_geojson)
        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-map me-2"></i>Boundary Map</div>
            <div class="card-body p-0" style="border-radius:0 0 14px 14px; overflow:hidden;">
                <div id="boundaryPreviewMap" style="height:280px;"></div>
            </div>
        </div>
        @endif

        {{-- Hazard zones --}}
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-triangle-exclamation me-2"></i>Hazard Zones</span>
                <span class="badge bg-secondary">{{ $barangay->hazardZones->count() }}</span>
            </div>
            <div class="card-body">
                @if($barangay->hazardZones->isEmpty())
                    <p class="text-muted mb-0">No hazard zones recorded for this barangay.</p>
                @else
                    {{-- Type summary pills --}}
                    <div class="mb-3 d-flex flex-wrap gap-2">
                        @foreach($hazardStats as $stat)
                            <span class="badge rounded-pill" style="background:{{ $stat['color'] }}; font-size:.78rem; padding:.4em .75em;">
                                {{ $stat['name'] }} &times;{{ $stat['count'] }}
                            </span>
                        @endforeach
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Type</th>
                                    <th>Risk Level</th>
                                    <th class="text-end">Area (km²)</th>
                                    <th class="text-end">Affected Pop.</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($barangay->hazardZones as $hz)
                                <tr>
                                    <td>
                                        <span class="badge me-1" style="background:{{ $hz->hazardType->color ?? '#888' }}; width:10px; height:10px; border-radius:50%; display:inline-block;"></span>
                                        {{ $hz->hazardType->name ?? '—' }}
                                    </td>
                                    <td><span class="badge bg-warning-subtle text-warning-emphasis">{{ $hz->risk_level }}</span></td>
                                    <td class="text-end">{{ $hz->area_km2 ? number_format($hz->area_km2, 2) : '—' }}</td>
                                    <td class="text-end">{{ number_format($hz->affected_population ?? 0) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Recent population data --}}
        @if($barangay->populationData->isNotEmpty())
        <div class="card">
            <div class="card-header"><i class="fas fa-chart-bar me-2"></i>Latest Population Record</div>
            <div class="card-body">
                @php $pd = $barangay->populationData->first(); @endphp
                <div class="row g-3 text-center">
                    <div class="col-4 col-md-2">
                        <div class="fw-bold text-primary">{{ number_format($pd->total_population ?? 0) }}</div>
                        <div class="text-muted small">Total</div>
                    </div>
                    <div class="col-4 col-md-2">
                        <div class="fw-bold text-success">{{ number_format($pd->households ?? 0) }}</div>
                        <div class="text-muted small">Households</div>
                    </div>
                    <div class="col-4 col-md-2">
                        <div class="fw-bold text-info">{{ number_format($pd->elderly_count ?? 0) }}</div>
                        <div class="text-muted small">Seniors</div>
                    </div>
                    <div class="col-4 col-md-2">
                        <div class="fw-bold text-warning">{{ number_format($pd->children_count ?? 0) }}</div>
                        <div class="text-muted small">Children</div>
                    </div>
                    <div class="col-4 col-md-2">
                        <div class="fw-bold text-danger">{{ number_format($pd->pwd_count ?? 0) }}</div>
                        <div class="text-muted small">PWD</div>
                    </div>
                    <div class="col-4 col-md-2">
                        <div class="fw-bold" style="color:#e67e22;">{{ number_format($pd->ips_count ?? 0) }}</div>
                        <div class="text-muted small">IP</div>
                    </div>
                </div>
                <div class="text-muted small mt-2">
                    <i class="fas fa-clock me-1"></i>
                    Last synced: {{ $pd->data_date ? $pd->data_date->format('M j, Y') : '—' }}
                    &bull; by {{ $pd->entered_by ?? 'system' }}
                </div>
            </div>
        </div>
        @endif

    </div>{{-- /col-lg-8 --}}
</div>
@endsection

@push('scripts')
@if($barangay->boundary_geojson)
<script>
(function () {
    var map = L.map('boundaryPreviewMap', { zoomControl: true, attributionControl: false });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18 }).addTo(map);

    var geo = @json(json_decode($barangay->boundary_geojson));
    var layer = L.geoJSON(geo, {
        style: { color: '#2563eb', weight: 2, fillOpacity: 0.15 }
    }).addTo(map);
    map.fitBounds(layer.getBounds(), { padding: [16, 16] });
})();
</script>
@endif
@endpush
