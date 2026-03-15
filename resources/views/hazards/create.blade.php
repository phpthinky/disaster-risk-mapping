@extends('layouts.app')

@section('title', 'Add Hazard Zone')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="page-title mb-0">Add Hazard Zone</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('hazards.index') }}">Hazard Zones</a></li>
                <li class="breadcrumb-item active">Add</li>
            </ol>
        </nav>
    </div>
    <a href="{{ route('hazards.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i> Back
    </a>
</div>
@endsection

@section('content')
<form method="POST" action="{{ route('hazards.store') }}">
    @csrf
    @include('hazards._form', ['hazard' => null, 'riskLevels' => \App\Models\HazardZone::RISK_LEVELS])

    <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-1"></i> Save Hazard Zone
        </button>
        <a href="{{ route('hazards.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
@endsection
