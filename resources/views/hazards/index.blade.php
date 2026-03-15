@extends('layouts.app')

@section('title', 'Hazard Zones')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="page-title mb-0">Hazard Zones</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item active">Hazard Zones</li>
            </ol>
        </nav>
    </div>
    @if(!auth()->user()->isDivisionChief())
    <a href="{{ route('hazards.create') }}" class="btn btn-primary btn-sm">
        <i class="fas fa-plus me-1"></i> Add Hazard Zone
    </a>
    @endif
</div>
@endsection

@section('content')

{{-- Stat Cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card stat-card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger rounded-3 p-3">
                    <i class="fas fa-triangle-exclamation fa-lg"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4">{{ number_format($stats['total']) }}</div>
                    <div class="text-muted small">Total Zones</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger rounded-3 p-3">
                    <i class="fas fa-radiation fa-lg"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4">{{ number_format($stats['high_risk']) }}</div>
                    <div class="text-muted small">High Risk Zones</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning rounded-3 p-3">
                    <i class="fas fa-users fa-lg"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4">{{ number_format($stats['affected']) }}</div>
                    <div class="text-muted small">Affected Population</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-info bg-opacity-10 text-info rounded-3 p-3">
                    <i class="fas fa-map fa-lg"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4">{{ number_format($stats['total_area'], 2) }}</div>
                    <div class="text-muted small">Total Area (km²)</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted">Hazard Type</label>
                <select name="hazard_type_id" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    @foreach($hazardTypes as $ht)
                        <option value="{{ $ht->id }}" {{ request('hazard_type_id') == $ht->id ? 'selected' : '' }}>
                            {{ $ht->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Risk Level</label>
                <select name="risk_level" class="form-select form-select-sm">
                    <option value="">All Levels</option>
                    @foreach($riskLevels as $rl)
                        <option value="{{ $rl }}" {{ request('risk_level') === $rl ? 'selected' : '' }}>
                            {{ $rl }}
                        </option>
                    @endforeach
                </select>
            </div>
            @if(!auth()->user()->isBarangayStaff())
            <div class="col-md-3">
                <label class="form-label small text-muted">Barangay</label>
                <select name="barangay_id" class="form-select form-select-sm">
                    <option value="">All Barangays</option>
                    @foreach($barangays as $b)
                        <option value="{{ $b->id }}" {{ request('barangay_id') == $b->id ? 'selected' : '' }}>
                            {{ $b->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-md-auto d-flex gap-2">
                <button class="btn btn-sm btn-primary"><i class="fas fa-filter me-1"></i>Filter</button>
                <a href="{{ route('hazards.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

{{-- Table --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list me-2"></i>Hazard Zones
            <span class="badge bg-secondary ms-1">{{ $zones->total() }}</span>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Hazard Type</th>
                        @if(!auth()->user()->isBarangayStaff())
                        <th>Barangay</th>
                        @endif
                        <th>Risk Level</th>
                        <th class="text-end">Area (km²)</th>
                        <th class="text-end">Affected Pop.</th>
                        <th class="text-center">Map</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($zones as $zone)
                    <tr>
                        <td>
                            <span class="d-flex align-items-center gap-2">
                                <span style="width:12px;height:12px;border-radius:3px;background:{{ $zone->hazardType->color ?? '#999' }};display:inline-block;flex-shrink:0;"></span>
                                <i class="fas {{ $zone->hazardType->icon ?? 'fa-exclamation' }} small" style="color:{{ $zone->hazardType->color ?? '#999' }}"></i>
                                <span class="fw-medium">{{ $zone->hazardType->name ?? '—' }}</span>
                            </span>
                        </td>
                        @if(!auth()->user()->isBarangayStaff())
                        <td>{{ $zone->barangay->name ?? '—' }}</td>
                        @endif
                        <td>
                            <span class="badge bg-{{ \App\Models\HazardZone::riskBadgeClass($zone->risk_level ?? '') }}">
                                {{ $zone->risk_level ?? '—' }}
                            </span>
                        </td>
                        <td class="text-end">{{ $zone->area_km2 ? number_format($zone->area_km2, 2) : '—' }}</td>
                        <td class="text-end">{{ $zone->affected_population ? number_format($zone->affected_population) : '—' }}</td>
                        <td class="text-center">
                            @if($zone->coordinates)
                                <span class="badge bg-success"><i class="fas fa-map-marker-alt me-1"></i>Yes</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('hazards.show', $zone) }}" class="btn btn-outline-primary" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                @if(!auth()->user()->isDivisionChief())
                                <a href="{{ route('hazards.edit', $zone) }}" class="btn btn-outline-secondary" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                @if(auth()->user()->isAdmin())
                                <button class="btn btn-outline-danger" title="Delete"
                                    onclick="confirmDelete({{ $zone->id }}, '{{ addslashes($zone->hazardType->name ?? '') }} — {{ addslashes($zone->barangay->name ?? '') }}')">
                                    <i class="fas fa-trash"></i>
                                </button>
                                @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No hazard zones found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($zones->hasPages())
    <div class="card-footer">{{ $zones->links() }}</div>
    @endif
</div>

{{-- Delete Modal --}}
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h6 class="modal-title"><i class="fas fa-trash me-2"></i>Delete Hazard Zone</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">Delete this zone?</p>
                <p class="fw-semibold" id="deleteZoneName"></p>
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
@endsection

@push('scripts')
<script>
function confirmDelete(id, name) {
    document.getElementById('deleteZoneName').textContent = name;
    document.getElementById('deleteForm').action = '/hazards/' + id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
@endpush
