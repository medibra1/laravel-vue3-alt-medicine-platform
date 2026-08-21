<?php

namespace App\Domains\Core\Policies;

use App\Domains\Auth\Models\User;
use App\Domains\Core\Models\Zone;

/**
 * Zones are managed exclusively by super_admin — same reasoning as
 * CenterPolicy: no center manager has a legitimate reason to edit the
 * global zone/country reference list.
 */
class ZonePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : false;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Zone $zone): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Zone $zone): bool
    {
        return false;
    }

    public function delete(User $user, Zone $zone): bool
    {
        return false;
    }
}
