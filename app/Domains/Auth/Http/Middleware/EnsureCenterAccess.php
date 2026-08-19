<?php

namespace App\Domains\Auth\Http\Middleware;

use App\Domains\Auth\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets spatie/laravel-permission's active team for the request to the
 * authenticated user's managed center, if any. super_admin ignores team
 * scoping entirely (see User::isSuperAdmin()), so this only matters for
 * managers.
 */
class EnsureCenterAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user) {
            setPermissionsTeamId($user->managedCenterId());
        }

        return $next($request);
    }
}
