@extends('layouts.app')

@section('title', 'Evacuation Centers')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="page-title mb-0">Evacuation Centers</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item active">Evacuation Centers</li>
            </ol>
        </nav>
    </div>
    @if(!auth()->user()->isDivisionChief())
    <a href="{{ route('evacuations.create') }}" class="btn btn-primary btn-sm">
        <i class="fas fa-plus me-1"></i> Add Center
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
                <div class="stat-icon bg-primary bg-opacity-10 text-primary rounded-3 p-3">
                    <i class="fas fa-house-medical-flag fa-lg"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4">{{ number_format($stats['total']) }}</div>
                    <div class="text-muted small">Total Centers</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success rounded-3 p-3">
                    <i class="fas fa-check-circle fa-lg"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4">{{ number_format($stats['operational']) }}</div>
                    <div class="text-muted small">Operational</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-info bg-opacity-10 text-info rounded-3 p-3">
                    <i class="fas fa-users fa-lg"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4">{{ number_format($stats['capacity']) }}</div>
                    <div class="text-muted small">Total Capacity</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning rounded-3 p-3">
                    <i class="fas fa-person-shelter fa-lg"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4">{{ number_format($stats['occupancy']) }}</div>
                    <div class="text-muted small">Current Occupancy</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Map --}}
<div class="card mb-4">
    <div class="card-header"><i class="fas fa-map me-2"></i>Evacuation Centers Map</div>
    <div class="card-body p-0">
        <div id="evacMap" style="height:380px;"></div>
    </div>
</div>

