@extends('layouts.app')

@section('title', 'Hazard Exposure — Graphical Report')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="page-title mb-0"><i class="fas fa-chart-pie me-2"></i>Hazard Exposure</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
                <li class="breadcrumb-item"><a href="{{ route('reports.graphical') }}">Graphical</a></li>
                <li class="breadcrumb-item active">Hazard Exposure</li>
            </ol>
        </nav>
    </div>
    <span class="badge bg-secondary bg-opacity-15 text-secondary fs-6">Current Snapshot</span>
</div>
@endsection

@section('content')

@php
    $riskColors = [
        'High Susceptible'         => '#ef4444',
        'Prone'                    => '#dc2626',
        'PEIS VIII - Very destructive to devastating ground shaking' => '#b91c1c',
        'Moderate Susceptible'     => '#f97316',
        'PEIS VII - Destructive ground shaking' => '#ea580c',
        'Low Susceptible'          => '#3b82f6',
        'Generally Susceptible'    => '#60a5fa',
        'General Inundation'       => '#06b6d4',
        'Not Susceptible'          => '#22c55e',
    ];
    $defaultColors = ['#4361ee','#f72585','#7209b7','#4cc9f0','#f77f00','#4ade80','#e63946','#fb8500','#118ab2'];
@endphp

<div class="row g-4">

    {{-- Risk Level Distribution (Doughnut) --}}
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header fw-semibold bg-transparent border-bottom">
                <i class="fas fa-chart-pie me-2 text-info"></i>Risk Level Distribution
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
                @if($riskCounts->sum() > 0)
                <canvas id="riskDoughnut" style="max-height: 320px; max-width: 320px;"></canvas>
                @else
                <p class="text-muted mb-0">No hazard zone data available.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Hazard Type Distribution (Doughnut) --}}
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header fw-semibold bg-transparent border-bottom">
                <i class="fas fa-triangle-exclamation me-2 text-warning"></i>Zones by Hazard Type
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
                @if($hazardCounts->sum() > 0)
                <canvas id="hazardTypeBar" style="max-height: 320px;"></canvas>
                @else
                <p class="text-muted mb-0">No hazard zone data available.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Top Barangays by Hazard Area (Horizontal Bar) --}}
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header fw-semibold bg-transparent border-bottom">
                <i class="fas fa-map-location-dot me-2 text-danger"></i>Top Barangays by Total Hazard Area (km²)
            </div>
            <div class="card-body">
                @if($topBarangays->isNotEmpty())
                <canvas id="barangayBar" style="max-height: 320px;"></canvas>
                @else
                <p class="text-muted mb-0 text-center py-4">No hazard zone data available.</p>
                @endif
            </div>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
const riskColors = @json($riskColors);
const defaultColors = @json($defaultColors);

@if($riskCounts->sum() > 0)
// ── Doughnut: risk levels ─────────────────────────────────────────────────
const riskLabels = @json($riskCounts->keys()->values());
const riskData   = @json($riskCounts->values());
const riskBgColors = riskLabels.map((l, i) => riskColors[l] || defaultColors[i % defaultColors.length]);

new Chart(document.getElementById('riskDoughnut'), {
    type: 'doughnut',
    data: {
        labels: riskLabels,
        datasets: [{ data: riskData, backgroundColor: riskBgColors, borderWidth: 2, borderColor: '#fff' }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 11, padding: 10, font: { size: 10 } } },
            tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.parsed} zones` } }
        }
    }
});
@endif

@if($hazardCounts->sum() > 0)
// ── Bar: hazard types ─────────────────────────────────────────────────────
const htLabels = @json($hazardCounts->keys()->values());
const htData   = @json($hazardCounts->values());

new Chart(document.getElementById('hazardTypeBar'), {
    type: 'bar',
    data: {
        labels: htLabels,
        datasets: [{
            label: 'Zones',
            data: htData,
            backgroundColor: defaultColors.map(c => c + 'bb'),
            borderColor: defaultColors,
            borderWidth: 1,
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: ctx => ` ${ctx.parsed.y} zone(s)` } }
        },
        scales: {
            x: { grid: { display: false } },
            y: { beginAtZero: true, ticks: { stepSize: 1 } }
        }
    }
});
@endif

@if($topBarangays->isNotEmpty())
// ── Horizontal bar: top barangays by area ─────────────────────────────────
const bLabels = @json($topBarangays->keys()->values());
const bData   = @json($topBarangays->values());

new Chart(document.getElementById('barangayBar'), {
    type: 'bar',
    data: {
        labels: bLabels,
        datasets: [{
            label: 'Hazard Area (km²)',
            data: bData,
            backgroundColor: '#4361eeaa',
            borderColor: '#4361ee',
            borderWidth: 1,
            borderRadius: 4,
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: ctx => ` ${ctx.parsed.x} km²` } }
        },
        scales: {
            x: { beginAtZero: true, title: { display: true, text: 'km²' } },
            y: { grid: { display: false } }
        }
    }
});
@endif
</script>
@endpush
