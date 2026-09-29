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
use App\Domains\Scheduling\Models\PractitionerAvailability;
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

        return Inertia::render('Admin/Scheduling/Availabilities/Index', [
            'availabilities' => PractitionerAvailabilityResource::collection($availabilities),
            'practitioners' => $this->practitionerOptions($request),
            'activeCenter' => $this->editableActiveCenter($request),
        ]);
    }

    /**
     * The manager's active center with its opening hours, so they can edit
     * them next to the practitioner schedules. super_admin/admin edit hours
     * from the centers page instead (they have no single active center).
     *
     * @return array<string, mixed>|null
     */
    private function editableActiveCenter(Request $request): ?array
    {
        $center = Center::query()->with('operatingHours')->find(getPermissionsTeamId());

        if ($center === null || $request->user()->isSuperAdmin() || $request->user()->isAdmin()
            || ! $request->user()->can('manageOperatingHours', $center)) {
            return null;
        }

        return [
            'id' => $center->id,
            'name' => $center->name,
            'operating_hours' => CenterOperatingHoursResource::collection($center->operatingHours)->resolve(),
        ];
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
