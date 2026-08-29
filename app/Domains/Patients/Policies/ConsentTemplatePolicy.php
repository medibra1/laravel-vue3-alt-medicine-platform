<?php

namespace App\Domains\Patients\Policies;

use App\Domains\Auth\Models\User;
use App\Domains\Patients\Models\ConsentTemplate;

/**
 * Consent templates are global reference data (the legal/consent text
 * shown to every patient regardless of center) — managed by
 * super_admin/admin, same reasoning as CenterPolicy: no center-scoped
 * delegation makes sense for cross-center referential content.
 */
class ConsentTemplatePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() || $user->isAdmin() ? true : false;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, ConsentTemplate $consentTemplate): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ConsentTemplate $consentTemplate): bool
    {
        return false;
    }

    public function delete(User $user, ConsentTemplate $consentTemplate): bool
    {
        return false;
    }
}
