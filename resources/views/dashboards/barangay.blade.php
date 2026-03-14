@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <h4>Barangay Dashboard</h4>
    <p class="text-muted">Welcome, {{ auth()->user()->name ?? auth()->user()->username }}.</p>
    {{-- Module 12 will build this out fully --}}
</div>
@endsection
