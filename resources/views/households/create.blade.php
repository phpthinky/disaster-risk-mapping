@extends('layouts.app')

@section('title', 'Add Household')

@section('page-header')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="page-title mb-0">Add Household</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('households.index') }}">Households</a></li>
                <li class="breadcrumb-item active">Add</li>
            </ol>
        </nav>
    </div>
    <a href="{{ route('households.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i> Back
    </a>
</div>
@endsection

@section('content')
@include('households._form', ['household' => null])
@endsection
