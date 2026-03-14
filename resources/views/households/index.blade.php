@extends('layouts.app')

@section('title', 'Household Management')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="page-title mb-0">Household Management</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item active">Households</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('households.export', request()->only('barangay_id')) }}"
           class="btn btn-outline-success btn-sm">
            <i class="fas fa-file-excel me-1"></i> Export Excel
        </a>
        @if(!auth()->user()->isDivisionChief())
        <a href="{{ route('households.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-1"></i> Add Household
        </a>
        @endif
    </div>
</div>
@endsection

@section('content')

{{-- Summary cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stats-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stats-label">Total Households</div>
                    <div class="stats-value">{{ number_format($summary['total']) }}</div>
                </div>
                <div class="stats-icon bg-primary bg-opacity-10 text-primary">
                    <i class="fas fa-house-chimney-user"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stats-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stats-label">Total Population</div>
                    <div class="stats-value">{{ number_format($summary['population']) }}</div>
                </div>
                <div class="stats-icon bg-success bg-opacity-10 text-success">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stats-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stats-label">PWD</div>
                    <div class="stats-value">{{ number_format($summary['pwd']) }}</div>
                </div>
                <div class="stats-icon bg-danger bg-opacity-10 text-danger">
                    <i class="fas fa-wheelchair"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stats-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="stats-label">Seniors (60+)</div>
                    <div class="stats-value">{{ number_format($summary['seniors']) }}</div>
                </div>
                <div class="stats-icon bg-warning bg-opacity-10 text-warning">
                    <i class="fas fa-person-cane"></i>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="card mb-4">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('households.index') }}" class="row g-2 align-items-end">
            @if(!auth()->user()->isBarangayStaff())
            <div class="col-md-4">
                <label class="form-label small mb-1">Filter by Barangay</label>
                <select name="barangay_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Barangays</option>
                    @foreach($barangays as $b)
                        <option value="{{ $b->id }}" {{ $selectedBarangay == $b->id ? 'selected' : '' }}>
                            {{ $b->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-md-4">
                <label class="form-label small mb-1">Search by Name</label>
                <div class="input-group input-group-sm">
                    <input type="text" name="search" class="form-control"
                           placeholder="Household head…"
                           value="{{ request('search') }}">
                    <button class="btn btn-outline-secondary" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            @if(request()->anyFilled(['barangay_id','search']))
            <div class="col-auto">
                <a href="{{ route('households.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-times me-1"></i>Clear
                </a>
            </div>
            @endif
        </form>
    </div>
</div>

{{-- Table --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list me-2"></i>Household List</span>
        <span class="badge bg-secondary">{{ $households->total() }} records</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Household Head</th>
                        @if(!auth()->user()->isBarangayStaff())
                        <th>Barangay</th>
                        @endif
                        <th>Zone / Sitio</th>
                        <th class="text-center">Members</th>
                        <th class="text-center">PWD</th>
                        <th class="text-center">GPS</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($households as $hh)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $hh->household_head }}</div>
                            @if($hh->hh_id)
                                <div class="text-muted small">HH#{{ $hh->hh_id }}</div>
                            @endif
                            @if($hh->ip_non_ip === 'IP')
                                <span class="badge bg-purple-subtle text-purple" style="font-size:.7rem;background:#f3e8ff;color:#7c3aed;">IP</span>
                            @endif
                        </td>
                        @if(!auth()->user()->isBarangayStaff())
                        <td class="small">{{ $hh->barangay->name ?? '—' }}</td>
                        @endif
                        <td class="small text-muted">{{ $hh->sitio_purok_zone ?? '—' }}</td>
                        <td class="text-center fw-semibold">{{ $hh->family_members }}</td>
                        <td class="text-center">
                            @if($hh->pwd_count > 0)
                                <span class="badge bg-danger-subtle text-danger">{{ $hh->pwd_count }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($hh->hasValidGps())
                                <span class="text-success" title="{{ $hh->latitude }}, {{ $hh->longitude }}">
                                    <i class="fas fa-map-marker-alt"></i>
                                </span>
                            @else
                                <span class="text-danger"><i class="fas fa-exclamation-circle"></i></span>
                            @endif
                        </td>
                        <td class="text-center">
                            <a href="{{ route('households.show', $hh) }}"
                               class="btn btn-sm btn-outline-info me-1" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            @if(!auth()->user()->isDivisionChief())
                            <a href="{{ route('households.edit', $hh) }}"
                               class="btn btn-sm btn-outline-primary me-1" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button class="btn btn-sm btn-outline-danger"
                                    onclick="confirmDelete({{ $hh->id }}, '{{ addslashes($hh->household_head) }}')"
                                    title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No households found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($households->hasPages())
    <div class="card-footer">
        {{ $households->links() }}
    </div>
    @endif
</div>

{{-- Delete modal --}}
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Delete Household</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Delete household of <strong id="delName"></strong>?</p>
                <p class="text-muted small mb-0">All family members will also be removed and population stats will be recomputed.</p>
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
var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
function confirmDelete(id, name) {
    document.getElementById('delName').textContent = name;
    document.getElementById('deleteForm').action = '/households/' + id;
    deleteModal.show();
}
</script>
@endpush
