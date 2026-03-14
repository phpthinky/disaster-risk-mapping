@extends('layouts.app')

@section('title', 'Barangay Management')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="page-title mb-0">Barangay Management</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item active">Barangays</li>
            </ol>
        </nav>
    </div>
    @can('create', App\Models\Barangay::class)
        <a href="{{ route('barangays.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> Add Barangay
        </a>
    @endcan
</div>
@endsection

@section('content')

{{-- Summary cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stats-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stats-label">Total Barangays</div>
                    <div class="stats-value">{{ $summary['total'] }}</div>
                </div>
                <div class="stats-icon bg-primary bg-opacity-10 text-primary">
                    <i class="fas fa-city"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stats-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stats-label">Boundaries Drawn</div>
                    <div class="stats-value">{{ $summary['with_boundary'] }}<span class="fs-6 fw-normal text-muted"> / {{ $summary['total'] }}</span></div>
                </div>
                <div class="stats-icon bg-success bg-opacity-10 text-success">
                    <i class="fas fa-draw-polygon"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stats-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stats-label">Total Population</div>
                    <div class="stats-value">{{ number_format($summary['total_population']) }}</div>
                </div>
                <div class="stats-icon bg-warning bg-opacity-10 text-warning">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stats-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stats-label">Total Households</div>
                    <div class="stats-value">{{ number_format($summary['total_households']) }}</div>
                </div>
                <div class="stats-icon bg-info bg-opacity-10 text-info">
                    <i class="fas fa-house-chimney-user"></i>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Search + table --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list me-2"></i>Barangay List</span>
        <input type="text" id="searchBox" class="form-control form-control-sm w-auto" placeholder="Search barangays…" style="min-width:200px;">
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="brgyTable">
                <thead class="table-dark">
                    <tr>
                        <th>Barangay</th>
                        <th>Assigned Staff</th>
                        <th class="text-center">Households</th>
                        <th class="text-center">Population</th>
                        <th class="text-center">Area (km²)</th>
                        <th class="text-center">Boundary</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($barangays as $b)
                    <tr class="brgy-row" data-name="{{ strtolower($b->name) }}">
                        <td class="fw-semibold">{{ $b->name }}</td>
                        <td>
                            @if($b->users->isNotEmpty())
                                <span class="badge bg-primary-subtle text-primary">
                                    <i class="fas fa-user me-1"></i>{{ $b->users->first()->username }}
                                </span>
                            @else
                                <span class="text-muted small">Unassigned</span>
                            @endif
                        </td>
                        <td class="text-center">{{ number_format($b->household_count) }}</td>
                        <td class="text-center">{{ number_format($b->population) }}</td>
                        <td class="text-center" style="font-size:.83rem;">
                            <span title="Official area">{{ $b->area_km2 ? number_format($b->area_km2, 2) : '—' }}</span>
                            @if($b->calculated_area_km2)
                                <span class="text-muted">/</span>
                                <span class="text-primary" title="Calculated from boundary">{{ number_format($b->calculated_area_km2, 2) }}</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($b->boundary_geojson)
                                <span class="text-success"><i class="fas fa-check-circle me-1"></i>Drawn</span>
                            @else
                                <span class="text-danger"><i class="fas fa-exclamation-circle me-1"></i>Not yet</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <a href="{{ route('barangays.show', $b) }}" class="btn btn-sm btn-outline-info me-1" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            @can('update', $b)
                                <a href="{{ route('barangays.edit', $b) }}" class="btn btn-sm btn-outline-primary me-1" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                            @endcan
                            @can('delete', $b)
                                <button class="btn btn-sm btn-outline-danger"
                                        onclick="confirmDelete({{ $b->id }}, '{{ addslashes($b->name) }}')"
                                        title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No barangays found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Delete modal --}}
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Delete Barangay</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="delName"></strong>?</p>
                <p class="text-muted small mb-0">This action is blocked if households are linked.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// Live search
document.getElementById('searchBox').addEventListener('input', function () {
    var q = this.value.toLowerCase();
    document.querySelectorAll('.brgy-row').forEach(function (row) {
        row.style.display = row.dataset.name.includes(q) ? '' : 'none';
    });
});

// Delete confirm
var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
function confirmDelete(id, name) {
    document.getElementById('delName').textContent = name;
    document.getElementById('deleteForm').action = '/barangays/' + id;
    deleteModal.show();
}
</script>
@endpush
