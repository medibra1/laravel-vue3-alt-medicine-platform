<?php

namespace App\Domains\Auth\Http\Middleware;

use App\Domains\Auth\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `module:<key>` — 403 when the module is switched off for the active
 * center or for this user. Must run after `center.access`.
 */
class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        abort_unless($user && $user->hasModule($module, getPermissionsTeamId()), 403, __('Ce module n\'est pas activé.'));

        return $next($request);
    }
}
