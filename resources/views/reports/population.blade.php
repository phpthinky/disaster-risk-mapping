@extends('layouts.app')

@section('title', 'Population Summary Report')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="page-title mb-0"><i class="fas fa-users me-2"></i>Population Summary</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
                <li class="breadcrumb-item active">Population Summary</li>
            </ol>
        </nav>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('reports.excel', 'population') }}" class="btn btn-success btn-sm">
            <i class="fas fa-file-excel me-1"></i> Export Excel
        </a>
        <a href="{{ route('reports.pdf', 'population') }}" class="btn btn-danger btn-sm">
            <i class="fas fa-file-pdf me-1"></i> Export PDF
        </a>
    </div>
</div>
@endsection

@section('content')

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Barangay</th>
                        <th class="text-end">Total Pop.</th>
                        <th class="text-end">Households</th>
                        <th class="text-end">PWD</th>
                        <th class="text-end">Senior (60+)</th>
                        <th class="text-end">Infant (0–2)</th>
                        <th class="text-end">Pregnant</th>
                        <th class="text-end">IP</th>
                        <th class="text-end">At-Risk</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($barangays as $b)
                    <tr>
                        <td class="fw-medium">{{ $b->name }}</td>
                        <td class="text-end">{{ number_format($b->population) }}</td>
                        <td class="text-end">{{ number_format($b->household_count) }}</td>
                        <td class="text-end">{{ number_format($b->pwd_count) }}</td>
                        <td class="text-end">{{ number_format($b->senior_count) }}</td>
                        <td class="text-end">{{ number_format($b->infant_count) }}</td>
                        <td class="text-end">{{ number_format($b->pregnant_count) }}</td>
                        <td class="text-end">{{ number_format($b->ip_count) }}</td>
                        <td class="text-end">{{ number_format($b->at_risk_count) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">No barangay data found.</td></tr>
                    @endforelse
                </tbody>
                @if($barangays->isNotEmpty())
                <tfoot class="table-secondary fw-bold">
                    <tr>
                        <td>TOTAL</td>
                        <td class="text-end">{{ number_format($barangays->sum('population')) }}</td>
                        <td class="text-end">{{ number_format($barangays->sum('household_count')) }}</td>
                        <td class="text-end">{{ number_format($barangays->sum('pwd_count')) }}</td>
                        <td class="text-end">{{ number_format($barangays->sum('senior_count')) }}</td>
                        <td class="text-end">{{ number_format($barangays->sum('infant_count')) }}</td>
                        <td class="text-end">{{ number_format($barangays->sum('pregnant_count')) }}</td>
                        <td class="text-end">{{ number_format($barangays->sum('ip_count')) }}</td>
                        <td class="text-end">{{ number_format($barangays->sum('at_risk_count')) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>

@endsection
