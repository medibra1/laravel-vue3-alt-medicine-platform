<?php

namespace App\Domains\Patients\Policies;

use App\Domains\Auth\Models\User;
use App\Domains\Patients\Models\Disease;

/**
 * Diseases are a shared reference list (used across every center) —
 * managed exclusively by super_admin, same shape as CenterPolicy: no
 * manager delegation, before() hardcoded true/false.
 */
class DiseasePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : false;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Disease $disease): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Disease $disease): bool
    {
        return false;
    }

    public function delete(User $user, Disease $disease): bool
    {
        return false;
    }
}
