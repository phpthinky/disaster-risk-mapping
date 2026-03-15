@extends('layouts.app')

@section('title', 'Household Registrations — Graphical Report')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="page-title mb-0"><i class="fas fa-house-chimney me-2"></i>Household Registrations</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
                <li class="breadcrumb-item"><a href="{{ route('reports.graphical') }}">Graphical</a></li>
                <li class="breadcrumb-item active">Household Registrations</li>
            </ol>
        </nav>
    </div>
</div>
@endsection

@section('content')

{{-- Filter Controls --}}
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
        <div class="row g-3 align-items-end">

            {{-- Mode toggle --}}
            <div class="col-auto">
                <label class="form-label fw-semibold small mb-1">View Mode</label>
                <div class="btn-group" role="group">
                    <input type="radio" class="btn-check" name="viewMode" id="modeAnnual" value="annual" checked>
                    <label class="btn btn-outline-primary btn-sm" for="modeAnnual">Annual</label>
                    <input type="radio" class="btn-check" name="viewMode" id="modeMonthly" value="monthly">
                    <label class="btn btn-outline-primary btn-sm" for="modeMonthly">Monthly</label>
                </div>
            </div>

            {{-- Annual: year checkboxes --}}
            <div class="col-auto" id="annualControls">
                <label class="form-label fw-semibold small mb-1">Years (up to 5)</label>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($availableYears as $yr)
                    <div class="form-check form-check-inline m-0">
                        <input class="form-check-input year-cb" type="checkbox" value="{{ $yr }}" id="yr_{{ $yr }}"
                            {{ in_array($yr, [now()->year, now()->year-1, now()->year-2, now()->year-3, now()->year-4]) ? 'checked' : '' }}>
                        <label class="form-check-label small" for="yr_{{ $yr }}">{{ $yr }}</label>
                    </div>
                    @endforeach
                    @if($availableYears->isEmpty())
                        <span class="text-muted small">No household data yet.</span>
                    @endif
                </div>
            </div>

            {{-- Monthly: single year select --}}
            <div class="col-auto d-none" id="monthlyControls">
                <label class="form-label fw-semibold small mb-1">Year</label>
                <select id="monthlyYear" class="form-select form-select-sm" style="min-width:100px;">
                    @foreach($availableYears as $yr)
                        <option value="{{ $yr }}" {{ $yr == now()->year ? 'selected' : '' }}>{{ $yr }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Barangay (admin/division only) --}}
            @if(!auth()->user()->isBarangayStaff())
            <div class="col-auto">
                <label class="form-label fw-semibold small mb-1">Barangay</label>
                <select id="barangaySelect" class="form-select form-select-sm">
                    <option value="all">All Barangays</option>
                    @foreach($barangays as $b)
                        <option value="{{ $b->id }}">{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            <div class="col-auto">
                <button id="applyFilters" class="btn btn-primary btn-sm">
                    <i class="fas fa-sync-alt me-1"></i> Update Chart
                </button>
            </div>

            <div class="col-auto ms-auto">
                <span id="modeBadge" class="badge bg-success bg-opacity-15 text-success">Annual Comparison</span>
            </div>
        </div>
    </div>
</div>

{{-- Chart --}}
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div id="chartLoading" class="text-center py-4 d-none">
            <div class="spinner-border spinner-border-sm text-primary me-2"></div>
            <span class="text-muted">Loading chart data…</span>
        </div>
        <div id="noDataMsg" class="text-center py-5 d-none">
            <i class="fas fa-house-chimney fa-2x text-muted mb-2"></i><br>
            <span class="text-muted">No household registrations for the selected filters.</span>
        </div>
        <canvas id="mainChart" style="max-height: 420px;"></canvas>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
const DATA_URL = '{{ route("reports.graphical.households.data") }}';
let chart = null;

function getMode() { return document.querySelector('input[name="viewMode"]:checked').value; }
function getSelectedYears() { return [...document.querySelectorAll('.year-cb:checked')].map(cb => cb.value); }
function getMonthlyYear() { return document.getElementById('monthlyYear').value; }
function getBarangayId() { const s = document.getElementById('barangaySelect'); return s ? s.value : 'all'; }

document.querySelectorAll('input[name="viewMode"]').forEach(radio => {
    radio.addEventListener('change', () => {
        const isAnnual = getMode() === 'annual';
        document.getElementById('annualControls').classList.toggle('d-none', !isAnnual);
        document.getElementById('monthlyControls').classList.toggle('d-none', isAnnual);
        document.getElementById('modeBadge').textContent = isAnnual ? 'Annual Comparison' : 'Monthly Breakdown';
    });
});

async function loadChart() {
    const mode = getMode();
    document.getElementById('chartLoading').classList.remove('d-none');

    const params = new URLSearchParams({ mode });
    params.set('barangay_id', getBarangayId());

    if (mode === 'annual') {
        const years = getSelectedYears();
        if (!years.length) { document.getElementById('chartLoading').classList.add('d-none'); return; }
        years.forEach(y => params.append('years[]', y));
    } else {
        params.set('year', getMonthlyYear());
    }

    const res  = await fetch(`${DATA_URL}?${params}`);
    const data = await res.json();
    document.getElementById('chartLoading').classList.add('d-none');

    const hasData = data.datasets.some(ds => ds.data.some(v => v > 0));
    if (!hasData) {
        document.getElementById('noDataMsg').classList.remove('d-none');
        document.getElementById('mainChart').classList.add('d-none');
        return;
    }
    document.getElementById('noDataMsg').classList.add('d-none');
    document.getElementById('mainChart').classList.remove('d-none');

    if (chart) {
        chart.data.labels   = data.labels;
        chart.data.datasets = data.datasets;
        chart.update();
    } else {
        const ctx = document.getElementById('mainChart').getContext('2d');
        chart = new Chart(ctx, {
            type: 'bar',
            data: { labels: data.labels, datasets: data.datasets },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top', labels: { boxWidth: 12, padding: 14, font: { size: 12 } } },
                    tooltip: { callbacks: { label: ctx => ` ${ctx.dataset.label}: ${ctx.parsed.y.toLocaleString()}` } }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
    }
}

document.querySelectorAll('.year-cb').forEach(cb => {
    cb.addEventListener('change', () => {
        if (document.querySelectorAll('.year-cb:checked').length > 5) cb.checked = false;
    });
});

document.getElementById('applyFilters').addEventListener('click', loadChart);
loadChart();
</script>
@endpush
