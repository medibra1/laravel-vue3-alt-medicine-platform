<?php

namespace App\Domains\Core\Policies;

use App\Domains\Auth\Models\User;
use App\Domains\Core\Models\Center;

/**
 * Centers are managed by super_admin and admin — a center manager
 * administers practitioners/patients *within* their center, not the
 * center record itself (creating a center is how a new center gets
 * a manager in the first place).
 */
class CenterPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        // null (not false) for everyone else so manageOperatingHours() below
        // still runs; every other ability returns false on its own.
        return $user->isSuperAdmin() || $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Center $center): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Center $center): bool
    {
        return false;
    }

    public function delete(User $user, Center $center): bool
    {
        return false;
    }

    /**
     * Opening hours are the one center setting a manager may edit — only
     * for the center they manage and currently have active.
     */
    public function manageOperatingHours(User $user, Center $center): bool
    {
        return $center->id === getPermissionsTeamId()
            && in_array($center->id, $user->managedCenterIds(), true);
    }
}
