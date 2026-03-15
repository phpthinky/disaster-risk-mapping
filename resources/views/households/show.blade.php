@extends('layouts.app')

@section('title', $household->household_head . ' — Household Profile')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="page-title mb-0">{{ $household->household_head }}</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('households.index') }}">Households</a></li>
                <li class="breadcrumb-item active">Profile</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2">
        @if(!auth()->user()->isDivisionChief())
        <a href="{{ route('households.edit', $household) }}" class="btn btn-primary btn-sm">
            <i class="fas fa-edit me-1"></i> Edit
        </a>
        @endif
        <a href="{{ route('households.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>
</div>
@endsection

@section('content')
<div class="row g-4">

    {{-- LEFT --}}
    <div class="col-lg-4">

        {{-- Household info --}}
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-house-chimney-user me-2"></i>Household Information
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td class="text-muted">HH ID</td>
                        <td class="fw-semibold">{{ $household->hh_id ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Barangay</td>
                        <td class="fw-semibold">{{ $household->barangay->name ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Zone / Sitio</td>
                        <td>{{ $household->sitio_purok_zone ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Head of Household</td>
                        <td class="fw-semibold">{{ $household->household_head }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Sex</td>
                        <td>{{ $household->sex }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Age</td>
                        <td>{{ $household->age }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Birthday</td>
                        <td>{{ $household->birthday ? $household->birthday->format('F j, Y') : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">House Type</td>
                        <td>{{ $household->house_type ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">IP / Non-IP</td>
                        <td>
                            @if($household->ip_non_ip === 'IP')
                                <span class="badge" style="background:#7c3aed;">IP Member</span>
                            @else
                                {{ $household->ip_non_ip ?? '—' }}
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Preparedness Kit</td>
                        <td>
                            @if($household->preparedness_kit)
                                <span class="badge bg-success">Yes</span>
                            @else
                                <span class="badge bg-secondary">No</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted">Education</td>
                        <td>{{ $household->educational_attainment ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">GPS</td>
                        <td style="font-size:.8rem;">
                            @if($household->hasValidGps())
                                <span class="text-success">
                                    <i class="fas fa-map-marker-alt me-1"></i>
                                    {{ $household->latitude }}, {{ $household->longitude }}
                                </span>
                            @else
                                <span class="text-danger"><i class="fas fa-exclamation-circle me-1"></i>No GPS</span>
                            @endif
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        {{-- Demographic summary --}}
        <div class="card">
            <div class="card-header"><i class="fas fa-users me-2"></i>Demographic Summary</div>
            <div class="card-body">
                <div class="row g-2 text-center">
                    <div class="col-6">
                        <div class="fw-bold fs-4 text-primary">{{ $household->family_members }}</div>
                        <div class="text-muted small">Total Members</div>
                    </div>
                    <div class="col-6">
                        <div class="fw-bold fs-5 text-danger">{{ $household->pwd_count }}</div>
                        <div class="text-muted small">PWD</div>
                    </div>
                    <div class="col-6">
                        <div class="fw-bold fs-5 text-warning">{{ $household->senior_count }}</div>
                        <div class="text-muted small">Seniors (60+)</div>
                    </div>
                    <div class="col-6">
                        <div class="fw-bold fs-5 text-info">{{ $household->minor_count + $household->child_count }}</div>
                        <div class="text-muted small">Children</div>
                    </div>
                    <div class="col-6">
                        <div class="fw-bold fs-5 text-secondary">{{ $household->infant_count }}</div>
                        <div class="text-muted small">Infants (0–2)</div>
                    </div>
                    <div class="col-6">
                        <div class="fw-bold fs-5" style="color:#9b59b6;">{{ $household->pregnant_count }}</div>
                        <div class="text-muted small">Pregnant</div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- RIGHT --}}
    <div class="col-lg-8">

        {{-- GPS mini-map --}}
        @if($household->hasValidGps())
        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-map-marker-alt me-2"></i>Location</div>
            <div class="card-body p-0" style="border-radius:0 0 14px 14px; overflow:hidden;">
                <div id="hhMap" style="height:240px;"></div>
            </div>
        </div>
        @endif

        {{-- Family members --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-users me-2"></i>Family Members
                    <span class="badge bg-secondary ms-1" id="memberCount">{{ $household->members->count() }}</span>
                </span>
                @if(!auth()->user()->isDivisionChief())
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                    <i class="fas fa-user-plus me-1"></i> Add Member
                </button>
                @endif
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0" id="membersTable">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th class="text-center">Age</th>
                                <th>Birthday</th>
                                <th>Sex</th>
                                <th>Relation</th>
                                <th class="text-center">PWD</th>
                                <th class="text-center">Pregnant</th>
                                <th class="text-center">IP</th>
                                @if(!auth()->user()->isDivisionChief())
                                <th class="text-center">Action</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody id="membersTbody">
                            @forelse($household->members as $m)
                            <tr id="member-row-{{ $m->id }}">
                                <td class="fw-semibold">{{ $m->name }}</td>
                                <td class="text-center">{{ $m->age }}</td>
                                <td class="small">{{ $m->birthday ? $m->birthday->format('M j, Y') : '—' }}</td>
                                <td>{{ $m->sex }}</td>
                                <td class="small text-muted">{{ $m->relation ?? '—' }}</td>
                                <td class="text-center">
                                    @if($m->is_pwd) <i class="fas fa-check text-danger"></i> @else <span class="text-muted">—</span> @endif
                                </td>
                                <td class="text-center">
                                    @if($m->is_pregnant) <i class="fas fa-check text-info"></i> @else <span class="text-muted">—</span> @endif
                                </td>
                                <td class="text-center">
                                    @if($m->is_ip) <i class="fas fa-check" style="color:#7c3aed;"></i> @else <span class="text-muted">—</span> @endif
                                </td>
                                @if(!auth()->user()->isDivisionChief())
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-danger"
                                            onclick="deleteMember({{ $m->id }})" title="Remove">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                                @endif
                            </tr>
                            @empty
                            <tr id="emptyRow">
                                <td colspan="9" class="text-center text-muted py-3">No members recorded yet.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- Add Member Modal --}}
@if(!auth()->user()->isDivisionChief())
<div class="modal fade" id="addMemberModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Add Family Member</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="memberFormAlert" class="alert d-none"></div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" id="mName" class="form-control" placeholder="Member name">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Age <span class="text-danger">*</span></label>
                        <input type="number" id="mAge" class="form-control" min="0" max="130">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Birthday</label>
                        <input type="date" id="mBirthday" class="form-control">
                        <div class="form-text">Setting a birthday auto-calculates age. Age only fills birthday when blank.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Sex <span class="text-danger">*</span></label>
                        <select id="mSex" class="form-select">
                            <option value="">— Select —</option>
                            <option>Male</option>
                            <option>Female</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Relation to Head</label>
                        <input type="text" id="mRelation" class="form-control" placeholder="e.g. Spouse, Child">
                    </div>
                    <div class="col-12 d-flex gap-4">
                        <div class="form-check">
                            <input type="checkbox" id="mPwd" class="form-check-input">
                            <label for="mPwd" class="form-check-label">PWD</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" id="mPregnant" class="form-check-input">
                            <label for="mPregnant" class="form-check-label">Pregnant</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" id="mIp" class="form-check-input">
                            <label for="mIp" class="form-check-label">IP Member</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="saveMemberBtn">
                    <i class="fas fa-save me-1"></i> Save Member
                </button>
            </div>
        </div>
    </div>
</div>
@endif

@endsection

@push('scripts')
@if($household->hasValidGps())
<script>
(function () {
    var map = L.map('hhMap', { zoomControl: true, attributionControl: false });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18 }).addTo(map);

    var lat = {{ $household->latitude }};
    var lng = {{ $household->longitude }};
    var marker = L.marker([lat, lng]).addTo(map);
    marker.bindPopup('<strong>{{ addslashes($household->household_head) }}</strong><br>{{ $household->barangay->name ?? "" }}').openPopup();
    map.setView([lat, lng], 15);
})();
</script>
@endif

@if(!auth()->user()->isDivisionChief())
<script>
var csrfToken = document.querySelector('meta[name=csrf-token]').content;
var householdId = {{ $household->id }};

// ── Age ↔ Birthday sync (modal) ───────────────────────────────────────────────
(function () {
    var ageEl  = document.getElementById('mAge');
    var bdayEl = document.getElementById('mBirthday');

    function calcAgeFromDate(dateStr) {
        var d = new Date(dateStr);
        if (isNaN(d)) return null;
        var today = new Date();
        var age = today.getFullYear() - d.getFullYear();
        if (today.getMonth() < d.getMonth() ||
            (today.getMonth() === d.getMonth() && today.getDate() < d.getDate())) {
            age--;
        }
        return Math.max(0, age);
    }

    bdayEl.addEventListener('change', function () {
        if (!this.value) return;
        var age = calcAgeFromDate(this.value);
        if (age !== null) ageEl.value = age;
    });

    ageEl.addEventListener('change', function () {
        // If birthday is already set, do not overwrite it
        if (bdayEl.value) return;
        var age = parseInt(this.value);
        if (isNaN(age) || age < 0) return;
        var year = new Date().getFullYear() - age;
        bdayEl.value = year + '-01-01';
    });

    // Reset sync state when modal is closed
    document.getElementById('addMemberModal').addEventListener('hidden.bs.modal', function () {
        bdayEl.value = '';
        ageEl.value  = '';
    });
})();

// Add member
document.getElementById('saveMemberBtn').addEventListener('click', function () {
    var btn = this;
    var alertEl = document.getElementById('memberFormAlert');

    var name     = document.getElementById('mName').value.trim();
    var age      = document.getElementById('mAge').value;
    var birthday = document.getElementById('mBirthday').value || null;
    var sex      = document.getElementById('mSex').value;
    var relation = document.getElementById('mRelation').value.trim();
    var is_pwd      = document.getElementById('mPwd').checked ? 1 : 0;
    var is_pregnant = document.getElementById('mPregnant').checked ? 1 : 0;
    var is_ip       = document.getElementById('mIp').checked ? 1 : 0;

    if (!name || !age || !sex) {
        alertEl.className = 'alert alert-warning';
        alertEl.textContent = 'Name, Age and Sex are required.';
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';

    fetch('/api/households/' + householdId + '/members', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ name, age, birthday, sex, relation, is_pwd, is_pregnant, is_ip })
    })
    .then(r => r.json())
    .then(function (res) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save me-1"></i> Save Member';

        if (!res.ok) {
            alertEl.className = 'alert alert-danger';
            alertEl.textContent = res.message || 'Error saving member.';
            return;
        }

        alertEl.className = 'alert d-none';

        // Clear form
        document.getElementById('mName').value = '';
        document.getElementById('mAge').value = '';
        document.getElementById('mBirthday').value = '';
        document.getElementById('mSex').value = '';
        document.getElementById('mRelation').value = '';
        document.getElementById('mPwd').checked = false;
        document.getElementById('mPregnant').checked = false;
        document.getElementById('mIp').checked = false;

        // Add row to table
        var m = res.member;
        var tbody = document.getElementById('membersTbody');
        var emptyRow = document.getElementById('emptyRow');
        if (emptyRow) emptyRow.remove();

        var tr = document.createElement('tr');
        tr.id = 'member-row-' + m.id;
        var bdayDisplay = '—';
        if (m.birthday) {
            var bd = new Date(m.birthday);
            bdayDisplay = bd.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        }
        tr.innerHTML =
            '<td class="fw-semibold">' + m.name + '</td>' +
            '<td class="text-center">' + m.age + '</td>' +
            '<td class="small">' + bdayDisplay + '</td>' +
            '<td>' + m.sex + '</td>' +
            '<td class="small text-muted">' + (m.relation || '—') + '</td>' +
            '<td class="text-center">' + (m.is_pwd ? '<i class="fas fa-check text-danger"></i>' : '<span class="text-muted">—</span>') + '</td>' +
            '<td class="text-center">' + (m.is_pregnant ? '<i class="fas fa-check text-info"></i>' : '<span class="text-muted">—</span>') + '</td>' +
            '<td class="text-center">' + (m.is_ip ? '<i class="fas fa-check" style="color:#7c3aed;"></i>' : '<span class="text-muted">—</span>') + '</td>' +
            '<td class="text-center"><button class="btn btn-sm btn-outline-danger" onclick="deleteMember(' + m.id + ')" title="Remove"><i class="fas fa-trash"></i></button></td>';
        tbody.appendChild(tr);

        // Update count badge
        var badge = document.getElementById('memberCount');
        badge.textContent = parseInt(badge.textContent) + 1;

        // Close modal
        var modalEl = document.getElementById('addMemberModal');
        var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        modal.hide();
        // Ensure backdrop is fully removed
        document.querySelectorAll('.modal-backdrop').forEach(function (el) { el.remove(); });
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');
    })
    .catch(function () {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save me-1"></i> Save Member';
        alertEl.className = 'alert alert-danger';
        alertEl.textContent = 'Network error. Please try again.';
    });
});

// Delete member
function deleteMember(id) {
    if (!confirm('Remove this family member?')) return;

    fetch('/api/household-members/' + id, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrfToken }
    })
    .then(r => r.json())
    .then(function (res) {
        if (res.ok) {
            var row = document.getElementById('member-row-' + id);
            if (row) row.remove();
            var badge = document.getElementById('memberCount');
            var newCount = Math.max(0, parseInt(badge.textContent) - 1);
            badge.textContent = newCount;
            if (newCount === 0) {
                var tbody = document.getElementById('membersTbody');
                tbody.innerHTML = '<tr id="emptyRow"><td colspan="9" class="text-center text-muted py-3">No members recorded yet.</td></tr>';
            }
        } else {
            alert(res.msg || 'Could not remove member.');
        }
    });
}
</script>
@endif
@endpush