{{-- Filters --}}
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            @if(!auth()->user()->isBarangayStaff())
            <div class="col-md-4">
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
            <div class="col-md-3">
                <label class="form-label small text-muted">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="operational" {{ request('status') === 'operational' ? 'selected' : '' }}>Operational</option>
                    <option value="maintenance" {{ request('status') === 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                    <option value="closed"      {{ request('status') === 'closed'      ? 'selected' : '' }}>Closed</option>
                </select>
            </div>
            <div class="col-md-auto d-flex gap-2">
                <button class="btn btn-sm btn-primary"><i class="fas fa-filter me-1"></i>Filter</button>
                <a href="{{ route('evacuations.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

{{-- Table --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list me-2"></i>Centers
            <span class="badge bg-secondary ms-1">{{ $centers->total() }}</span>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        @if(!auth()->user()->isBarangayStaff())
                        <th>Barangay</th>
                        @endif
                        <th>Status</th>
                        <th class="text-end">Capacity</th>
                        <th class="text-end">Occupancy</th>
                        <th class="text-center">GPS</th>
                        <th>Contact</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($centers as $center)
                    @php
                        $rate = $center->capacity > 0 ? ($center->current_occupancy / $center->capacity) * 100 : 0;
                        $barColor = $rate >= 80 ? 'danger' : ($rate >= 50 ? 'warning' : 'success');
                        $statusColor = match($center->status) {
                            'operational' => 'success',
                            'maintenance' => 'warning',
                            'closed'      => 'danger',
                            default       => 'secondary',
                        };
                    @endphp
                    <tr>
                        <td class="fw-medium">{{ $center->name }}</td>
                        @if(!auth()->user()->isBarangayStaff())
                        <td>{{ $center->barangay->name ?? '—' }}</td>
                        @endif
                        <td>
                            <span class="badge bg-{{ $statusColor }}">{{ ucfirst($center->status) }}</span>
                        </td>
                        <td class="text-end">{{ number_format($center->capacity) }}</td>
                        <td class="text-end">
                            <div>{{ number_format($center->current_occupancy) }}</div>
                            <div class="progress mt-1" style="height:4px;width:70px;margin-left:auto;">
                                <div class="progress-bar bg-{{ $barColor }}" style="width:{{ min(100, $rate) }}%"></div>
                            </div>
                        </td>
                        <td class="text-center">
                            @if($center->latitude && $center->longitude)
                                <span class="badge bg-success"><i class="fas fa-map-marker-alt me-1"></i>Set</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td class="small text-muted">
                            @if($center->contact_person)
                                <div>{{ $center->contact_person }}</div>
                            @endif
                            @if($center->contact_number)
                                <div>{{ $center->contact_number }}</div>
                            @endif
                            @if(!$center->contact_person && !$center->contact_number)
                                —
                            @endif
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('evacuations.show', $center) }}" class="btn btn-outline-primary" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                @if(!auth()->user()->isDivisionChief())
                                <a href="{{ route('evacuations.edit', $center) }}" class="btn btn-outline-secondary" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                @if(auth()->user()->isAdmin())
                                <button class="btn btn-outline-danger" title="Delete"
                                    onclick="confirmDelete({{ $center->id }}, '{{ addslashes($center->name) }}')">
                                    <i class="fas fa-trash"></i>
                                </button>
                                @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No evacuation centers found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($centers->hasPages())
    <div class="card-footer">{{ $centers->links() }}</div>
    @endif
</div>

{{-- Delete Modal --}}
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
@endsection

@push('scripts')
<script>
function confirmDelete(id, name) {
    document.getElementById('deleteCenterName').textContent = name;
    document.getElementById('deleteForm').action = '/evacuations/' + id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// ── Evacuation Centers Map ────────────────────────────────────────────────
(function () {
    var street = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '© OpenStreetMap contributors'
    });
    var map = L.map('evacMap', { center: [12.835, 120.82], zoom: 11, layers: [street] });

    var greenIcon = L.divIcon({
        className: '',
        html: '<div style="background:#198754;color:#fff;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(0,0,0,.35);"><i class="fas fa-house-medical-flag" style="font-size:12px;"></i></div>',
        iconSize: [28, 28], iconAnchor: [14, 14], popupAnchor: [0, -16]
    });
    var warnIcon = L.divIcon({
        className: '',
        html: '<div style="background:#ffc107;color:#212529;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(0,0,0,.35);"><i class="fas fa-house-medical-flag" style="font-size:12px;"></i></div>',
        iconSize: [28, 28], iconAnchor: [14, 14], popupAnchor: [0, -16]
    });
    var redIcon = L.divIcon({
        className: '',
        html: '<div style="background:#dc3545;color:#fff;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(0,0,0,.35);"><i class="fas fa-house-medical-flag" style="font-size:12px;"></i></div>',
        iconSize: [28, 28], iconAnchor: [14, 14], popupAnchor: [0, -16]
    });

    fetch('{{ route("api.map.evacuation-centers") }}')
        .then(r => r.json())
        .then(data => {
            if (!data.length) return;
            var bounds = [];
            data.forEach(function (c) {
                var icon = c.status === 'operational' ? greenIcon : (c.status === 'maintenance' ? warnIcon : redIcon);
                var rate = c.capacity > 0 ? Math.round((c.current_occupancy / c.capacity) * 100) : 0;
                var marker = L.marker([c.latitude, c.longitude], { icon: icon })
                    .bindPopup(
                        '<strong>' + c.name + '</strong><br>' +
                        '<small class="text-muted">' + (c.barangay || '') + '</small><br>' +
                        'Status: <span class="badge bg-' + (c.status === 'operational' ? 'success' : (c.status === 'maintenance' ? 'warning' : 'danger')) + '">' + c.status + '</span><br>' +
                        'Capacity: ' + c.capacity + ' | Occupancy: ' + c.current_occupancy + ' (' + rate + '%)<br>' +
                        '<a href="/evacuations/' + c.id + '" class="btn btn-xs btn-primary mt-1" style="font-size:11px;padding:2px 6px;">View</a>'
                    );
                marker.addTo(map);
                bounds.push([c.latitude, c.longitude]);
            });
            if (bounds.length) map.fitBounds(bounds, { padding: [30, 30] });
        });
})();
</script>
@endpush
