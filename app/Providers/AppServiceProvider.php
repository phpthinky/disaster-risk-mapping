<?php

namespace App\Providers;

use App\Models\Barangay;
use App\Models\Household;
use App\Observers\HouseholdObserver;
use App\Policies\BarangayPolicy;
use App\Policies\HouseholdPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Household::observe(HouseholdObserver::class);

        Gate::policy(Barangay::class, BarangayPolicy::class);
        Gate::policy(Household::class, HouseholdPolicy::class);
    }
}
