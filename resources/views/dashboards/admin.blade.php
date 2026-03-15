@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="page-title mb-0">Dashboard</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item active">Admin Overview</li>
            </ol>
        </nav>
    </div>
    <div class="text-muted" style="font-size:.82rem;">
        <i class="fas fa-calendar me-1"></i>{{ now()->format('l, F j, Y') }}
    </div>
</div>
@endsection

@section('content')

{{-- Active Alerts Banner --}}
@if($activeAlerts->isNotEmpty())
<div class="mb-4">
    @foreach($activeAlerts as $alert)
    <div class="alert alert-{{ $alert->alert_type === 'danger' ? 'danger' : ($alert->alert_type === 'warning' ? 'warning' : 'info') }} alert-dismissible d-flex align-items-center gap-2 py-2" role="alert">
        <i class="fas fa-{{ $alert->alert_type === 'danger' ? 'triangle-exclamation' : ($alert->alert_type === 'warning' ? 'exclamation-circle' : 'info-circle') }}"></i>
        <div>
            <strong>{{ $alert->title }}</strong>
            @if($alert->message) — {{ $alert->message }} @endif
            @if($alert->barangay) <span class="badge bg-secondary ms-1">{{ $alert->barangay->name }}</span> @endif
        </div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
    @endforeach
</div>
@endif

{{-- Stat Cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-2">
        <div class="card stat-card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary rounded-3 p-3">
                    <i class="fas fa-map fa-lg"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4">{{ number_format($stats['barangays']) }}</div>
                    <div class="text-muted small">Barangays</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card stat-card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success rounded-3 p-3">
                    <i class="fas fa-house fa-lg"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4">{{ number_format($stats['households']) }}</div>
                    <div class="text-muted small">Households</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card stat-card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-info bg-opacity-10 text-info rounded-3 p-3">
                    <i class="fas fa-users fa-lg"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4">{{ number_format($stats['population']) }}</div>
                    <div class="text-muted small">Population</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card stat-card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger rounded-3 p-3">
                    <i class="fas fa-triangle-exclamation fa-lg"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4">{{ number_format($stats['active_incidents']) }}</div>
                    <div class="text-muted small">Active Incidents</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card stat-card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning rounded-3 p-3">
                    <i class="fas fa-radiation fa-lg"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4">{{ number_format($stats['hazard_zones']) }}</div>
                    <div class="text-muted small">Hazard Zones</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card stat-card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success rounded-3 p-3">
                    <i class="fas fa-house-medical-flag fa-lg"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4">{{ number_format($stats['evac_centers']) }}</div>
                    <div class="text-muted small">Evac Centers</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Charts Row --}}
<div class="row g-4 mb-4">
    {{-- Hazard Zone Distribution (doughnut) --}}
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-chart-pie me-2"></i>Hazard Zone Distribution</div>
            <div class="card-body d-flex align-items-center justify-content-center">
                @if($hazardChart['data']->sum() > 0)
                <canvas id="hazardDoughnut" style="max-height:260px;"></canvas>
                @else
                <p class="text-muted mb-0">No hazard zones recorded yet.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Top Barangays by Population (horizontal bar) --}}
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-chart-bar me-2"></i>Top Barangays by Population</div>
            <div class="card-body">
                @if($popChart['data']->sum() > 0)
                <canvas id="popBar" style="max-height:260px;"></canvas>
                @else
                <p class="text-muted mb-0">No population data yet.</p>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Recent Incidents + Quick Links --}}
<div class="row g-4">
    {{-- Recent Incidents --}}
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-bolt me-2"></i>Recent Incidents</span>
                <a href="{{ route('incidents.index') }}" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentIncidents as $inc)
                        @php
                            $sc = match($inc->status) {
                                'ongoing'    => 'danger',
                                'monitoring' => 'warning',
                                'resolved'   => 'success',
                                default      => 'secondary',
                            };
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('incidents.show', $inc) }}" class="text-decoration-none fw-medium">
                                    {{ $inc->title }}
                                </a>
                            </td>
                            <td>
                                @if($inc->hazardType)
                                <span style="color:{{ $inc->hazardType->color }}">
                                    <i class="fas {{ $inc->hazardType->icon }} me-1"></i>
                                </span>
                                {{ $inc->hazardType->name }}
                                @else
                                <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $inc->incident_date?->format('M d, Y') }}</td>
                            <td><span class="badge bg-{{ $sc }}">{{ ucfirst($inc->status) }}</span></td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">No incidents recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Quick Links --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="fas fa-bolt me-2"></i>Quick Actions</div>
            <div class="card-body d-grid gap-2">
                <a href="{{ route('households.create') }}" class="btn btn-outline-primary btn-sm text-start">
                    <i class="fas fa-plus me-2"></i>Add Household
                </a>
                <a href="{{ route('hazards.create') }}" class="btn btn-outline-warning btn-sm text-start">
                    <i class="fas fa-plus me-2"></i>Add Hazard Zone
                </a>
                <a href="{{ route('incidents.create') }}" class="btn btn-outline-danger btn-sm text-start">
                    <i class="fas fa-plus me-2"></i>Create Incident Report
                </a>
                <a href="{{ route('evacuations.create') }}" class="btn btn-outline-success btn-sm text-start">
                    <i class="fas fa-plus me-2"></i>Add Evacuation Center
                </a>
                <a href="{{ route('map.index') }}" class="btn btn-outline-info btn-sm text-start">
                    <i class="fas fa-map me-2"></i>View Master Map
                </a>
                <a href="{{ route('alerts.index') }}" class="btn btn-outline-secondary btn-sm text-start">
                    <i class="fas fa-bell me-2"></i>Manage Alerts
                </a>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
@if($hazardChart['data']->sum() > 0)
new Chart(document.getElementById('hazardDoughnut'), {
    type: 'doughnut',
    data: {
        labels: @json($hazardChart['labels']),
        datasets: [{
            data: @json($hazardChart['data']),
            backgroundColor: @json($hazardChart['colors']),
            borderWidth: 2,
            borderColor: '#fff',
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10, font: { size: 11 } } }
        }
    }
});
@endif

@if($popChart['data']->sum() > 0)
new Chart(document.getElementById('popBar'), {
    type: 'bar',
    data: {
        labels: @json($popChart['labels']),
        datasets: [{
            label: 'Population',
            data: @json($popChart['data']),
            backgroundColor: 'rgba(13,110,253,0.75)',
            borderRadius: 4,
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { color: 'rgba(0,0,0,.05)' } },
            y: { ticks: { font: { size: 11 } } }
        }
    }
});
@endif
</script>
@endpush
