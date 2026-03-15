@extends('layouts.app')

@section('title', 'Reports')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="page-title mb-0"><i class="fas fa-file-lines me-2"></i>Reports</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item active">Reports</li>
            </ol>
        </nav>
    </div>
</div>
@endsection

@section('content')

<div class="row g-4">

    {{-- Tabular Reports Card --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="rounded-3 p-3 bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-table fa-2x"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold">Tabular Reports</h5>
                        <div class="text-muted small">Filter, preview, and download as Excel or PDF</div>
                    </div>
                </div>
                <div class="list-group list-group-flush">
                    <a href="{{ route('reports.population') }}" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3 px-0 border-0 border-bottom">
                        <div class="rounded-circle p-2 bg-info bg-opacity-10 text-info">
                            <i class="fas fa-users fa-sm"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-medium">Population Summary</div>
                            <div class="text-muted small">All barangay population figures with vulnerability counts</div>
                        </div>
                        <span class="badge bg-info bg-opacity-15 text-info">Excel · PDF</span>
                    </a>
                    <a href="{{ route('reports.risk') }}" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3 px-0 border-0 border-bottom">
                        <div class="rounded-circle p-2 bg-danger bg-opacity-10 text-danger">
                            <i class="fas fa-triangle-exclamation fa-sm"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-medium">Barangay Risk Analysis</div>
                            <div class="text-muted small">Hazard zone exposure grouped by barangay and risk level</div>
                        </div>
                        <span class="badge bg-info bg-opacity-15 text-info">Excel · PDF</span>
                    </a>
                    <a href="{{ route('reports.households') }}" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3 px-0 border-0 border-bottom">
                        <div class="rounded-circle p-2 bg-success bg-opacity-10 text-success">
                            <i class="fas fa-house-chimney-user fa-sm"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-medium">Household Data Export</div>
                            <div class="text-muted small">Full household roster with demographics and GPS</div>
                        </div>
                        <span class="badge bg-info bg-opacity-15 text-info">Excel only</span>
                    </a>
                    <a href="{{ route('reports.incidents') }}" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3 px-0 border-0">
                        <div class="rounded-circle p-2 bg-warning bg-opacity-10 text-warning">
                            <i class="fas fa-file-circle-exclamation fa-sm"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-medium">Incident Summary</div>
                            <div class="text-muted small">All incidents with affected barangay aggregates</div>
                        </div>
                        <span class="badge bg-info bg-opacity-15 text-info">Excel · PDF</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Graphical Reports Card --}}
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="rounded-3 p-3 bg-success bg-opacity-10 text-success">
                        <i class="fas fa-chart-line fa-2x"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold">Graphical Reports</h5>
                        <div class="text-muted small">Interactive charts with year-by-year &amp; month-by-month comparison</div>
                    </div>
                </div>
                <div class="list-group list-group-flush">
                    <a href="{{ route('reports.graphical.population') }}" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3 px-0 border-0 border-bottom">
                        <div class="rounded-circle p-2 bg-primary bg-opacity-10 text-primary">
                            <i class="fas fa-chart-line fa-sm"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-medium">Population Trend</div>
                            <div class="text-muted small">Line chart — compare up to 5 years across barangays</div>
                        </div>
                        <span class="badge bg-success bg-opacity-15 text-success">Annual</span>
                    </a>
                    <a href="{{ route('reports.graphical.vulnerability') }}" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3 px-0 border-0 border-bottom">
                        <div class="rounded-circle p-2 bg-danger bg-opacity-10 text-danger">
                            <i class="fas fa-chart-bar fa-sm"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-medium">Vulnerability Overview</div>
                            <div class="text-muted small">Grouped bar — PWD, Seniors, Children, At-Risk, IP per year</div>
                        </div>
                        <span class="badge bg-success bg-opacity-15 text-success">Annual</span>
                    </a>
                    <a href="{{ route('reports.graphical.incidents') }}" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3 px-0 border-0 border-bottom">
                        <div class="rounded-circle p-2 bg-warning bg-opacity-10 text-warning">
                            <i class="fas fa-chart-column fa-sm"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-medium">Incident Frequency</div>
                            <div class="text-muted small">Stacked bar — annual 5-year or monthly breakdown by hazard type</div>
                        </div>
                        <span class="badge bg-warning bg-opacity-15 text-warning">Annual · Monthly</span>
                    </a>
                    <a href="{{ route('reports.graphical.households') }}" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3 px-0 border-0 border-bottom">
                        <div class="rounded-circle p-2 bg-success bg-opacity-10 text-success">
                            <i class="fas fa-chart-bar fa-sm"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-medium">Household Registrations</div>
                            <div class="text-muted small">Bar chart — new households per year or per month</div>
                        </div>
                        <span class="badge bg-warning bg-opacity-15 text-warning">Annual · Monthly</span>
                    </a>
                    <a href="{{ route('reports.graphical.hazards') }}" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3 px-0 border-0">
                        <div class="rounded-circle p-2 bg-info bg-opacity-10 text-info">
                            <i class="fas fa-chart-pie fa-sm"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-medium">Hazard Exposure</div>
                            <div class="text-muted small">Doughnut &amp; bar — risk level distribution and top barangays</div>
                        </div>
                        <span class="badge bg-secondary bg-opacity-15 text-secondary">Snapshot</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
