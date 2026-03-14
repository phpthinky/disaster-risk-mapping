<?php

namespace App\Policies;

use App\Models\Household;
use App\Models\User;

class HouseholdPolicy
{
    /**
     * All roles can list households (filtered in controller by role).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Admin and division_chief can view any household.
     * Barangay staff can only view households in their barangay.
     */
    public function view(User $user, Household $household): bool
    {
        if ($user->isAdmin() || $user->isDivisionChief()) {
            return true;
        }

        return (int) $user->barangay_id === (int) $household->barangay_id;
    }

    /**
     * Admin can create households in any barangay.
     * Barangay staff can only create in their own barangay.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isBarangayStaff();
    }

    /**
     * Admin can update any household.
     * Barangay staff can only update their own barangay's households.
     */
    public function update(User $user, Household $household): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isBarangayStaff()) {
            return (int) $user->barangay_id === (int) $household->barangay_id;
        }

        return false;
    }

    /**
     * Same rule as update.
     */
    public function delete(User $user, Household $household): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isBarangayStaff()) {
            return (int) $user->barangay_id === (int) $household->barangay_id;
        }

        return false;
    }
}
