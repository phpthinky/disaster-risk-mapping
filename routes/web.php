<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\BarangayController;
use App\Http\Controllers\EvacuationCenterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HazardZoneController;
use App\Http\Controllers\HouseholdController;
use App\Http\Controllers\HouseholdMemberController;
use App\Http\Controllers\IncidentReportController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\PopulationDataController;
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

    // ── Module 5: Household Management ──────────────────────────────────────
    Route::middleware('role:admin,barangay_staff')->group(function () {
        Route::get('households/export', [HouseholdController::class, 'export'])
            ->name('households.export');
        Route::resource('households', HouseholdController::class);
    });

    // ── API / AJAX: Household Members ─────────────────────────────────────
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('barangays/boundaries', [BarangayController::class, 'boundaries'])
            ->name('boundaries');
        Route::get('barangays/staff-users', [BarangayController::class, 'staffUsers'])
            ->name('staff-users');
        Route::get('households/{household}/members',  [HouseholdMemberController::class, 'index'])
            ->name('members.index');
        Route::post('households/{household}/members', [HouseholdMemberController::class, 'store'])
            ->name('members.store');
        Route::put('household-members/{member}',     [HouseholdMemberController::class, 'update'])
            ->name('members.update');
        Route::delete('household-members/{member}',  [HouseholdMemberController::class, 'destroy'])
            ->name('members.destroy');
    });

    // ── Module 6: Hazard Zones ───────────────────────────────────────────────
    Route::resource('hazards', HazardZoneController::class);
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('hazard-zones/geojson', [HazardZoneController::class, 'geojson'])
            ->name('hazards.geojson');
    });

    // ── Module 7: Population Data (read-only + admin snapshot) ──────────────
    Route::get('population', [PopulationDataController::class, 'index'])
        ->name('population.index');
    Route::get('population/{barangay}', [PopulationDataController::class, 'show'])
        ->name('population.show');
    Route::post('population/{barangay}/snapshot', [PopulationDataController::class, 'snapshot'])
        ->middleware('role:admin')
        ->name('population.snapshot');

    // ── Module 8: Incident Reports ───────────────────────────────────────────
    // All authenticated users can view; write access checked in controller.
    Route::resource('incidents', IncidentReportController::class);

    // API: map data for incident form & show map
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('incidents/map-data', [IncidentReportController::class, 'mapData'])
            ->name('incidents.map-data');
    });

    // ── Module 9: Map View ───────────────────────────────────────────────────
    Route::get('map', [MapController::class, 'index'])->name('map.index');

    Route::prefix('api/map')->name('api.map.')->group(function () {
        Route::get('barangays',          [MapController::class, 'apiBarangays'])        ->name('barangays');
        Route::get('hazard-zones',       [MapController::class, 'apiHazardZones'])      ->name('hazard-zones');
        Route::get('households',         [MapController::class, 'apiHouseholds'])       ->name('households');
        Route::get('incidents',          [MapController::class, 'apiIncidents'])        ->name('incidents');
        Route::get('evacuation-centers', [MapController::class, 'apiEvacuationCenters'])->name('evacuation-centers');
    });

    // ── Module 11: Evacuation Centers ───────────────────────────────────────
    Route::resource('evacuations', EvacuationCenterController::class);

    // ── Module 10: Alerts & Announcements ───────────────────────────────────
    Route::resource('alerts', AlertController::class)
        ->except(['create', 'edit', 'show'])
        ->middleware('role:admin');
    Route::patch('alerts/{alert}/toggle', [AlertController::class, 'toggle'])
        ->middleware('role:admin')
        ->name('alerts.toggle');

    Route::resource('announcements', AnnouncementController::class)
        ->except(['create', 'edit', 'show']);
    Route::patch('announcements/{announcement}/toggle', [AnnouncementController::class, 'toggle'])
        ->name('announcements.toggle');
});
