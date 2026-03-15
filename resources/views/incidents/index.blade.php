@extends('layouts.app')

@section('title', 'Incident Reports')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="page-title mb-0"><i class="fas fa-file-circle-exclamation me-2"></i>Incident Reports</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item active">Incident Reports</li>
            </ol>
        </nav>
    </div>
    @if(!auth()->user()->isDivisionChief())
    <div>
        <a href="{{ route('incidents.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> New Incident
        </a>
    </div>
    @endif
</div>
@endsection

@section('content')

{{-- Stat cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 p-3 bg-primary bg-opacity-10 text-primary">
                    <i class="fas fa-file-circle-exclamation fa-lg"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4">{{ number_format($stats['total']) }}</div>
                    <div class="text-muted small">Total Incidents</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 p-3 bg-danger bg-opacity-10 text-danger">
                    <i class="fas fa-triangle-exclamation fa-lg"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4">{{ number_format($stats['ongoing']) }}</div>
                    <div class="text-muted small">Active (Ongoing)</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 p-3 bg-warning bg-opacity-10 text-warning">
                    <i class="fas fa-people-group fa-lg"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4">{{ number_format($stats['affected']) }}</div>
                    <div class="text-muted small">Total Affected Pop.</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="card mb-3 border-0 shadow-sm">
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
            <div class="col">
                <input type="text" name="search" value="{{ request('search') }}"
                       class="form-control form-control-sm" placeholder="Search title or hazard type…">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-search"></i>
                </button>
                @if(request()->hasAny(['status','search']))
                    <a href="{{ route('incidents.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
                @endif
            </div>
        </form>
    </div>
</div>

{{-- Table --}}
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Title</th>
                        <th>Disaster Type</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th class="text-end">Affected Pop.</th>
                        <th class="text-muted small">Reported By</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($incidents as $incident)
                    <tr>
                        <td class="fw-semibold">{{ $incident->title }}</td>
                        <td>
                            @if($incident->hazardType)
                            <span style="color:{{ $incident->hazardType->color ?? '#333' }}">
                                <i class="fas {{ $incident->hazardType->icon ?? 'fa-exclamation-triangle' }} me-1"></i>
                                {{ $incident->hazardType->name }}
                            </span>
                            @else
                            <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-nowrap">{{ $incident->incident_date->format('M j, Y') }}</td>
                        <td>
                            @php
                                $statusClass = match($incident->status) {
                                    'ongoing'    => 'danger',
                                    'monitoring' => 'warning',
                                    'resolved'   => 'success',
                                    default      => 'secondary',
                                };
                            @endphp
                            <span class="badge bg-{{ $statusClass }}">{{ ucfirst($incident->status) }}</span>
                        </td>
                        <td class="text-end fw-medium">
                            {{ number_format($incident->total_affected ?? 0) }}
                        </td>
                        <td class="small text-muted">{{ $incident->reporter?->username ?? '—' }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('incidents.show', $incident) }}"
                               class="btn btn-sm btn-outline-info" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            @if(!auth()->user()->isDivisionChief())
                            <a href="{{ route('incidents.edit', $incident) }}"
                               class="btn btn-sm btn-outline-primary" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form method="POST" action="{{ route('incidents.destroy', $incident) }}"
                                  class="d-inline"
                                  onsubmit="return confirm('Delete this incident report?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            No incident reports found.
                            @if(!auth()->user()->isDivisionChief())
                            <a href="{{ route('incidents.create') }}">Create the first one.</a>
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($incidents->hasPages())
    <div class="card-footer">{{ $incidents->appends(request()->query())->links() }}</div>
    @endif
</div>

@endsection
