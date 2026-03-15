@extends('layouts.app')

@section('title', 'Household Data Export')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="page-title mb-0"><i class="fas fa-house-chimney-user me-2"></i>Household Data Export</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
                <li class="breadcrumb-item active">Households</li>
            </ol>
        </nav>
    </div>
    <div>
        <a href="{{ route('reports.excel', 'households') }}?{{ http_build_query(request()->all()) }}" class="btn btn-success btn-sm">
            <i class="fas fa-file-excel me-1"></i> Export Excel
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
                <select name="ip" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">IP &amp; Non-IP</option>
                    <option value="IP" {{ request('ip') === 'IP' ? 'selected' : '' }}>IP only</option>
                    <option value="Non-IP" {{ request('ip') === 'Non-IP' ? 'selected' : '' }}>Non-IP only</option>
                </select>
            </div>
            <div class="col-auto">
                <select name="gps" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All (GPS optional)</option>
                    <option value="1" {{ request('gps') === '1' ? 'selected' : '' }}>Has GPS only</option>
                </select>
            </div>
            @if(request()->hasAny(['barangay_id','ip','gps']))
            <div class="col-auto">
                <a href="{{ route('reports.households') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
            </div>
            @endif
            <div class="col-auto ms-auto text-muted small">
                {{ $households->total() }} record(s)
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Barangay</th>
                        <th>Household Head</th>
                        <th>Zone / Sitio</th>
                        <th>IP/Non-IP</th>
                        <th class="text-center">Members</th>
                        <th class="text-center">PWD</th>
                        <th class="text-center">Senior</th>
                        <th class="text-center">Infant</th>
                        <th class="text-center">Pregnant</th>
                        <th class="text-center">GPS</th>
                        <th>Registered</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($households as $h)
                    <tr>
                        <td class="fw-medium">{{ $h->barangay->name ?? '—' }}</td>
                        <td>{{ $h->household_head }}</td>
                        <td>{{ $h->sitio_purok_zone ?? '—' }}</td>
                        <td>
                            @if($h->ip_non_ip === 'IP')
                                <span class="badge bg-info bg-opacity-15 text-info">IP</span>
                            @else
                                <span class="text-muted small">Non-IP</span>
                            @endif
                        </td>
                        <td class="text-center">{{ $h->family_members }}</td>
                        <td class="text-center">{{ $h->pwd_count ?: '—' }}</td>
                        <td class="text-center">{{ $h->senior_count ?: '—' }}</td>
                        <td class="text-center">{{ $h->infant_count ?: '—' }}</td>
                        <td class="text-center">{{ $h->pregnant_count ?: '—' }}</td>
                        <td class="text-center">
                            @if($h->hasValidGps())
                                <i class="fas fa-location-dot text-success" title="{{ $h->latitude }}, {{ $h->longitude }}"></i>
                            @else
                                <i class="fas fa-location-dot text-muted"></i>
                            @endif
                        </td>
                        <td class="text-muted small">{{ $h->created_at?->format('M d, Y') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="11" class="text-center text-muted py-4">No households found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($households->hasPages())
    <div class="card-footer bg-transparent">
        {{ $households->links() }}
    </div>
    @endif
</div>

@endsection
