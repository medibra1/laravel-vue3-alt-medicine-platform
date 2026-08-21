<?php

namespace App\Domains\Patients\Policies;

use App\Domains\Auth\Models\User;
use App\Domains\Patients\Models\CareItem;

/**
 * Care items are managed exclusively by super_admin — same reasoning
 * as CareCategoryPolicy/CenterPolicy: shared reference data, not
 * delegated to center managers.
 */
class CareItemPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : false;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, CareItem $careItem): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, CareItem $careItem): bool
    {
        return false;
    }

    public function delete(User $user, CareItem $careItem): bool
    {
        return false;
    }
}
