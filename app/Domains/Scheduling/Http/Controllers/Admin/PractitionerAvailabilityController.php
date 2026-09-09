<?php

namespace App\Domains\Scheduling\Http\Controllers\Admin;

use App\Domains\Practitioners\Http\Concerns\ResolvesPractitionerOptions;
use App\Domains\Scheduling\Http\Requests\StorePractitionerAvailabilityRequest;
use App\Domains\Scheduling\Http\Requests\UpdatePractitionerAvailabilityRequest;
use App\Domains\Scheduling\Http\Resources\PractitionerAvailabilityResource;
use App\Domains\Scheduling\Models\PractitionerAvailability;
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
        ]);
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
}
