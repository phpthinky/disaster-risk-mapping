@extends('layouts.app')

@section('title', 'Graphical Reports')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="page-title mb-0"><i class="fas fa-chart-line me-2"></i>Graphical Reports</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
                <li class="breadcrumb-item active">Graphical</li>
            </ol>
        </nav>
    </div>
</div>
@endsection

@section('content')

<div class="row g-4">

    <div class="col-md-6 col-xl-4">
        <a href="{{ route('reports.graphical.population') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 card-hover">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="rounded-3 p-3 bg-primary bg-opacity-10 text-primary">
                            <i class="fas fa-chart-line fa-2x"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold text-dark">Population Trend</h6>
                            <span class="badge bg-success bg-opacity-15 text-success mt-1">Annual comparison</span>
                        </div>
                    </div>
                    <p class="text-muted small mb-0">
                        Line chart comparing total population across barangays for up to 5 selected years.
                    </p>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-6 col-xl-4">
        <a href="{{ route('reports.graphical.vulnerability') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 card-hover">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="rounded-3 p-3 bg-danger bg-opacity-10 text-danger">
                            <i class="fas fa-chart-bar fa-2x"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold text-dark">Vulnerability Overview</h6>
                            <span class="badge bg-success bg-opacity-15 text-success mt-1">Annual comparison</span>
                        </div>
                    </div>
                    <p class="text-muted small mb-0">
                        Grouped bar chart showing PWD, Seniors, Children, At-Risk, and IP counts per year.
                    </p>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-6 col-xl-4">
        <a href="{{ route('reports.graphical.incidents') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 card-hover">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="rounded-3 p-3 bg-warning bg-opacity-10 text-warning">
                            <i class="fas fa-chart-column fa-2x"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold text-dark">Incident Frequency</h6>
                            <span class="badge bg-warning bg-opacity-15 text-warning mt-1">Annual · Monthly</span>
                        </div>
                    </div>
                    <p class="text-muted small mb-0">
                        Stacked bar chart by hazard type. Switch between 5-year annual view or month-by-month for a selected year.
                    </p>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-6 col-xl-4">
        <a href="{{ route('reports.graphical.households') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 card-hover">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="rounded-3 p-3 bg-success bg-opacity-10 text-success">
                            <i class="fas fa-house-chimney fa-2x"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold text-dark">Household Registrations</h6>
                            <span class="badge bg-warning bg-opacity-15 text-warning mt-1">Annual · Monthly</span>
                        </div>
                    </div>
                    <p class="text-muted small mb-0">
                        Bar chart showing new household registrations per year or per month within a selected year.
                    </p>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-6 col-xl-4">
        <a href="{{ route('reports.graphical.hazards') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 card-hover">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="rounded-3 p-3 bg-info bg-opacity-10 text-info">
                            <i class="fas fa-chart-pie fa-2x"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold text-dark">Hazard Exposure</h6>
                            <span class="badge bg-secondary bg-opacity-15 text-secondary mt-1">Current snapshot</span>
                        </div>
                    </div>
                    <p class="text-muted small mb-0">
                        Doughnut chart for risk level distribution + horizontal bar for top barangays by hazard area.
                    </p>
                </div>
            </div>
        </a>
    </div>

</div>

@endsection

@push('styles')
<style>
.card-hover { transition: transform .15s, box-shadow .15s; cursor: pointer; }
.card-hover:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,.12) !important; }
</style>
@endpush
