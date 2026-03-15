@extends('layouts.app')

@section('title', 'Edit Incident Report')

@section('page-header')
<div class="mb-3">
    <h4 class="page-title mb-0"><i class="fas fa-edit me-2"></i>Edit Incident Report</h4>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('incidents.index') }}">Incident Reports</a></li>
            <li class="breadcrumb-item"><a href="{{ route('incidents.show', $incident) }}">{{ $incident->title }}</a></li>
            <li class="breadcrumb-item active">Edit</li>
        </ol>
    </nav>
</div>
@endsection

@section('content')
@include('incidents._form', [
    'incident' => $incident,
    'hazardTypes' => $hazardTypes,
    'action' => route('incidents.update', $incident),
    'method' => 'PUT',
])
@endsection
