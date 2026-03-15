@extends('layouts.app')

@section('title', $barangay->name . ' — Population Data')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="page-title mb-0">{{ $barangay->name }}</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                @if(!auth()->user()->isBarangayStaff())
                <li class="breadcrumb-item"><a href="{{ route('population.index') }}">Population Data</a></li>
                @endif
                <li class="breadcrumb-item active">{{ $barangay->name }}</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="badge bg-info"><i class="fas fa-lock me-1"></i>Read only</span>
        @if(auth()->user()->isAdmin())
        <form method="POST" action="{{ route('population.snapshot', $barangay) }}"
              onsubmit="return confirm('Save an annual snapshot for {{ $barangay->name }}?\nThis will also refresh the At Risk count from current hazard zone data.')">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-success">
                <i class="fas fa-camera me-1"></i>Take Annual Snapshot
            </button>
        </form>
        @endif
        @if(!auth()->user()->isBarangayStaff())
        <a href="{{ route('population.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> All Barangays
        </a>
        @endif
    </div>
</div>
@endsection

@section('content')

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">
    <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show">
    <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

@if(!$current)
<div class="alert alert-warning">
    <i class="fas fa-info-circle me-2"></i>
    No population data yet for this barangay. Data is auto-computed when households are added.
</div>
@endif

