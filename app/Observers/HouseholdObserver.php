<?php

namespace App\Observers;

use App\Models\Household;
use App\Services\SyncService;

class HouseholdObserver
{
    /**
     * Handle the Household "saved" event (created + updated).
     * Triggers the full sync chain for the household's barangay.
     */
    public function saved(Household $household): void
    {
        SyncService::handleSync((int) $household->barangay_id);
    }

    /**
     * Handle the Household "deleted" event.
     * Triggers the full sync chain for the household's barangay.
     */
    public function deleted(Household $household): void
    {
        SyncService::handleSync((int) $household->barangay_id);
    }
}
