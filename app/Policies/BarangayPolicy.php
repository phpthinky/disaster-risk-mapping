<?php

namespace App\Policies;

use App\Models\Barangay;
use App\Models\User;

class BarangayPolicy
{
    /**
     * All roles can view any barangay listing.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * All roles can view a specific barangay.
     */
    public function view(User $user, Barangay $barangay): bool
    {
        return true;
    }

    /**
     * Only admin can create barangays.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Admin can update any barangay.
     * Barangay staff can only update their own barangay (e.g. boundary drawing).
     */
    public function update(User $user, Barangay $barangay): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isBarangayStaff()) {
            return (int) $user->barangay_id === (int) $barangay->id;
        }

        return false;
    }

    /**
     * Only admin can delete barangays.
     */
    public function delete(User $user, Barangay $barangay): bool
    {
        return $user->isAdmin();
    }
}