{{-- Row 1: Snapshot card + Trend chart --}}
<div class="row g-4 mb-4">

    {{-- Current Snapshot --}}
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <span><i class="fas fa-chart-pie me-2"></i>Current Snapshot</span>
                @if($current)
                <small class="opacity-75">{{ $current->updated_at?->format('M j, Y') }}</small>
                @endif
            </div>
            <div class="card-body">
                @php $pop = $barangay->population; @endphp

                {{-- Top numbers --}}
                <div class="row g-3 mb-3">
                    <div class="col-6 text-center">
                        <div class="fw-bold fs-3 text-primary">{{ number_format($pop) }}</div>
                        <div class="text-muted small">Total Population</div>
                    </div>
                    <div class="col-6 text-center">
                        <div class="fw-bold fs-3 text-success">{{ number_format($barangay->household_count) }}</div>
                        <div class="text-muted small">Households</div>
                    </div>
                </div>

                {{-- At Risk highlight --}}
                @php $atRiskPct = $pop > 0 ? round($barangay->at_risk_count / $pop * 100, 1) : 0; @endphp
                <div class="alert alert-danger py-2 mb-3 d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-triangle-exclamation me-1"></i>
                        <strong>At Risk (Hazard Zones)</strong>
                    </div>
                    <div class="text-end">
                        <span class="fw-bold fs-5">{{ number_format($barangay->at_risk_count) }}</span>
                        <span class="small ms-1 opacity-75">{{ $atRiskPct }}%</span>
                    </div>
                </div>

                <hr>

                <table class="table table-sm table-borderless mb-0">
                    @php
                        $rows = [
                            ['icon'=>'fa-user-clock',  'label'=>'Elderly (60+)',  'val'=>$barangay->senior_count],
                            ['icon'=>'fa-child',       'label'=>'Children',       'val'=>$barangay->children_count],
                            ['icon'=>'fa-baby',        'label'=>'Infants (0–2)',  'val'=>$barangay->infant_count],
                            ['icon'=>'fa-wheelchair',  'label'=>'PWD',            'val'=>$barangay->pwd_count],
                            ['icon'=>'fa-female',      'label'=>'Pregnant',       'val'=>$barangay->pregnant_count],
                            ['icon'=>'fa-leaf',        'label'=>'IPs',            'val'=>$barangay->ip_count],
                        ];
                    @endphp
                    @foreach($rows as $r)
                    <tr>
                        <td><i class="fas {{ $r['icon'] }} text-muted me-2"></i>{{ $r['label'] }}</td>
                        <td class="text-end fw-semibold">{{ number_format($r['val']) }}</td>
                        <td class="text-end text-muted small">
                            {{ $pop > 0 ? round($r['val'] / $pop * 100, 1) : 0 }}%
                        </td>
                    </tr>
                    @endforeach
                    @if($current?->solo_parent_count)
                    <tr>
                        <td><i class="fas fa-user text-muted me-2"></i>Solo Parents</td>
                        <td class="text-end fw-semibold">{{ number_format($current->solo_parent_count) }}</td>
                        <td></td>
                    </tr>
                    @endif
                    @if($current?->widow_count)
                    <tr>
                        <td><i class="fas fa-user text-muted me-2"></i>Widows/Widowers</td>
                        <td class="text-end fw-semibold">{{ number_format($current->widow_count) }}</td>
                        <td></td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>
    </div>

    {{-- Population Trend Chart --}}
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-chart-line me-2"></i>Population &amp; At Risk Trend</span>
                <small class="text-muted">Last 10 annual snapshots</small>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
                @if($chartData->isNotEmpty())
                <canvas id="popTrendChart" style="max-height:280px;"></canvas>
                @else
                <div class="text-muted text-center py-5">
                    <i class="fas fa-chart-line fa-2x mb-2 d-block opacity-50"></i>
                    No annual snapshot yet — trend will appear after the first annual snapshot is taken.
                    @if(auth()->user()->isAdmin())
                    <div class="mt-3">
                        <form method="POST" action="{{ route('population.snapshot', $barangay) }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-success">
                                <i class="fas fa-camera me-1"></i>Take First Snapshot Now
                            </button>
                        </form>
                    </div>
                    @endif
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Row 2: Incident Impact History --}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>
            <i class="fas fa-house-flood-water me-2 text-warning"></i>Incident Impact History
            <span class="badge bg-secondary ms-1">{{ $incidentImpact->count() }} year(s) recorded</span>
        </span>
        <a href="{{ route('incidents.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-file-circle-exclamation me-1"></i>View Incidents
        </a>
    </div>
    <div class="card-body p-0">
        @if($incidentImpact->isEmpty())
        <div class="text-muted text-center py-4">
            <i class="fas fa-shield-halved fa-lg d-block mb-2 opacity-50"></i>
            No incident reports have affected this barangay yet.
        </div>
        @else
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Year</th>
                        <th class="text-center">Incidents</th>
                        <th class="text-end">People Affected</th>
                        <th class="text-end">Households</th>
                        <th class="text-end">PWD</th>
                        <th class="text-end">Elderly</th>
                        <th class="text-end">Infants</th>
                        <th class="text-end">Pregnant</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($incidentImpact as $row)
                    <tr>
                        <td class="fw-semibold">{{ $row->year }}</td>
                        <td class="text-center">
                            <span class="badge bg-warning text-dark">{{ $row->incident_count }}</span>
                        </td>
                        <td class="text-end fw-bold text-danger">{{ number_format($row->population) }}</td>
                        <td class="text-end">{{ number_format($row->households) }}</td>
                        <td class="text-end">{{ number_format($row->pwd) }}</td>
                        <td class="text-end">{{ number_format($row->seniors) }}</td>
                        <td class="text-end">{{ number_format($row->infants) }}</td>
                        <td class="text-end">{{ number_format($row->pregnant) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                @if($incidentImpact->count() > 1)
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td>All Years</td>
                        <td class="text-center">{{ $incidentImpact->sum('incident_count') }}</td>
                        <td class="text-end text-danger">{{ number_format($incidentImpact->sum('population')) }}</td>
                        <td class="text-end">{{ number_format($incidentImpact->sum('households')) }}</td>
                        <td class="text-end">{{ number_format($incidentImpact->sum('pwd')) }}</td>
                        <td class="text-end">{{ number_format($incidentImpact->sum('seniors')) }}</td>
                        <td class="text-end">{{ number_format($incidentImpact->sum('infants')) }}</td>
                        <td class="text-end">{{ number_format($incidentImpact->sum('pregnant')) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
        @endif
    </div>
</div>

{{-- Row 3: Archive Trail --}}
<div class="card" id="archive-trail">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>
            <i class="fas fa-history me-2"></i>Archive Trail
            <span class="badge bg-secondary ms-1">{{ $archives->total() }} records</span>
            <span class="ms-2 small text-muted">
                <span class="badge bg-success text-dark me-1">annual</span>year-end snapshot
                <span class="badge bg-light border text-dark ms-2 me-1">auto</span>on household change
            </span>
        </span>
        {{-- Filters --}}
        <form method="GET" class="d-flex align-items-center gap-2 flex-wrap">
            <select name="type" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                <option value="">All Types</option>
                <option value="annual" {{ request('type') === 'annual' ? 'selected' : '' }}>Annual only</option>
                <option value="auto"   {{ request('type') === 'auto'   ? 'selected' : '' }}>Auto only</option>
            </select>
            <select name="year" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                <option value="">All Years</option>
                @foreach($archiveYears as $yr)
                    <option value="{{ $yr }}" {{ request('year') == $yr ? 'selected' : '' }}>{{ $yr }}</option>
                @endforeach
            </select>
            @if(request('year') || request('type'))
                <a href="{{ route('population.show', $barangay) }}#archive-trail"
                   class="btn btn-sm btn-outline-secondary">Clear</a>
            @endif
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Archived At</th>
                        <th>Type</th>
                        <th class="text-end">Population</th>
                        <th class="text-end">Households</th>
                        <th class="text-end">Elderly</th>
                        <th class="text-end">Children</th>
                        <th class="text-end">PWD</th>
                        <th class="text-end">IPs</th>
                        <th class="text-end text-danger"
                            title="People living in susceptible hazard zones at time of snapshot">
                            <i class="fas fa-triangle-exclamation me-1"></i>At Risk
                        </th>
                        <th>Archived By</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($archives as $arc)
                    @php $isAnnual = ($arc->snapshot_type ?? 'auto') === 'annual'; @endphp
                    <tr class="{{ $isAnnual ? 'table-success' : '' }}">
                        <td class="small {{ $isAnnual ? 'fw-semibold' : 'text-muted' }}">
                            {{ $arc->archived_at?->format('M j, Y g:i A') ?? '—' }}
                        </td>
                        <td>
                            @if($isAnnual)
                                <span class="badge bg-success text-dark">
                                    <i class="fas fa-star me-1"></i>Annual
                                </span>
                            @else
                                <span class="badge bg-light border text-muted">Auto</span>
                            @endif
                        </td>
                        <td class="text-end fw-medium">{{ number_format($arc->total_population) }}</td>
                        <td class="text-end">{{ number_format($arc->households) }}</td>
                        <td class="text-end">{{ number_format($arc->elderly_count) }}</td>
                        <td class="text-end">{{ number_format($arc->children_count) }}</td>
                        <td class="text-end">{{ number_format($arc->pwd_count) }}</td>
                        <td class="text-end">{{ number_format($arc->ips_count) }}</td>
                        <td class="text-end">
                            @if(($arc->at_risk_count ?? 0) > 0)
                                <span class="text-danger fw-semibold">{{ number_format($arc->at_risk_count) }}</span>
                            @else
                                <span class="text-muted">0</span>
                            @endif
                        </td>
                        <td class="small">{{ $arc->archived_by ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">No archive records found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($archives->hasPages())
    <div class="card-footer">{{ $archives->appends(request()->query())->links() }}</div>
    @endif
</div>

@endsection

@push('scripts')
@if($chartData->isNotEmpty())
<script>
(function () {
    var labels    = [{!! $chartData->pluck('archived_at')->map(fn($d) => '"' . \Carbon\Carbon::parse($d)->format('M j, Y') . '"')->join(',') !!}];
    var popData   = [{!! $chartData->pluck('total_population')->join(',') !!}];
    var hhData    = [{!! $chartData->pluck('households')->join(',') !!}];
    var riskData  = [{!! $chartData->pluck('at_risk_count')->join(',') !!}];

    new Chart(document.getElementById('popTrendChart'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Population',
                    data: popData,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13,110,253,0.08)',
                    tension: 0.3,
                    fill: true,
                    pointRadius: 4,
                    yAxisID: 'y',
                },
                {
                    label: 'Households',
                    data: hhData,
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25,135,84,0.08)',
                    tension: 0.3,
                    fill: false,
                    pointRadius: 4,
                    yAxisID: 'y1',
                },
                {
                    label: 'At Risk',
                    data: riskData,
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220,53,69,0.10)',
                    tension: 0.3,
                    fill: true,
                    pointRadius: 4,
                    borderDash: [5, 3],
                    yAxisID: 'y',
                }
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'top' } },
            scales: {
                y:  { position: 'left',  title: { display: true, text: 'People' } },
                y1: { position: 'right', title: { display: true, text: 'Households' },
                      grid: { drawOnChartArea: false } }
            }
        }
    });
})();
</script>
@endif
@endpush
