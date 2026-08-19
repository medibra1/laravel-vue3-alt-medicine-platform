<?php

namespace App\Domains\Patients\Http\Controllers\Admin;

use App\Domains\Core\Models\Center;
use App\Domains\Patients\Http\Requests\ConfirmPatientRequest;
use App\Domains\Patients\Http\Requests\StorePatientDraftRequest;
use App\Domains\Patients\Http\Requests\UpdatePatientDraftRequest;
use App\Domains\Patients\Models\Patient;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PatientController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Patient::class);

        $query = Patient::query()->with('center');

        if (! $request->user()->isSuperAdmin()) {
            $query->where('intake_center_id', getPermissionsTeamId());
        }

        $patients = QueryBuilder::for($query)
            ->allowedFilters(
                AllowedFilter::partial('first_name'),
                AllowedFilter::partial('last_name'),
                AllowedFilter::exact('intake_center_id'),
            )
            ->allowedSorts('first_name', 'last_name', 'created_at')
            ->defaultSort('-created_at')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return Inertia::render('Admin/Patients/Index', [
            'patients' => $patients,
            'filters' => $request->only(['filter', 'sort']),
            'centers' => $request->user()->isSuperAdmin() ? Center::query()->orderBy('code')->get(['id', 'name', 'code']) : [],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Patient::class);

        return Inertia::render('Admin/Patients/Form', [
            'patient' => null,
            'centers' => $request->user()->isSuperAdmin() ? Center::query()->orderBy('code')->get(['id', 'name', 'code']) : [],
        ]);
    }

    public function edit(Request $request, Patient $patient): Response
    {
        Gate::authorize('update', $patient);

        return Inertia::render('Admin/Patients/Form', [
            'patient' => $patient,
            'centers' => $request->user()->isSuperAdmin() ? Center::query()->orderBy('code')->get(['id', 'name', 'code']) : [],
        ]);
    }

    /**
     * Idempotent on client_uuid: a lost response to a genuinely
     * successful first save must not create a duplicate Patient row
     * when the frontend's debounce retries.
     */
    public function storeDraft(StorePatientDraftRequest $request): JsonResponse
    {
        $existing = Patient::query()->where('client_uuid', $request->string('client_uuid'))->first();

        if ($existing) {
            Gate::authorize('update', $existing);

            return response()->json(['id' => $existing->id, 'client_uuid' => $existing->client_uuid]);
        }

        $patient = Patient::create([
            ...$request->validated(),
            'intake_center_id' => $request->centerId(),
            'created_by' => $request->user()->id,
        ]);
        $patient->setStatus('draft');

        return response()->json(['id' => $patient->id, 'client_uuid' => $patient->client_uuid], 201);
    }

    public function updateDraft(UpdatePatientDraftRequest $request, Patient $patient): JsonResponse
    {
        $patient->update($request->validated());

        return response()->json(['id' => $patient->id]);
    }

    public function confirm(ConfirmPatientRequest $request, Patient $patient): RedirectResponse
    {
        $patient->update($request->validated());
        $patient->setStatus('confirmed');

        return redirect()->route('admin.patients.index');
    }

    public function destroy(Patient $patient): RedirectResponse
    {
        Gate::authorize('delete', $patient);

        $patient->delete();

        return redirect()->route('admin.patients.index');
    }
}
