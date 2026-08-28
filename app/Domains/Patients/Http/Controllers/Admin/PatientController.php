<?php

namespace App\Domains\Patients\Http\Controllers\Admin;

use App\Domains\Common\Models\EnumOption;
use App\Domains\Core\Http\Concerns\ResolvesCenterOptions;
use App\Domains\Core\Models\Center;
use App\Domains\Patients\Http\Requests\ConfirmPatientRequest;
use App\Domains\Patients\Http\Requests\StorePatientDraftRequest;
use App\Domains\Patients\Http\Requests\UpdatePatientDraftRequest;
use App\Domains\Patients\Http\Resources\CareCategoryResource;
use App\Domains\Patients\Http\Resources\DiseaseCategoryResource;
use App\Domains\Patients\Http\Resources\DiseaseResource;
use App\Domains\Patients\Http\Resources\TreatmentResource;
use App\Domains\Patients\Models\CareCategory;
use App\Domains\Patients\Models\Disease;
use App\Domains\Patients\Models\DiseaseCategory;
use App\Domains\Patients\Models\Patient;
use App\Domains\Patients\Services\PatientNumberGenerator;
use App\Domains\Practitioners\Http\Concerns\ResolvesPractitionerOptions;
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
    use ResolvesCenterOptions;
    use ResolvesPractitionerOptions;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Patient::class);

        $query = Patient::query()->with('center.country');

        if (! $request->user()->isSuperAdmin()) {
            $query->where('intake_center_id', getPermissionsTeamId());
        }

        $patients = QueryBuilder::for($query)
            ->allowedFilters(
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($query) use ($value) {
                        $query->where('first_name', 'like', "%{$value}%")
                            ->orWhere('last_name', 'like', "%{$value}%");
                    });
                }),
                AllowedFilter::exact('intake_center_id'),
            )
            ->allowedSorts('first_name', 'last_name', 'created_at')
            ->defaultSort('-created_at')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return Inertia::render('Admin/Patients/Index', [
            'patients' => $patients,
            'filters' => (object) $request->only(['filter', 'sort']),
            'centers' => $this->centerOptions($request),
            // A read-only practitioner (patients.view but not
            // patients.create/update) sees the same list — the template
            // hides create/edit affordances based on this rather than
            // guessing from a role name client-side.
            'can_create' => Gate::allows('create', Patient::class),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Patient::class);

        return Inertia::render('Admin/Patients/Form', [
            'patient' => null,
            'centers' => $this->centerOptions($request),
            'religionOptions' => $this->religionOptions(),
            // Form.vue declares can_update as a required prop (it drives
            // PatientInfoForm's readonly state) — create() never sent it,
            // producing a "Missing required prop" console warning on
            // every visit to this page. Always true here: reaching this
            // action already passed the 'create' gate, and creating
            // implies being able to edit what was just created.
            'can_update' => true,
        ]);
    }

    public function edit(Request $request, Patient $patient): Response
    {
        // 'view', not 'update' — a read-only practitioner (patients.view
        // only) can open a patient's file, just not edit it. can_update
        // below drives what the template actually lets them touch.
        Gate::authorize('view', $patient);

        $patient->load([
            'treatments' => fn ($query) => $query->with([
                'practitioner',
                'diseases.category',
                'sessions.careItems.category',
                'sessions.diseaseProgress.disease',
            ])->orderByDesc('started_at'),
        ]);

        return Inertia::render('Admin/Patients/Form', [
            'patient' => [...$patient->toArray(), 'derived_status' => $patient->derivedStatus()],
            'treatments' => TreatmentResource::collection($patient->treatments),
            'centers' => $this->centerOptions($request),
            'practitioners' => $this->practitionerOptions($request),
            'religionOptions' => $this->religionOptions(),
            'can_update' => Gate::allows('update', $patient),
            'diseases' => DiseaseResource::collection(
                Disease::query()->where('active', true)->with('category')->orderBy('code')->get(),
            ),
            'diseaseCategories' => DiseaseCategoryResource::collection(
                DiseaseCategory::query()->where('active', true)->orderBy('order')->get(),
            ),
            'careCategories' => CareCategoryResource::collection(
                CareCategory::query()->where('active', true)->with('items')->orderBy('order')->get(),
            ),
        ]);
    }

    /**
     * Idempotent on client_uuid: a lost response to a genuinely
     * successful first save must not create a duplicate Patient row
     * when the frontend's debounce retries.
     */
    public function storeDraft(StorePatientDraftRequest $request, PatientNumberGenerator $numberGenerator): JsonResponse
    {
        $existing = Patient::query()->where('client_uuid', $request->string('client_uuid'))->first();

        if ($existing) {
            Gate::authorize('update', $existing);

            return response()->json(['id' => $existing->id, 'client_uuid' => $existing->client_uuid]);
        }

        $center = Center::query()->findOrFail($request->centerId());

        $patient = Patient::create([
            ...$request->validated(),
            'intake_center_id' => $center->id,
            'patient_number' => $numberGenerator->next($center),
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

        // Back to the patient's own file, not the flat list: confirming is
        // rarely the end of the task, it's usually followed by adding the
        // first treatment — staying in place saves re-finding the patient.
        // ?tab=ongoing&open=treatment additionally opens the treatment
        // wizard automatically, since confirm() only ever fires once in a
        // patient's lifecycle (draft -> confirmed), never on a later update.
        return redirect()->route('admin.patients.edit', [
            'patient' => $patient,
            'tab' => 'ongoing',
            'open' => 'treatment',
        ]);
    }

    public function destroy(Patient $patient): RedirectResponse
    {
        Gate::authorize('delete', $patient);

        $patient->delete();

        return redirect()->route('admin.patients.index');
    }

    /**
     * @return array<int, array{id: int, code: string, label: string}>
     */
    private function religionOptions(): array
    {
        return EnumOption::cachedByType('patient.religion')
            ->map(fn (EnumOption $option) => [
                'id' => $option->id,
                'code' => $option->code,
                'label' => $option->label['fr'] ?? $option->code,
            ])
            ->all();
    }
}
