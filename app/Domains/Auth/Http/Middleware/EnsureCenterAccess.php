<?php

namespace App\Domains\Auth\Http\Middleware;

use App\Domains\Auth\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets spatie/laravel-permission's active team for the request.
 * super_admin/admin ignore team scoping entirely (see
 * User::isSuperAdmin()/isAdmin()). A manager is scoped to their single
 * managed center. A practitioner may be scoped to several centers —
 * the active one is resolved from session('active_center_id') (see
 * ActiveCenterController), auto-selecting the first accessible center
 * the first time (or if the session value is no longer valid) rather
 * than blocking on an explicit choice screen.
 */
class EnsureCenterAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user) {
            setPermissionsTeamId($this->resolveTeamId($request, $user));
        }

        return $next($request);
    }

    private function resolveTeamId(Request $request, User $user): ?int
    {
        if ($managedCenterId = $user->managedCenterId()) {
            return $managedCenterId;
        }

        $accessibleCenterIds = $user->accessibleCenterIds();

        if ($accessibleCenterIds === []) {
            return null;
        }

        $activeCenterId = $request->session()->get('active_center_id');

        if (is_int($activeCenterId) && in_array($activeCenterId, $accessibleCenterIds, true)) {
            return $activeCenterId;
        }

        // No valid selection yet — auto-select rather than force an
        // interstitial "choose your center" screen; the practitioner
        // can switch centers at any time via AppCenterSwitcher.
        $defaultCenterId = $accessibleCenterIds[0];
        $request->session()->put('active_center_id', $defaultCenterId);

        return $defaultCenterId;
    }
}
