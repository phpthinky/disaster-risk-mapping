@extends('layouts.app')

@section('title', 'Population Trend — Graphical Report')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="page-title mb-0"><i class="fas fa-chart-line me-2"></i>Population Trend</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
                <li class="breadcrumb-item"><a href="{{ route('reports.graphical') }}">Graphical</a></li>
                <li class="breadcrumb-item active">Population Trend</li>
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

            {{-- Year checkboxes --}}
            <div class="col-auto">
                <label class="form-label fw-semibold small mb-1">Years (select up to 5)</label>
                <div class="d-flex flex-wrap gap-2" id="yearCheckboxes">
                    @foreach($availableYears as $yr)
                    <div class="form-check form-check-inline m-0">
                        <input class="form-check-input year-cb" type="checkbox" value="{{ $yr }}" id="yr_{{ $yr }}"
                            {{ in_array($yr, [now()->year, now()->year-1, now()->year-2, now()->year-3, now()->year-4]) ? 'checked' : '' }}>
                        <label class="form-check-label small" for="yr_{{ $yr }}">{{ $yr }}</label>
                    </div>
                    @endforeach
                    @if($availableYears->isEmpty())
                        <span class="text-muted small">No population data recorded yet.</span>
                    @endif
                </div>
            </div>

            {{-- Barangay selector --}}
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
                <span class="badge bg-primary bg-opacity-10 text-primary">Annual Comparison</span>
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
            <i class="fas fa-chart-line fa-2x text-muted mb-2"></i><br>
            <span class="text-muted">No population data for the selected years and barangay.</span>
        </div>
        <canvas id="mainChart" style="max-height: 420px;"></canvas>
    </div>
</div>

@endsection

@push('styles')
<style>
.form-check-input.year-cb:checked { background-color: #4361ee; border-color: #4361ee; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
const DATA_URL = '{{ route("reports.graphical.population.data") }}';

let chart = null;

function getSelectedYears() {
    return [...document.querySelectorAll('.year-cb:checked')].map(cb => cb.value);
}

function getBarangayId() {
    const sel = document.getElementById('barangaySelect');
    return sel ? sel.value : 'all';
}

async function loadChart() {
    const years = getSelectedYears();
    if (years.length === 0) return;

    document.getElementById('chartLoading').classList.remove('d-none');

    const params = new URLSearchParams();
    years.forEach(y => params.append('years[]', y));
    params.set('barangay_id', getBarangayId());

    const res  = await fetch(`${DATA_URL}?${params}`);
    const data = await res.json();

    document.getElementById('chartLoading').classList.add('d-none');

    if (!data.datasets.length || data.datasets.every(ds => ds.data.every(v => v === 0))) {
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
            type: 'line',
            data: { labels: data.labels, datasets: data.datasets },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top', labels: { boxWidth: 12, padding: 14, font: { size: 12 } } },
                    tooltip: {
                        callbacks: {
                            label: ctx => ` ${ctx.dataset.label}: ${ctx.parsed.y.toLocaleString()}`
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: v => v >= 1000 ? (v/1000).toFixed(1)+'k' : v
                        }
                    }
                }
            }
        });
    }
}

// Enforce max 5 checkboxes
document.querySelectorAll('.year-cb').forEach(cb => {
    cb.addEventListener('change', () => {
        const checked = document.querySelectorAll('.year-cb:checked');
        if (checked.length > 5) { cb.checked = false; }
    });
});

document.getElementById('applyFilters').addEventListener('click', loadChart);

// Initial load
loadChart();
</script>
@endpush
