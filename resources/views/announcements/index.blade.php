@extends('layouts.app')

@section('title', 'Announcements')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="page-title mb-0"><i class="fas fa-bullhorn me-2"></i>Announcements</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item active">Announcements</li>
            </ol>
        </nav>
    </div>
    @if($canWrite)
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#announcementModal">
        <i class="fas fa-plus me-1"></i> Post Announcement
    </button>
    @endif
</div>
@endsection

@section('content')

{{-- List --}}
@forelse($announcements as $ann)
@php
    $borderColor = \App\Models\Announcement::typeBorderColor($ann->announcement_type);
    $badgeClass  = \App\Models\Announcement::typeBadgeClass($ann->announcement_type);
    $icon        = \App\Models\Announcement::typeIcon($ann->announcement_type);
@endphp
<div class="card mb-3 shadow-sm {{ $ann->is_active ? '' : 'opacity-60' }}"
     style="border-left: 4px solid {{ $borderColor }};">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
            <div class="flex-grow-1">
                <h6 class="fw-bold mb-1">
                    <i class="fas {{ $icon }} me-2" style="color:{{ $borderColor }};"></i>
                    {{ $ann->title }}
                </h6>
                <p class="mb-2 text-secondary" style="white-space:pre-wrap;">{{ $ann->message }}</p>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="badge bg-{{ $badgeClass }} {{ $ann->announcement_type === 'maintenance' ? 'text-dark' : '' }}">
                        {{ ucfirst($ann->announcement_type) }}
                    </span>
                    <span class="badge bg-primary">
                        <i class="fas fa-users me-1"></i>
                        {{ match($ann->target_audience) {
                            'all'            => 'All Users',
                            'barangay_staff' => 'Barangay Staff',
                            'division_chief' => 'Division Chiefs',
                            'admin'          => 'Administrators',
                            default          => $ann->target_audience,
                        } }}
                    </span>
                    <span class="badge bg-{{ $ann->is_active ? 'success' : 'secondary' }}">
                        {{ $ann->is_active ? 'Active' : 'Inactive' }}
                    </span>
                    <span class="text-muted small">
                        By {{ $ann->creator?->username ?? '—' }} &middot;
                        {{ $ann->created_at->format('M j, Y g:i A') }}
                    </span>
                </div>
            </div>
            @if($canWrite)
            <div class="d-flex gap-1 flex-shrink-0">
                <form method="POST" action="{{ route('announcements.toggle', $ann) }}">
                    @csrf @method('PATCH')
                    <button type="submit"
                            class="btn btn-sm btn-outline-{{ $ann->is_active ? 'warning' : 'success' }}"
                            title="{{ $ann->is_active ? 'Deactivate' : 'Activate' }}">
                        <i class="fas fa-{{ $ann->is_active ? 'pause' : 'play' }}"></i>
                    </button>
                </form>
                <button class="btn btn-sm btn-outline-primary"
                        onclick="editAnnouncement({{ $ann->id }}, {{ json_encode($ann->title) }}, {{ json_encode($ann->message) }}, '{{ $ann->announcement_type }}', '{{ $ann->target_audience }}', {{ $ann->is_active ? 'true' : 'false' }})"
                        title="Edit">
                    <i class="fas fa-edit"></i>
                </button>
                <form method="POST" action="{{ route('announcements.destroy', $ann) }}" class="d-inline"
                      onsubmit="return confirm('Delete this announcement?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </div>
            @endif
        </div>
    </div>
</div>
@empty
<div class="card shadow-sm">
    <div class="card-body text-center text-muted py-5">
        <i class="fas fa-bullhorn fa-2x d-block mb-2 opacity-50"></i>
        No announcements yet.
        @if($canWrite)
        <div class="mt-2">
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#announcementModal">
                Post the first one
            </button>
        </div>
        @endif
    </div>
</div>
@endforelse

@if($announcements->hasPages())
<div class="mt-3">{{ $announcements->links() }}</div>
@endif

{{-- Create / Edit Modal (only rendered for writers) --}}
@if($canWrite)
<div class="modal fade" id="announcementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="annModalTitle">
                    <i class="fas fa-bullhorn me-2"></i>Post Announcement
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="annForm" action="{{ route('announcements.store') }}">
                @csrf
                <span id="annMethodField"></span>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="annTitle" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message <span class="text-danger">*</span></label>
                        <textarea name="message" id="annMessage" class="form-control" rows="5" required></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Type <span class="text-danger">*</span></label>
                            <select name="announcement_type" id="annType" class="form-select" required>
                                <option value="general">General</option>
                                <option value="info">Information</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="emergency">Emergency</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Target Audience <span class="text-danger">*</span></label>
                            <select name="target_audience" id="annAudience" class="form-select" required>
                                <option value="all">All Users</option>
                                <option value="barangay_staff">Barangay Staff Only</option>
                                <option value="division_chief">Division Chiefs Only</option>
                                <option value="admin">Administrators Only</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-check mt-3">
                        <input type="checkbox" name="is_active" id="annActive" class="form-check-input" value="1" checked>
                        <label class="form-check-label" for="annActive">Activate immediately</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="annSubmitBtn">
                        <i class="fas fa-bullhorn me-1"></i>Post Announcement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function editAnnouncement(id, title, message, type, audience, isActive) {
    document.getElementById('annModalTitle').innerHTML =
        '<i class="fas fa-edit me-2"></i>Edit Announcement';
    document.getElementById('annForm').action = '/announcements/' + id;
    document.getElementById('annMethodField').innerHTML =
        '<input type="hidden" name="_method" value="PUT">';
    document.getElementById('annTitle').value    = title;
    document.getElementById('annMessage').value  = message;
    document.getElementById('annType').value     = type;
    document.getElementById('annAudience').value = audience;
    document.getElementById('annActive').checked = isActive;
    document.getElementById('annSubmitBtn').innerHTML =
        '<i class="fas fa-save me-1"></i>Update Announcement';
    new bootstrap.Modal(document.getElementById('announcementModal')).show();
}
document.getElementById('announcementModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('annForm').reset();
    document.getElementById('annForm').action = '{{ route("announcements.store") }}';
    document.getElementById('annMethodField').innerHTML = '';
    document.getElementById('annModalTitle').innerHTML =
        '<i class="fas fa-bullhorn me-2"></i>Post Announcement';
    document.getElementById('annSubmitBtn').innerHTML =
        '<i class="fas fa-bullhorn me-1"></i>Post Announcement';
    document.getElementById('annActive').checked = true;
});
</script>
@endpush
@endif

@endsection
