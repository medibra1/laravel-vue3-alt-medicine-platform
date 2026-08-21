<?php

namespace App\Domains\Common\Policies;

use App\Domains\Auth\Models\User;
use App\Domains\Common\Models\EnumOption;

/**
 * EnumOption drives dropdowns/classifications shared across domains
 * (disease_category.type, payroll_organism.type...) — managed
 * exclusively by super_admin, same reasoning as CenterPolicy: no
 * center-scoped delegation makes sense for a cross-domain referential.
 */
class EnumOptionPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : false;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, EnumOption $enumOption): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, EnumOption $enumOption): bool
    {
        return false;
    }

    public function delete(User $user, EnumOption $enumOption): bool
    {
        return false;
    }
}
