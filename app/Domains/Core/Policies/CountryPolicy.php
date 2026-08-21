<?php

namespace App\Domains\Core\Policies;

use App\Domains\Auth\Models\User;
use App\Domains\Core\Models\Country;

/**
 * Countries are managed exclusively by super_admin — same reasoning as
 * CenterPolicy/ZonePolicy: no center manager has a legitimate reason to
 * edit the global zone/country reference list.
 */
class CountryPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : false;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Country $country): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Country $country): bool
    {
        return false;
    }

    public function delete(User $user, Country $country): bool
    {
        return false;
    }
}
