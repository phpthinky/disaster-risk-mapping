<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// ── Public routes ───────────────────────────────────────────────────────────
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// Auth routes (login/logout/password reset only — no register)
Auth::routes(['register' => false, 'verify' => false]);

// ── Authenticated routes ────────────────────────────────────────────────────
Route::middleware(['auth', 'active'])->group(function () {

    // Dashboard — role-specific
    Route::get('/dashboard', [DashboardController::class, 'admin'])
        ->middleware('role:admin')
        ->name('dashboard');

    Route::get('/dashboard/division', [DashboardController::class, 'division'])
        ->middleware('role:admin,division_chief')
        ->name('dashboard.division');

    Route::get('/dashboard/barangay', [DashboardController::class, 'barangay'])
        ->middleware('role:admin,barangay_staff')
        ->name('dashboard.barangay');

    // ── Module 4+ routes will be added here ─────────────────────────────────
});
