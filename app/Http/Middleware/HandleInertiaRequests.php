<?php

namespace App\Http\Middleware;

use App\Domains\Core\Models\Center;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $accessibleCenterIds = $user?->accessibleCenterIds() ?? [];
        // Resolved lazily, at response time: `center.access` (a route
        // middleware, so it runs after this one) is what puts the active
        // center in session on the very first request. Routes outside
        // that group (profile...) fall back to the first accessible center.
        $activeCenterId = function () use ($request, $accessibleCenterIds): ?int {
            $selected = $request->session()->get('active_center_id');

            return is_int($selected) && in_array($selected, $accessibleCenterIds, true)
                ? $selected
                : ($accessibleCenterIds[0] ?? null);
        };

        return [
            ...parent::share($request),
            'locale' => app()->getLocale(),
            'auth' => [
                'user' => $user,
                'is_super_admin' => $user?->isSuperAdmin() ?? false,
                'is_admin' => $user?->isAdmin() ?? false,
                'is_manager' => $user?->isManager() ?? false,
                'is_global_admin' => $user?->isGlobalAdmin() ?? false,
                'is_practitioner' => $user?->isPractitioner() ?? false,
                'modules' => fn () => $user?->enabledModules($activeCenterId()) ?? [],
                'active_center' => function () use ($activeCenterId) {
                    $center = $activeCenterId() !== null ? Center::query()->find($activeCenterId()) : null;

                    return $center ? [
                        'id' => $center->id,
                        'name' => $center->name,
                        'currency' => $center->currency,
                        'timezone' => $center->timezone,
                    ] : null;
                },
                'unread_notifications_count' => $user?->unreadNotifications()->count() ?? 0,
                // Only meaningful for someone with more than one
                // accessible center (a multi-center practitioner, or —
                // extended 2026-08-26 — a manager managing several
                // centers) — AppCenterSwitcher only renders once
                // there's more than one to switch between (see
                // EnsureCenterAccess for how the active one is
                // resolved/auto-selected).
                'accessible_centers' => $accessibleCenterIds !== []
                    ? Center::query()->whereIn('id', $accessibleCenterIds)->orderBy('name')->get(['id', 'name'])
                    : [],
                'active_center_id' => $activeCenterId,
            ],
            'flash' => [
                // Set by PractitionerTimeOffController::store().
                'time_off_affected_appointments' => fn () => $request->session()->get('time_off_affected_appointments'),
            ],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }
}
