@extends('layouts.app')

@section('title', 'New Incident Report')

@section('page-header')
<div class="mb-3">
    <h4 class="page-title mb-0"><i class="fas fa-plus me-2"></i>New Incident Report</h4>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('incidents.index') }}">Incident Reports</a></li>
            <li class="breadcrumb-item active">New</li>
        </ol>
    </nav>
</div>
@endsection

@section('content')
@include('incidents._form', [
    'incident' => new \App\Models\IncidentReport,
    'hazardTypes' => $hazardTypes,
    'action' => route('incidents.store'),
    'method' => 'POST',
])
@endsection
