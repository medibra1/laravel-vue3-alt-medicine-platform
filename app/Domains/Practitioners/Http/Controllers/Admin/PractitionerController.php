<?php

namespace App\Domains\Practitioners\Http\Controllers\Admin;

use App\Domains\Core\Models\Center;
use App\Domains\Core\Models\Grade;
use App\Domains\Practitioners\Http\Requests\StorePractitionerRequest;
use App\Domains\Practitioners\Http\Requests\UpdatePractitionerRequest;
use App\Domains\Practitioners\Models\Practitioner;
use App\Domains\Practitioners\Services\PractitionerCodeGenerator;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PractitionerController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Practitioner::class);

        $query = Practitioner::query()->with(['center.country', 'grade', 'user']);

        if (! $request->user()->isSuperAdmin()) {
            $query->where('center_id', getPermissionsTeamId());
        }

        $practitioners = QueryBuilder::for($query)
            ->allowedFilters(
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($query) use ($value) {
                        $query->where('full_code', 'like', "%{$value}%")
                            ->orWhere('matricule', 'like', "%{$value}%")
                            ->orWhere('first_name', 'like', "%{$value}%")
                            ->orWhere('last_name', 'like', "%{$value}%");
                    });
                }),
                AllowedFilter::exact('grade_id'),
                AllowedFilter::exact('center_id'),
            )
            ->allowedSorts('full_code', 'matricule', 'hired_at', 'created_at')
            ->defaultSort('-created_at')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return Inertia::render('Admin/Practitioners/Index', [
            'practitioners' => $practitioners,
            'filters' => (object) $request->only(['filter', 'sort']),
            'centers' => $request->user()->isSuperAdmin() ? Center::query()->orderBy('code')->get(['id', 'name', 'code']) : [],
            'grades' => Grade::query()->orderBy('order')->get(['id', 'label', 'coefficient']),
        ]);
    }

    /**
     * Suggestion only — the form field stays editable, a manager may
     * want to enter a real diploma/registration number instead.
     */
    public function nextMatricule(Request $request, PractitionerCodeGenerator $generator): JsonResponse
    {
        Gate::authorize('create', Practitioner::class);

        $centerId = $request->user()->isSuperAdmin()
            ? $request->integer('center_id')
            : $request->user()->managedCenterId();

        $center = Center::query()->findOrFail($centerId);

        return response()->json(['matricule' => $generator->suggestNextMatricule($center)]);
    }

    public function store(StorePractitionerRequest $request): RedirectResponse
    {
        Practitioner::create([
            ...$request->validated(),
            'center_id' => $request->centerId(),
        ]);

        return redirect()->route('admin.practitioners.index');
    }

    public function update(UpdatePractitionerRequest $request, Practitioner $practitioner): RedirectResponse
    {
        $practitioner->update($request->validated());

        return redirect()->route('admin.practitioners.index');
    }

    public function destroy(Practitioner $practitioner): RedirectResponse
    {
        Gate::authorize('delete', $practitioner);

        $practitioner->delete();

        return redirect()->route('admin.practitioners.index');
    }
}
