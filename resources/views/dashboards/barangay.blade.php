@extends('layouts.app')

@section('title', 'Barangay Dashboard')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="page-title mb-0">Barangay Dashboard</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                @if(auth()->user()->barangay)
                    <li class="breadcrumb-item">{{ auth()->user()->barangay->name }}</li>
                @endif
                <li class="breadcrumb-item active">Overview</li>
            </ol>
        </nav>
    </div>
    <div class="text-muted" style="font-size:.82rem;">
        <i class="fas fa-calendar me-1"></i>{{ now()->format('l, F j, Y') }}
    </div>
</div>
@endsection

@section('content')
<div class="fade-in-up">
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>
        Barangay Staff Dashboard — barangay-specific stats will be built in <strong>Module 12</strong>.
    </div>
</div>
@endsection
