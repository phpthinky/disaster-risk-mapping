@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="page-title mb-0">Dashboard</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
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
        Admin Dashboard — full stats and charts will be built in <strong>Module 12</strong>.
    </div>
</div>
@endsection
