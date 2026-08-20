<?php

namespace App\Domains\Patients\Http\Controllers\Admin;

use App\Domains\Core\Models\Center;
use App\Domains\Patients\Http\Requests\ConfirmPatientRequest;
use App\Domains\Patients\Http\Requests\StorePatientDraftRequest;
use App\Domains\Patients\Http\Requests\UpdatePatientDraftRequest;
use App\Domains\Patients\Models\CareCategory;
use App\Domains\Patients\Models\CareItem;
use App\Domains\Patients\Models\Disease;
use App\Domains\Patients\Models\DiseaseCategory;
use App\Domains\Patients\Models\Patient;
use App\Domains\Patients\Models\Treatment;
use App\Domains\Patients\Models\TreatmentSession;
use App\Domains\Practitioners\Models\Practitioner;
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

        $patient->load([
            'treatments' => fn ($query) => $query->with([
                'practitioner',
                'diseases.category',
                'sessions.careItems.category',
                'sessions.diseaseProgress.disease',
            ])->orderByDesc('started_at'),
        ]);

        return Inertia::render('Admin/Patients/Form', [
            'patient' => $patient,
            'treatments' => $patient->treatments->map(fn (Treatment $treatment) => [
                'id' => $treatment->id,
                'started_at' => $treatment->started_at,
                'ended_at' => $treatment->ended_at,
                'practitioner' => $treatment->practitioner ? ['id' => $treatment->practitioner->id, 'full_code' => $treatment->practitioner->full_code] : null,
                'diseases' => $treatment->diseases->map(fn (Disease $disease) => [
                    'id' => $disease->id,
                    'code' => $disease->code,
                    'label' => $disease->label,
                    'category_label' => $disease->category->label,
                ])->values()->all(),
                'sessions' => $treatment->sessions->map(fn (TreatmentSession $session) => [
                    'id' => $session->id,
                    'session_date' => $session->session_date,
                    'duration_minutes' => $session->duration_minutes,
                    'notes' => $session->notes,
                    'care_items' => $session->careItems->map(fn (CareItem $item) => [
                        'id' => $item->id,
                        'label' => $item->label,
                        'category_label' => $item->category->label,
                    ])->values()->all(),
                    'disease_progress' => $session->diseaseProgress->map(fn ($progress) => [
                        'disease_id' => $progress->disease_id,
                        'disease_label' => $progress->disease->label,
                        'outcome' => $progress->outcome,
                        'outcome_percentage' => $progress->outcome_percentage,
                        'notes' => $progress->notes,
                    ])->values()->all(),
                ])->values()->all(),
            ])->values(),
            'centers' => $request->user()->isSuperAdmin() ? Center::query()->orderBy('code')->get(['id', 'name', 'code']) : [],
            'practitioners' => Practitioner::query()
                ->when(! $request->user()->isSuperAdmin(), fn ($query) => $query->where('center_id', $request->user()->managedCenterId()))
                ->orderBy('full_code')
                ->get(['id', 'full_code']),
            // ->map() resolves HasTranslations' current-locale label
            // explicitly — see TreatmentController::formOptions() for the
            // same gotcha (raw ->get()->toArray() serializes the
            // {fr:..., en:...} JSON column, not the resolved string).
            'diseases' => Disease::query()->where('active', true)->with('category')->orderBy('code')->get()
                ->map(fn (Disease $disease) => [
                    'id' => $disease->id,
                    'code' => $disease->code,
                    'label' => $disease->label,
                    'category_id' => $disease->disease_category_id,
                    'category_label' => $disease->category->label,
                ])
                ->values(),
            'diseaseCategories' => DiseaseCategory::query()->where('active', true)->orderBy('order')->get()
                ->map(fn (DiseaseCategory $category) => ['id' => $category->id, 'code' => $category->code, 'label' => $category->label])
                ->values(),
            'careCategories' => CareCategory::query()->where('active', true)->with('items')->orderBy('order')->get()
                ->map(fn (CareCategory $category) => [
                    'id' => $category->id,
                    'code' => $category->code,
                    'label' => $category->label,
                    'items' => $category->items->where('active', true)->values()->map(fn (CareItem $item) => [
                        'id' => $item->id,
                        'code' => $item->code,
                        'label' => $item->label,
                    ]),
                ])
                ->values(),
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
