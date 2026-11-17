<?php

namespace App\Domains\Reporting\Http\Controllers;

use App\Domains\Core\Models\Center;
use App\Domains\Reporting\Services\DashboardStats;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardStats $stats): Response
    {
        $user = $request->user();

        // Global admins see every center by default and may narrow to one;
        // everyone else is always confined to their active center.
        if ($user->isGlobalAdmin()) {
            $centerId = $request->filled('center_id') ? $request->integer('center_id') : null;
            $centers = Center::query()->orderBy('name')->get(['id', 'name']);
        } else {
            $centerId = getPermissionsTeamId();
            $centers = [];
            abort_if($centerId === null, 403);
        }

        // Day whose figures are shown (today unless another is picked).
        $day = $request->date('day') ?? now();

        if ($day->isFuture()) {
            $day = now();
        }

        return Inertia::render('Dashboard', [
            'stats' => $stats->handle($user, $centerId, $user->enabledModules($centerId)),
            'daily' => $stats->day($user, $centerId, $user->enabledModules($centerId), Carbon::parse($day)),
            'centers' => $centers,
            'selectedCenterId' => $user->isGlobalAdmin() ? $centerId : null,
        ]);
    }
}
