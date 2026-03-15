@extends('layouts.app')

@section('title', 'Barangay Risk Analysis Report')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="page-title mb-0"><i class="fas fa-triangle-exclamation me-2"></i>Barangay Risk Analysis</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
                <li class="breadcrumb-item active">Risk Analysis</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('reports.excel', 'risk') }}?{{ http_build_query(request()->all()) }}" class="btn btn-success btn-sm">
            <i class="fas fa-file-excel me-1"></i> Export Excel
        </a>
        <a href="{{ route('reports.pdf', 'risk') }}?{{ http_build_query(request()->all()) }}" class="btn btn-danger btn-sm">
            <i class="fas fa-file-pdf me-1"></i> Export PDF
        </a>
    </div>
</div>
@endsection

@section('content')

{{-- Filters --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            @if(!auth()->user()->isBarangayStaff())
            <div class="col-auto">
                <select name="barangay_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Barangays</option>
                    @foreach($allBarangays as $b)
                        <option value="{{ $b->id }}" {{ request('barangay_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-auto">
                <select name="hazard_type_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Hazard Types</option>
                    @foreach($hazardTypes as $ht)
                        <option value="{{ $ht->id }}" {{ request('hazard_type_id') == $ht->id ? 'selected' : '' }}>{{ $ht->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <select name="risk_level" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Risk Levels</option>
                    @foreach($riskLevels as $rl)
                        <option value="{{ $rl }}" {{ request('risk_level') === $rl ? 'selected' : '' }}>{{ $rl }}</option>
                    @endforeach
                </select>
            </div>
            @if(request()->hasAny(['barangay_id','hazard_type_id','risk_level']))
            <div class="col-auto">
                <a href="{{ route('reports.risk') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
            </div>
            @endif
        </form>
    </div>
</div>

{{-- Data --}}
@forelse($zones as $barangayName => $barangayZones)
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-light fw-semibold">
        <i class="fas fa-map-marker-alt me-2 text-danger"></i>{{ $barangayName }}
        <span class="badge bg-secondary ms-2">{{ $barangayZones->count() }} zone(s)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Hazard Type</th>
                        <th>Risk Level</th>
                        <th class="text-end">Area (km²)</th>
                        <th class="text-end">Affected Pop.</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($barangayZones as $zone)
                    <tr>
                        <td>{{ $zone->hazardType->name ?? '—' }}</td>
                        <td>
                            <span class="badge bg-{{ \App\Models\HazardZone::riskBadgeClass($zone->risk_level) }}">
                                {{ $zone->risk_level }}
                            </span>
                        </td>
                        <td class="text-end">{{ number_format($zone->area_km2, 2) }}</td>
                        <td class="text-end">{{ number_format($zone->affected_population) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@empty
<div class="text-center text-muted py-5">
    <i class="fas fa-search fa-2x mb-2"></i><br>No hazard zones match the selected filters.
</div>
@endforelse

@endsection
