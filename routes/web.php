<?php

use App\Http\Controllers\BarangayController;
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

    // ── Module 4: Barangay Management ───────────────────────────────────────
    Route::resource('barangays', BarangayController::class);
    Route::delete('barangays/{barangay}/boundary', [BarangayController::class, 'deleteBoundary'])
        ->name('barangays.boundary.delete');

    // ── API / AJAX endpoints ─────────────────────────────────────────────────
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('barangays/boundaries', [BarangayController::class, 'boundaries'])
            ->name('boundaries');
        Route::get('barangays/staff-users', [BarangayController::class, 'staffUsers'])
            ->name('staff-users');
    });

    // ── Module 5+ routes will be added here ─────────────────────────────────
});
