<?php

namespace App\Domains\Core\Http\Controllers\Admin;

use App\Domains\Core\Http\Requests\StoreCenterClosureRequest;
use App\Domains\Core\Http\Resources\CenterClosureResource;
use App\Domains\Core\Models\Center;
use App\Domains\Core\Models\CenterClosure;
use App\Domains\Scheduling\Models\Appointment;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * No update(): same as PractitionerTimeOffController, a closure is
 * deleted and re-created rather than edited.
 */
class CenterClosureController extends Controller
{
    /**
     * JSON list of a center's closures, optionally only those still
     * current or upcoming (?upcoming=1).
     */
    public function index(Request $request, Center $center): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [CenterClosure::class, $center]);

        $closures = $center->closures()
            ->when($request->boolean('upcoming'), fn ($query) => $query->whereDate('ends_on', '>=', today()))
            ->orderBy('starts_on')
            ->get();

        return CenterClosureResource::collection($closures);
    }

    /**
     * Never cancels appointments already booked in the period — counts
     * them across every practitioner of the center and flashes the count
     * (same flash key as a practitioner time off) so the UI can warn.
     */
    public function store(StoreCenterClosureRequest $request, Center $center): RedirectResponse
    {
        $closure = $center->closures()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        $affectedCount = Appointment::query()
            ->where('center_id', $closure->center_id)
            ->whereNotIn('status', ['cancelled', 'no_show', 'completed'])
            ->whereBetween('starts_at', [$closure->starts_on->copy()->startOfDay(), $closure->ends_on->copy()->endOfDay()])
            ->count();

        return back()->with('time_off_affected_appointments', $affectedCount);
    }

    public function destroy(CenterClosure $closure): RedirectResponse
    {
        Gate::authorize('delete', $closure);

        $closure->delete();

        return back();
    }
}
