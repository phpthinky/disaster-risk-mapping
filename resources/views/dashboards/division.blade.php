@extends('layouts.app')

@section('title', 'Division Chief Dashboard')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="page-title mb-0">Division Chief Dashboard</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item active">Aggregate Overview</li>
            </ol>
        </nav>
    </div>
    <div class="text-muted" style="font-size:.82rem;">
        <i class="fas fa-calendar me-1"></i>{{ now()->format('l, F j, Y') }}
    </div>
</div>
@endsection

@section('content')

{{-- Stat Cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg">
        <div class="card stat-card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-info bg-opacity-10 text-info rounded-3 p-3">
                    <i class="fas fa-users fa-lg"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4">{{ number_format($stats['population']) }}</div>
                    <div class="text-muted small">Total Population</div>
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
                    <div class="text-muted small">At-Risk Population</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg">
        <div class="card stat-card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning rounded-3 p-3">
                    <i class="fas fa-triangle-exclamation fa-lg"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4">{{ number_format($stats['active_incidents']) }}</div>
                    <div class="text-muted small">Active Incidents</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg">
        <div class="card stat-card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success rounded-3 p-3">
                    <i class="fas fa-house-medical-flag fa-lg"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4">{{ number_format($stats['evac_operational']) }}</div>
                    <div class="text-muted small">Evac Centers</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg">
        <div class="card stat-card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary rounded-3 p-3">
                    <i class="fas fa-person-shelter fa-lg"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4">{{ number_format($stats['evac_capacity']) }}</div>
                    <div class="text-muted small">Evac Capacity</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Charts Row --}}
<div class="row g-4 mb-4">
    {{-- Vulnerable Population by Barangay --}}
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-chart-bar me-2"></i>Vulnerable Population by Barangay</div>
            <div class="card-body" style="overflow-x:auto;">
                @if($vulnChart['pwd']->sum() + $vulnChart['senior']->sum() + $vulnChart['infant']->sum() > 0)
                <canvas id="vulnChart" style="min-height:280px;"></canvas>
                @else
                <p class="text-muted mb-0">No vulnerability data recorded yet.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Hazard Zones by Barangay --}}
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-chart-bar me-2"></i>Hazard Zones by Barangay</div>
            <div class="card-body">
                @if($hazardBarChart['data']->sum() > 0)
                <canvas id="hazardBar" style="max-height:280px;"></canvas>
                @else
                <p class="text-muted mb-0">No hazard zones recorded yet.</p>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Barangay Summary Table + Recent Incidents --}}
<div class="row g-4">
    {{-- Barangay Summary --}}
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-table me-2"></i>Barangay Summary</span>
                <a href="{{ route('barangays.index') }}" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Barangay</th>
                                <th class="text-end">Population</th>
                                <th class="text-end">Households</th>
                                <th class="text-end">At-Risk</th>
                                <th class="text-end">Hazard Zones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($barangays as $b)
                            <tr>
                                <td>
                                    <a href="{{ route('barangays.show', $b) }}" class="text-decoration-none fw-medium">
                                        {{ $b->name }}
                                    </a>
                                </td>
                                <td class="text-end">{{ number_format($b->population) }}</td>
                                <td class="text-end">{{ number_format($b->household_count) }}</td>
                                <td class="text-end">
                                    @if($b->at_risk_count > 0)
                                    <span class="text-danger fw-medium">{{ number_format($b->at_risk_count) }}</span>
                                    @else
                                    <span class="text-muted">0</span>
                                    @endif
                                </td>
                                <td class="text-end">{{ number_format($b->hazard_zones_count ?? 0) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">No barangays found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Incidents --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-bolt me-2"></i>Recent Incidents</span>
                <a href="{{ route('incidents.index') }}" class="btn btn-sm btn-outline-secondary">All</a>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    @forelse($recentIncidents as $inc)
                    @php
                        $sc = match($inc->status) {
                            'ongoing'    => 'danger',
                            'monitoring' => 'warning',
                            'resolved'   => 'success',
                            default      => 'secondary',
                        };
                    @endphp
                    <li class="list-group-item px-3 py-2">
                        <div class="d-flex justify-content-between align-items-start">
                            <a href="{{ route('incidents.show', $inc) }}" class="text-decoration-none fw-medium small">
                                {{ $inc->title }}
                            </a>
                            <span class="badge bg-{{ $sc }} ms-2 flex-shrink-0">{{ ucfirst($inc->status) }}</span>
                        </div>
                        <div class="text-muted" style="font-size:.75rem;">
                            {{ $inc->incident_date?->format('M d, Y') }}
                            @if($inc->hazardType) · {{ $inc->hazardType->name }} @endif
                        </div>
                    </li>
                    @empty
                    <li class="list-group-item text-center text-muted py-3">No incidents recorded.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
@if($vulnChart['pwd']->sum() + $vulnChart['senior']->sum() + $vulnChart['infant']->sum() > 0)
new Chart(document.getElementById('vulnChart'), {
    type: 'bar',
    data: {
        labels: @json($vulnChart['labels']),
        datasets: [
            { label: 'Senior', data: @json($vulnChart['senior']),   backgroundColor: 'rgba(111,66,193,.75)',  borderRadius: 3 },
            { label: 'PWD',    data: @json($vulnChart['pwd']),      backgroundColor: 'rgba(220,53,69,.75)',   borderRadius: 3 },
            { label: 'Infant', data: @json($vulnChart['infant']),   backgroundColor: 'rgba(253,126,20,.75)',  borderRadius: 3 },
            { label: 'Pregnant',data:@json($vulnChart['pregnant']), backgroundColor: 'rgba(214,51,132,.75)',  borderRadius: 3 },
            { label: 'IP',     data: @json($vulnChart['ip']),       backgroundColor: 'rgba(32,201,151,.75)',  borderRadius: 3 },
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
        scales: {
            x: { stacked: true, ticks: { font: { size: 10 }, maxRotation: 45 } },
            y: { stacked: true, grid: { color: 'rgba(0,0,0,.05)' } }
        }
    }
});
@endif

@if($hazardBarChart['data']->sum() > 0)
new Chart(document.getElementById('hazardBar'), {
    type: 'bar',
    data: {
        labels: @json($hazardBarChart['labels']),
        datasets: [{
            label: 'Hazard Zones',
            data: @json($hazardBarChart['data']),
            backgroundColor: 'rgba(255,193,7,.8)',
            borderRadius: 4,
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { color: 'rgba(0,0,0,.05)' }, ticks: { precision: 0 } },
            y: { ticks: { font: { size: 11 } } }
        }
    }
});
@endif
</script>
@endpush
