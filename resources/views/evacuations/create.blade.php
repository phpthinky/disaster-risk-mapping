@extends('layouts.app')

@section('title', 'Add Evacuation Center')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="page-title mb-0">Add Evacuation Center</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('evacuations.index') }}">Evacuation Centers</a></li>
                <li class="breadcrumb-item active">Add</li>
            </ol>
        </nav>
    </div>
    <a href="{{ route('evacuations.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i> Back
    </a>
</div>
@endsection

@section('content')
<form method="POST" action="{{ route('evacuations.store') }}">
    @csrf
    @include('evacuations._form', ['evacuation' => null])
    <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-1"></i> Save Center
        </button>
        <a href="{{ route('evacuations.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
@endsection
