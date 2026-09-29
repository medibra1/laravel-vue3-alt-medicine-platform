<?php

namespace App\Domains\Core\Policies;

use App\Domains\Auth\Models\User;
use App\Domains\Core\Models\Center;
use App\Domains\Core\Models\CenterClosure;

/**
 * Same audience as a center's opening hours: super_admin/admin for any
 * center, a manager only for the center they manage and have active
 * (CenterPolicy::manageOperatingHours()).
 */
class CenterClosurePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() || $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user, Center $center): bool
    {
        return $user->can('manageOperatingHours', $center);
    }

    public function create(User $user, Center $center): bool
    {
        return $user->can('manageOperatingHours', $center);
    }

    public function delete(User $user, CenterClosure $closure): bool
    {
        return $user->can('manageOperatingHours', $closure->center);
    }
}
