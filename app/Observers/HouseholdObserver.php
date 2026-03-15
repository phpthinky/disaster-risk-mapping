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
        // Recompute this household's own demographic columns (senior_count,
        // pwd_count, ip_count, etc.) from the head's fields + members BEFORE
        // aggregating up to the barangay. Without this, changing the head's
        // age/birthday had no effect on the barangay totals.
        SyncService::recomputeHousehold((int) $household->id);
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
