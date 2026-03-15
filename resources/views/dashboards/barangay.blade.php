@extends('layouts.app')

@section('title', 'Barangay Dashboard')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="page-title mb-0">
            {{ $barangay?->name ?? 'Barangay' }} Dashboard
        </h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                @if($barangay)
                <li class="breadcrumb-item">
                    <a href="{{ route('barangays.show', $barangay) }}">{{ $barangay->name }}</a>
                </li>
                @endif
                <li class="breadcrumb-item active">Overview</li>
            </ol>
        </nav>
    </div>
    <div class="text-muted" style="font-size:.82rem;">
        <i class="fas fa-calendar me-1"></i>{{ now()->format('l, F j, Y') }}
    </div>
</div>
@endsection

@section('content')

@if(!$barangay)
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle me-2"></i>
    Your account is not assigned to a barangay. Please contact an administrator.
</div>
@else

{{-- Stat Cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg">
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
    <div class="col-6 col-lg">
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
    <div class="col-6 col-lg">
        <div class="card stat-card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger rounded-3 p-3">
                    <i class="fas fa-shield-halved fa-lg"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4">{{ number_format($stats['at_risk']) }}</div>
                    <div class="text-muted small">At-Risk Pop.</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg">
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
    <div class="col-6 col-lg">
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
</div>

{{-- Charts Row --}}
<div class="row g-4 mb-4">
    {{-- Population Breakdown (doughnut) --}}
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-chart-pie me-2"></i>Population Breakdown</div>
            <div class="card-body d-flex align-items-center justify-content-center">
                @if($stats['population'] > 0)
                <canvas id="popDoughnut" style="max-height:260px;"></canvas>
                @else
                <p class="text-muted mb-0">No population data yet.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Hazard Zones by Type (bar) --}}
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-chart-bar me-2"></i>Hazard Zones by Type</div>
            <div class="card-body d-flex align-items-center">
                @if($hazardChart['data']->sum() > 0)
                <canvas id="hazardBar" style="max-height:260px; width:100%;"></canvas>
                @else
                <p class="text-muted mb-0">No hazard zones in this barangay yet.</p>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Recent Households + Evac Centers + Quick Links --}}
<div class="row g-4">
    {{-- Recent Households --}}
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-house me-2"></i>Recent Households</span>
                <a href="{{ route('households.index') }}" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    @forelse($recentHouseholds as $hh)
                    <li class="list-group-item px-3 py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="{{ route('households.show', $hh) }}" class="text-decoration-none fw-medium small">
                                {{ $hh->household_head }}
                            </a>
                            @if($hh->latitude && $hh->longitude)
                            <span class="badge bg-success" title="GPS mapped"><i class="fas fa-map-marker-alt"></i></span>
                            @else
                            <span class="badge bg-secondary" title="No GPS"><i class="fas fa-map-marker-alt"></i></span>
                            @endif
                        </div>
                        @if($hh->sitio_purok_zone)
                        <div class="text-muted" style="font-size:.75rem;">{{ $hh->sitio_purok_zone }}</div>
                        @endif
                    </li>
                    @empty
                    <li class="list-group-item text-center text-muted py-3">No households recorded yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    {{-- Evacuation Centers --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-house-medical-flag me-2"></i>Evacuation Centers</span>
                <a href="{{ route('evacuations.index') }}" class="btn btn-sm btn-outline-secondary">All</a>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    @forelse($evacCenters as $ec)
                    @php
                        $rate = $ec->capacity > 0 ? ($ec->current_occupancy / $ec->capacity) * 100 : 0;
                        $sc   = match($ec->status) { 'operational' => 'success', 'maintenance' => 'warning', default => 'danger' };
                    @endphp
                    <li class="list-group-item px-3 py-2">
                        <div class="d-flex justify-content-between align-items-start">
                            <a href="{{ route('evacuations.show', $ec) }}" class="text-decoration-none fw-medium small">
                                {{ $ec->name }}
                            </a>
                            <span class="badge bg-{{ $sc }} ms-1 flex-shrink-0">{{ ucfirst($ec->status) }}</span>
                        </div>
                        <div class="text-muted" style="font-size:.75rem;">
                            Cap: {{ number_format($ec->capacity) }} · Occ: {{ number_format($ec->current_occupancy) }}
                        </div>
                        <div class="progress mt-1" style="height:3px;">
                            <div class="progress-bar bg-{{ $rate >= 80 ? 'danger' : ($rate >= 50 ? 'warning' : 'success') }}"
                                 style="width:{{ min(100,$rate) }}%"></div>
                        </div>
                    </li>
                    @empty
                    <li class="list-group-item text-center text-muted py-3">No evacuation centers.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="col-lg-3">
        <div class="card">
            <div class="card-header"><i class="fas fa-bolt me-2"></i>Quick Actions</div>
            <div class="card-body d-grid gap-2">
                <a href="{{ route('households.create') }}" class="btn btn-outline-primary btn-sm text-start">
                    <i class="fas fa-plus me-2"></i>Add Household
                </a>
                <a href="{{ route('households.index') }}" class="btn btn-outline-secondary btn-sm text-start">
                    <i class="fas fa-list me-2"></i>View Households
                </a>
                <a href="{{ route('hazards.index') }}" class="btn btn-outline-warning btn-sm text-start">
                    <i class="fas fa-radiation me-2"></i>Hazard Zones
                </a>
                <a href="{{ route('population.show', $barangay) }}" class="btn btn-outline-info btn-sm text-start">
                    <i class="fas fa-chart-bar me-2"></i>Population Data
                </a>
                <a href="{{ route('map.index') }}" class="btn btn-outline-dark btn-sm text-start">
                    <i class="fas fa-map me-2"></i>View Map
                </a>
            </div>
        </div>
    </div>
</div>

@endif
@endsection

@push('scripts')
@if($barangay && $stats['population'] > 0)
<script>
new Chart(document.getElementById('popDoughnut'), {
    type: 'doughnut',
    data: {
        labels: @json($popChart['labels']),
        datasets: [{
            data: @json($popChart['data']),
            backgroundColor: @json($popChart['colors']),
            borderWidth: 2,
            borderColor: '#fff',
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, padding: 8, font: { size: 11 } } }
        }
    }
});

@if($hazardChart['data']->sum() > 0)
new Chart(document.getElementById('hazardBar'), {
    type: 'bar',
    data: {
        labels: @json($hazardChart['labels']),
        datasets: [{
            label: 'Zones',
            data: @json($hazardChart['data']),
            backgroundColor: @json($hazardChart['colors']),
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { grid: { color: 'rgba(0,0,0,.05)' }, ticks: { precision: 0 } },
            x: { ticks: { font: { size: 11 }, maxRotation: 30 } }
        }
    }
});
@endif
</script>
@endif
@endpush
