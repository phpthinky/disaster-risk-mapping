@extends('layouts.app')

@section('title', 'Incident Summary Report')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="page-title mb-0"><i class="fas fa-file-circle-exclamation me-2"></i>Incident Summary</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
                <li class="breadcrumb-item active">Incident Summary</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('reports.excel', 'incidents') }}?{{ http_build_query(request()->all()) }}" class="btn btn-success btn-sm">
            <i class="fas fa-file-excel me-1"></i> Export Excel
        </a>
        <a href="{{ route('reports.pdf', 'incidents') }}?{{ http_build_query(request()->all()) }}" class="btn btn-danger btn-sm">
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
            <div class="col-auto">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="ongoing"    {{ request('status') === 'ongoing'    ? 'selected' : '' }}>Ongoing</option>
                    <option value="monitoring" {{ request('status') === 'monitoring' ? 'selected' : '' }}>Monitoring</option>
                    <option value="resolved"   {{ request('status') === 'resolved'   ? 'selected' : '' }}>Resolved</option>
                </select>
            </div>
            <div class="col-auto">
                <select name="hazard_type_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Hazard Types</option>
                    @foreach($hazardTypes as $ht)
                        <option value="{{ $ht->id }}" {{ request('hazard_type_id') == $ht->id ? 'selected' : '' }}>{{ $ht->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}" placeholder="From date">
            </div>
            <div class="col-auto">
                <input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}" placeholder="To date">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            </div>
            @if(request()->hasAny(['status','hazard_type_id','from','to']))
            <div class="col-auto">
                <a href="{{ route('reports.incidents') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
            </div>
            @endif
            <div class="col-auto ms-auto text-muted small">
                {{ $incidents->total() }} record(s)
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Title</th>
                        <th>Hazard Type</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th class="text-end">Barangays</th>
                        <th class="text-end">Households</th>
                        <th class="text-end">Population</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($incidents as $inc)
                    @php
                        $badgeMap = ['ongoing' => 'danger', 'monitoring' => 'warning', 'resolved' => 'success'];
                    @endphp
                    <tr>
                        <td class="fw-medium">{{ $inc->title }}</td>
                        <td>{{ $inc->hazardType->name ?? '—' }}</td>
                        <td>
                            <span class="badge bg-{{ $badgeMap[$inc->status] ?? 'secondary' }}">
                                {{ ucfirst($inc->status) }}
                            </span>
                        </td>
                        <td>{{ $inc->incident_date?->format('M d, Y') }}</td>
                        <td class="text-end">{{ $inc->affectedAreas->count() }}</td>
                        <td class="text-end">{{ number_format($inc->affectedAreas->sum('affected_households')) }}</td>
                        <td class="text-end">{{ number_format($inc->affectedAreas->sum('affected_population')) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No incidents match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($incidents->hasPages())
    <div class="card-footer bg-transparent">
        {{ $incidents->links() }}
    </div>
    @endif
</div>

@endsection
