<?php

namespace App\Domains\Patients\Policies;

use App\Domains\Auth\Models\User;
use App\Domains\Patients\Models\CareCategory;

/**
 * Care categories are managed exclusively by super_admin — same
 * reasoning as CenterPolicy: the care catalog is shared reference data
 * across all centers, not something a center manager administers.
 */
class CareCategoryPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : false;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, CareCategory $careCategory): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, CareCategory $careCategory): bool
    {
        return false;
    }

    public function delete(User $user, CareCategory $careCategory): bool
    {
        return false;
    }
}
