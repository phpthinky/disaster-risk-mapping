@extends('layouts.app')

@section('title', 'Population Data')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="page-title mb-0">Population Data</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item active">Population Data</li>
            </ol>
        </nav>
    </div>
    <div>
        <span class="badge bg-info"><i class="fas fa-lock me-1"></i>Auto-computed — read only</span>
    </div>
</div>
@endsection

@section('content')

<div class="alert alert-info d-flex gap-3 align-items-start mb-4 py-2">
    <i class="fas fa-info-circle mt-1 flex-shrink-0"></i>
    <div class="small">
        <strong>How archiving works:</strong>
        Population figures are computed automatically every time a household or member is saved.
        <em>Auto</em> archive entries are created on each change (continuous history).
        <em>Annual</em> snapshots are deliberate year-end records — admins can save one manually from the
        <strong>Details &amp; History</strong> page.
        The <strong class="text-danger">At Risk</strong> count shows people living inside mapped hazard zones
        (excludes "Not Susceptible" zones) — re-computed on every household sync.
    </div>
</div>

{{-- Stat Cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card stat-card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary rounded-3 p-3">
                    <i class="fas fa-users fa-lg"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4">{{ number_format($totals['population']) }}</div>
                    <div class="text-muted small">Total Population</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success rounded-3 p-3">
                    <i class="fas fa-house fa-lg"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4">{{ number_format($totals['households']) }}</div>
                    <div class="text-muted small">Total Households</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning rounded-3 p-3">
                    <i class="fas fa-wheelchair fa-lg"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4">{{ number_format($totals['pwd']) }}</div>
                    <div class="text-muted small">PWD Count</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card border-0 shadow-sm border-danger border-opacity-25">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger rounded-3 p-3">
                    <i class="fas fa-triangle-exclamation fa-lg"></i>
                </div>
                <div>
                    <div class="fw-bold fs-4 text-danger">{{ number_format($totals['at_risk']) }}</div>
                    <div class="text-muted small">
                        Living in Risk Zones
                        @if($totals['population'] > 0)
                        <span class="text-danger fw-semibold">
                            ({{ round($totals['at_risk'] / $totals['population'] * 100, 1) }}%)
                        </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Table --}}
<div class="card">
    <div class="card-header"><i class="fas fa-table me-2"></i>Barangay Population Summary</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Barangay</th>
                        <th class="text-end">Population</th>
                        <th class="text-end">Change</th>
                        <th class="text-end">Households</th>
                        <th class="text-end">Elderly (60+)</th>
                        <th class="text-end">Children</th>
                        <th class="text-end">PWD</th>
                        <th class="text-end">IPs</th>
                        <th class="text-end text-danger"
                            title="People living inside a mapped susceptible hazard zone (High/Moderate/Low/Prone)">
                            <i class="fas fa-triangle-exclamation me-1"></i>At Risk
                        </th>
                        <th class="text-muted text-end small">Last Sync</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($barangays as $b)
                    @php
                        $rec       = $currentRecords[$b->id] ?? null;
                        $prev      = $latestArchive[$b->id]  ?? null;
                        $diff      = ($prev) ? ($b->population - $prev->total_population) : null;
                        $atRiskPct = $b->population > 0 ? round($b->at_risk_count / $b->population * 100, 1) : 0;
                    @endphp
                    <tr>
                        <td class="fw-medium">{{ $b->name }}</td>
                        <td class="text-end">{{ number_format($b->population) }}</td>
                        <td class="text-end">
                            @if($diff !== null)
                                @if($diff > 0)
                                    <span class="text-success small"><i class="fas fa-arrow-up me-1"></i>{{ number_format($diff) }}</span>
                                @elseif($diff < 0)
                                    <span class="text-danger small"><i class="fas fa-arrow-down me-1"></i>{{ number_format(abs($diff)) }}</span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td class="text-end">{{ number_format($b->household_count) }}</td>
                        <td class="text-end">{{ number_format($b->senior_count) }}</td>
                        <td class="text-end">{{ number_format($b->children_count) }}</td>
                        <td class="text-end">{{ number_format($b->pwd_count) }}</td>
                        <td class="text-end">{{ number_format($b->ip_count) }}</td>
                        <td class="text-end">
                            @if($b->at_risk_count > 0)
                                <span class="text-danger fw-semibold">{{ number_format($b->at_risk_count) }}</span>
                                <span class="text-muted small d-block">{{ $atRiskPct }}%</span>
                            @else
                                <span class="text-muted">0</span>
                            @endif
                        </td>
                        <td class="text-end text-muted small">
                            {{ $rec?->updated_at?->format('M j, Y') ?? '—' }}
                        </td>
                        <td class="text-end">
                            <a href="{{ route('population.show', $b) }}" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-chart-line me-1"></i>Details &amp; History
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td>Total</td>
                        <td class="text-end">{{ number_format($totals['population']) }}</td>
                        <td></td>
                        <td class="text-end">{{ number_format($totals['households']) }}</td>
                        <td class="text-end">{{ number_format($barangays->sum('senior_count')) }}</td>
                        <td class="text-end">{{ number_format($barangays->sum('children_count')) }}</td>
                        <td class="text-end">{{ number_format($totals['pwd']) }}</td>
                        <td class="text-end">{{ number_format($barangays->sum('ip_count')) }}</td>
                        <td class="text-end text-danger">{{ number_format($totals['at_risk']) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
