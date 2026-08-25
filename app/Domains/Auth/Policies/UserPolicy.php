<?php

namespace App\Domains\Auth\Policies;

use App\Domains\Auth\Models\User;

class UserPolicy
{
    /**
     * super_admin and admin both bypass every ability below — never
     * $user->can('users.*') (spatie's permission resolution is
     * team-scoped the same way hasRole() is: it filters by the
     * *currently active* team, which EnsureCenterAccess sets to null
     * for a non-manager, while admin/super_admin roles live under the
     * sentinel team 0 — so can() would always resolve false for them
     * regardless of what's actually synced). update()/delete() still
     * need to inspect the *target* user's own role, so those two stay
     * outside before() rather than being fully short-circuited by it.
     */
    public function before(User $user, string $ability): ?bool
    {
        if (in_array($ability, ['update', 'delete'], true)) {
            return $user->isSuperAdmin() ? true : null;
        }

        return $user->isSuperAdmin() || $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, User $target): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    /**
     * An admin may only manage manager accounts — never another
     * admin/super_admin. super_admin already bypassed via before().
     */
    public function update(User $user, User $target): bool
    {
        return $user->isAdmin() && $target->isManager() && ! $target->isAdmin() && ! $target->isSuperAdmin();
    }

    public function delete(User $user, User $target): bool
    {
        return $user->isAdmin() && $target->isManager() && ! $target->isAdmin() && ! $target->isSuperAdmin();
    }
}
