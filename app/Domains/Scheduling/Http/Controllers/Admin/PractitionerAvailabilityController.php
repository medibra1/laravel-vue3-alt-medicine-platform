<?php

namespace App\Domains\Scheduling\Http\Controllers\Admin;

use App\Domains\Core\Http\Resources\CenterOperatingHoursResource;
use App\Domains\Core\Models\Center;
use App\Domains\Practitioners\Http\Concerns\ResolvesPractitionerOptions;
use App\Domains\Practitioners\Models\Practitioner;
use App\Domains\Scheduling\Http\Requests\BulkSyncPractitionerAvailabilitiesRequest;
use App\Domains\Scheduling\Http\Requests\StorePractitionerAvailabilityRequest;
use App\Domains\Scheduling\Http\Requests\SyncPractitionerAvailabilitiesRequest;
use App\Domains\Scheduling\Http\Requests\UpdatePractitionerAvailabilityRequest;
use App\Domains\Scheduling\Http\Resources\PractitionerAvailabilityResource;
use App\Domains\Scheduling\Http\Resources\PractitionerTimeOffResource;
use App\Domains\Scheduling\Models\PractitionerAvailability;
use App\Domains\Scheduling\Models\PractitionerTimeOff;
use App\Domains\Scheduling\Services\BulkSyncPractitionerAvailabilitiesAction;
use App\Domains\Scheduling\Services\SyncPractitionerAvailabilitiesAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PractitionerAvailabilityController extends Controller
{
    use ResolvesPractitionerOptions;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', PractitionerAvailability::class);

        $query = PractitionerAvailability::query()->with('practitioner');

        if (! $request->user()->isSuperAdmin()) {
            $query->whereHas('practitioner', fn ($q) => $q->where('center_id', getPermissionsTeamId()));
        }

        $availabilities = $query->orderBy('practitioner_id')->orderBy('day_of_week')->get();

        // Current and upcoming time offs only — past ones are history.
        $timeOffs = PractitionerTimeOff::query()
            ->whereDate('ends_on', '>=', today())
            ->when(! $request->user()->isSuperAdmin(), fn ($q) => $q->whereHas('practitioner', fn ($q) => $q->where('center_id', getPermissionsTeamId())))
            ->orderBy('starts_on')
            ->get();

        return Inertia::render('Admin/Scheduling/Availabilities/Index', [
            'availabilities' => PractitionerAvailabilityResource::collection($availabilities),
            'timeOffs' => PractitionerTimeOffResource::collection($timeOffs),
            'practitioners' => $this->practitionerOptions($request),
            'editableCenters' => $this->editableCenters($request),
        ]);
    }

    /**
     * Centers whose opening hours this user may edit from this page:
     * every center for super_admin/admin, the active one for a manager.
     *
     * @return array<int, array<string, mixed>>
     */
    private function editableCenters(Request $request): array
    {
        $user = $request->user();

        $centers = $user->isSuperAdmin() || $user->isAdmin()
            ? Center::query()->with('operatingHours')->orderBy('name')->get()
            : Center::query()->with('operatingHours')->whereKey(getPermissionsTeamId())->get()
                ->filter(fn (Center $center) => $user->can('manageOperatingHours', $center));

        return $centers->map(fn (Center $center) => [
            'id' => $center->id,
            'name' => $center->name,
            'operating_hours' => CenterOperatingHoursResource::collection($center->operatingHours)->resolve(),
        ])->values()->all();
    }

    public function store(StorePractitionerAvailabilityRequest $request): RedirectResponse
    {
        PractitionerAvailability::create($request->validated());

        return redirect()->route('admin.availabilities.index');
    }

    public function update(UpdatePractitionerAvailabilityRequest $request, PractitionerAvailability $availability): RedirectResponse
    {
        $availability->update($request->validated());

        return redirect()->route('admin.availabilities.index');
    }

    public function destroy(PractitionerAvailability $availability): RedirectResponse
    {
        Gate::authorize('delete', $availability);

        $availability->delete();

        return redirect()->route('admin.availabilities.index');
    }

    /**
     * Replaces one practitioner's whole weekly schedule.
     */
    public function sync(SyncPractitionerAvailabilitiesRequest $request, Practitioner $practitioner, SyncPractitionerAvailabilitiesAction $action): RedirectResponse
    {
        $action->handle($practitioner, $request->validated('slots'));

        return redirect()->route('admin.availabilities.index');
    }

    /**
     * Applies one weekly schedule to several practitioners at once.
     */
    public function bulkSync(BulkSyncPractitionerAvailabilitiesRequest $request, BulkSyncPractitionerAvailabilitiesAction $action): RedirectResponse
    {
        $practitioners = Practitioner::query()->whereIn('id', $request->validated('practitioner_ids'))->get();

        $action->handle($practitioners, $request->validated('slots'));

        return redirect()->route('admin.availabilities.index');
    }
}
