<?php

namespace App\Domains\Scheduling\Http\Controllers\Admin;

use App\Domains\Practitioners\Models\Practitioner;
use App\Domains\Scheduling\Http\Requests\StorePractitionerTimeOffRequest;
use App\Domains\Scheduling\Http\Resources\PractitionerTimeOffResource;
use App\Domains\Scheduling\Models\Appointment;
use App\Domains\Scheduling\Models\PractitionerTimeOff;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * No update(): editing a time off means deleting and re-creating it —
 * avoids diffing date ranges against appointments already warned about.
 */
class PractitionerTimeOffController extends Controller
{
    /**
     * JSON list of one practitioner's time offs, optionally only those
     * still current or upcoming (?upcoming=1).
     */
    public function index(Request $request, Practitioner $practitioner): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', PractitionerTimeOff::class);
        abort_unless($request->user()->isSuperAdmin() || $practitioner->center_id === getPermissionsTeamId(), 403);

        $timeOffs = $practitioner->timeOffs()
            ->when($request->boolean('upcoming'), fn ($query) => $query->whereDate('ends_on', '>=', today()))
            ->orderBy('starts_on')
            ->get();

        return PractitionerTimeOffResource::collection($timeOffs);
    }

    /**
     * Never cancels appointments already booked in the period: it only
     * counts them and flashes the count so the UI can warn — a human
     * decides what to do with each one.
     */
    // $practitioner is type-hinted so route model binding resolves it before
    // StorePractitionerTimeOffRequest::prepareForValidation() reads it.
    public function store(StorePractitionerTimeOffRequest $request, Practitioner $practitioner): RedirectResponse
    {
        $timeOff = PractitionerTimeOff::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        $affectedCount = Appointment::query()
            ->where('practitioner_id', $timeOff->practitioner_id)
            ->whereNotIn('status', ['cancelled', 'no_show', 'completed'])
            ->whereBetween('starts_at', [$timeOff->starts_on->copy()->startOfDay(), $timeOff->ends_on->copy()->endOfDay()])
            ->count();

        return back()->with('time_off_affected_appointments', $affectedCount);
    }

    public function destroy(PractitionerTimeOff $timeOff): RedirectResponse
    {
        Gate::authorize('delete', $timeOff);

        $timeOff->delete();

        return back();
    }
}
