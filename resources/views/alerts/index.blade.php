@extends('layouts.app')

@section('title', 'System Alerts')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="page-title mb-0"><i class="fas fa-triangle-exclamation me-2"></i>System Alerts</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item active">Alerts</li>
            </ol>
        </nav>
    </div>
    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#alertModal">
        <i class="fas fa-plus me-1"></i> New Alert
    </button>
</div>
@endsection

@section('content')

{{-- Active Alerts Banner --}}
@php $activeCount = $alerts->where('is_active', true)->count(); @endphp
@if($activeCount > 0)
<div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
    <i class="fas fa-circle-exclamation fa-lg flex-shrink-0"></i>
    <div>
        <strong>{{ $activeCount }} active alert{{ $activeCount > 1 ? 's' : '' }}</strong>
        currently visible to all users in the navbar.
    </div>
</div>
@endif

{{-- Alerts List --}}
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list me-2"></i>All Alerts</span>
        <span class="badge bg-secondary">{{ $alerts->total() }} total</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Scope</th>
                        <th class="text-center">Status</th>
                        <th class="text-muted small">Created</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($alerts as $alert)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $alert->title }}</div>
                            @if($alert->message)
                            <div class="text-muted small text-truncate" style="max-width:320px;">
                                {{ $alert->message }}
                            </div>
                            @endif
                        </td>
                        <td>
                            @php
                                $typeClass = match($alert->alert_type) {
                                    'danger'  => 'danger',
                                    'warning' => 'warning',
                                    default   => 'info',
                                };
                            @endphp
                            <span class="badge bg-{{ $typeClass }}">{{ ucfirst($alert->alert_type) }}</span>
                        </td>
                        <td>
                            @if($alert->barangay)
                                <span class="badge bg-secondary">{{ $alert->barangay->name }}</span>
                            @else
                                <span class="badge bg-primary">Municipality-wide</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <form method="POST" action="{{ route('alerts.toggle', $alert) }}" class="d-inline">
                                @csrf @method('PATCH')
                                <button type="submit"
                                        class="badge border-0 bg-{{ $alert->is_active ? 'success' : 'secondary' }} py-1 px-2"
                                        title="Click to toggle">
                                    {{ $alert->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </form>
                        </td>
                        <td class="small text-muted">{{ $alert->created_at->format('M j, Y') }}</td>
                        <td class="text-end text-nowrap">
                            <button class="btn btn-sm btn-outline-primary"
                                    onclick="editAlert({{ $alert->id }}, '{{ addslashes($alert->title) }}', '{{ addslashes($alert->message ?? '') }}', '{{ $alert->alert_type }}', '{{ $alert->barangay_id }}', {{ $alert->is_active ? 'true' : 'false' }})"
                                    title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" action="{{ route('alerts.destroy', $alert) }}" class="d-inline"
                                  onsubmit="return confirm('Delete this alert?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="fas fa-shield-halved fa-2x d-block mb-2 opacity-50"></i>
                            No alerts yet. Create one to notify all users.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($alerts->hasPages())
    <div class="card-footer">{{ $alerts->links() }}</div>
    @endif
</div>

{{-- Create / Edit Modal --}}
<div class="modal fade" id="alertModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="alertModalTitle">
                    <i class="fas fa-triangle-exclamation me-2"></i>New Alert
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="alertForm" action="{{ route('alerts.store') }}">
                @csrf
                <span id="alertMethodField"></span>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="alertTitle" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea name="message" id="alertMessage" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Alert Type <span class="text-danger">*</span></label>
                            <select name="alert_type" id="alertType" class="form-select" required>
                                <option value="danger">Danger</option>
                                <option value="warning">Warning</option>
                                <option value="info">Info</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Scope</label>
                            <select name="barangay_id" id="alertBarangay" class="form-select">
                                <option value="">Municipality-wide</option>
                                @foreach($barangays as $b)
                                <option value="{{ $b->id }}">{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-check mt-3">
                        <input type="checkbox" name="is_active" id="alertActive" class="form-check-input" value="1" checked>
                        <label class="form-check-label" for="alertActive">Activate immediately</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="alertSubmitBtn">
                        <i class="fas fa-save me-1"></i>Create Alert
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function editAlert(id, title, message, type, barangayId, isActive) {
    document.getElementById('alertModalTitle').innerHTML =
        '<i class="fas fa-edit me-2"></i>Edit Alert';
    document.getElementById('alertForm').action = '/alerts/' + id;
    document.getElementById('alertMethodField').innerHTML =
        '<input type="hidden" name="_method" value="PUT">';
    document.getElementById('alertTitle').value   = title;
    document.getElementById('alertMessage').value = message;
    document.getElementById('alertType').value    = type;
    document.getElementById('alertBarangay').value = barangayId || '';
    document.getElementById('alertActive').checked = isActive;
    document.getElementById('alertSubmitBtn').textContent = 'Update Alert';
    new bootstrap.Modal(document.getElementById('alertModal')).show();
}
// Reset modal on close
document.getElementById('alertModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('alertForm').reset();
    document.getElementById('alertForm').action = '{{ route("alerts.store") }}';
    document.getElementById('alertMethodField').innerHTML = '';
    document.getElementById('alertModalTitle').innerHTML =
        '<i class="fas fa-triangle-exclamation me-2"></i>New Alert';
    document.getElementById('alertSubmitBtn').textContent = 'Create Alert';
    document.getElementById('alertActive').checked = true;
});
</script>
@endpush
