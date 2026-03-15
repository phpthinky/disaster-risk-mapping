@extends('layouts.app')

@section('title', 'Edit Evacuation Center')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="page-title mb-0">Edit: {{ $evacuation->name }}</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('evacuations.index') }}">Evacuation Centers</a></li>
                <li class="breadcrumb-item"><a href="{{ route('evacuations.show', $evacuation) }}">{{ $evacuation->name }}</a></li>
                <li class="breadcrumb-item active">Edit</li>
            </ol>
        </nav>
    </div>
    <a href="{{ route('evacuations.show', $evacuation) }}" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i> Back
    </a>
</div>
@endsection

@section('content')
<form method="POST" action="{{ route('evacuations.update', $evacuation) }}">
    @csrf
    @method('PUT')
    @include('evacuations._form')
    <div class="mt-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-1"></i> Update Center
        </button>
        <a href="{{ route('evacuations.show', $evacuation) }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
@endsection
