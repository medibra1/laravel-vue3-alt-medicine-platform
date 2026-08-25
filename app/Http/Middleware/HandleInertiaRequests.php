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

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'is_super_admin' => $user?->isSuperAdmin() ?? false,
                'is_admin' => $user?->isAdmin() ?? false,
                'is_manager' => $user?->isManager() ?? false,
                'unread_notifications_count' => $user?->unreadNotifications()->count() ?? 0,
                // Only meaningful for a multi-center practitioner —
                // AppCenterSwitcher only renders once there's more than
                // one center to switch between (see EnsureCenterAccess
                // for how the active one is resolved/auto-selected).
                'accessible_centers' => $accessibleCenterIds !== []
                    ? Center::query()->whereIn('id', $accessibleCenterIds)->orderBy('name')->get(['id', 'name'])
                    : [],
                'active_center_id' => $accessibleCenterIds !== [] ? $request->session()->get('active_center_id') : null,
            ],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }
}
