<?php

namespace App\Domains\Patients\Http\Controllers\Admin;

use App\Domains\Core\Http\Concerns\ResolvesCenterOptions;
use App\Domains\Patients\Http\Requests\CloseTreatmentRequest;
use App\Domains\Patients\Http\Requests\ConfirmTreatmentRequest;
use App\Domains\Patients\Http\Requests\ReopenTreatmentRequest;
use App\Domains\Patients\Http\Requests\StoreTreatmentDraftRequest;
use App\Domains\Patients\Http\Requests\UpdateTreatmentDraftRequest;
use App\Domains\Patients\Http\Resources\CareCategoryResource;
use App\Domains\Patients\Http\Resources\DiseaseCategoryResource;
use App\Domains\Patients\Http\Resources\DiseaseResource;
use App\Domains\Patients\Http\Resources\PatientOptionResource;
use App\Domains\Patients\Models\CareCategory;
use App\Domains\Patients\Models\Disease;
use App\Domains\Patients\Models\DiseaseCategory;
use App\Domains\Patients\Models\Patient;
use App\Domains\Patients\Models\Treatment;
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

class TreatmentController extends Controller
{
    use ResolvesCenterOptions;
    use ResolvesPractitionerOptions;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Treatment::class);

        $query = Treatment::query()->with(['patient', 'practitioner', 'center']);

        if (! $request->user()->isSuperAdmin()) {
            $query->where('center_id', getPermissionsTeamId());
        }

        $treatments = QueryBuilder::for($query)
            ->allowedFilters(
                AllowedFilter::exact('patient_id'),
                AllowedFilter::exact('center_id'),
            )
            ->allowedSorts('started_at', 'created_at')
            ->defaultSort('-created_at')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return Inertia::render('Admin/Treatments/Index', [
            'treatments' => $treatments,
            'filters' => (object) $request->only(['filter', 'sort']),
            'centers' => $this->centerOptions($request),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Treatment::class);

        return Inertia::render('Admin/Treatments/Form', [
            'treatment' => null,
            ...$this->formOptions($request),
        ]);
    }

    public function edit(Request $request, Treatment $treatment): Response
    {
        Gate::authorize('update', $treatment);

        $treatment->load('diseases.category', 'sessions');

        return Inertia::render('Admin/Treatments/Form', [
            'treatment' => [
                ...$treatment->toArray(),
                'disease_ids' => $treatment->diseases->pluck('id')->all(),
                'locked_disease_ids' => $treatment->latestOutcomePerDisease()->keys()->values(),
            ],
            ...$this->formOptions($request),
        ]);
    }

    /**
     * Idempotent on client_uuid, same reasoning as PatientController::storeDraft().
     */
    public function storeDraft(StoreTreatmentDraftRequest $request): JsonResponse
    {
        $existing = Treatment::query()->where('client_uuid', $request->string('client_uuid'))->first();

        if ($existing) {
            Gate::authorize('update', $existing);

            return response()->json(['id' => $existing->id, 'client_uuid' => $existing->client_uuid]);
        }

        $validated = $request->validated();
        $diseaseIds = $validated['disease_ids'] ?? [];
        unset($validated['disease_ids']);

        $treatment = Treatment::create([
            ...$validated,
            'center_id' => $request->centerId(),
            'created_by' => $request->user()->id,
        ]);
        $treatment->diseases()->sync($diseaseIds);
        $treatment->setStatus('draft');

        return response()->json(['id' => $treatment->id, 'client_uuid' => $treatment->client_uuid], 201);
    }

    public function updateDraft(UpdateTreatmentDraftRequest $request, Treatment $treatment): JsonResponse
    {
        $validated = $request->validated();

        if (array_key_exists('disease_ids', $validated)) {
            $treatment->diseases()->sync($validated['disease_ids'] ?? []);
            unset($validated['disease_ids']);
        }

        $treatment->update($validated);

        return response()->json(['id' => $treatment->id]);
    }

    public function confirm(ConfirmTreatmentRequest $request, Treatment $treatment): RedirectResponse
    {
        $validated = $request->validated();
        $treatment->diseases()->sync($validated['disease_ids']);
        $careItemIds = $validated['care_item_ids'] ?? [];
        unset($validated['disease_ids'], $validated['care_item_ids']);

        $treatment->update($validated);
        $treatment->setStatus('confirmed');
        // Confirming immediately starts real-world follow-up — there's no
        // separate manual step to reach `ongoing`, see CLAUDE.md "Statut
        // global Treatment" for why this transition is automatic.
        $treatment->setStatus('ongoing');

        // The wizard's "Soins — 1ère séance" step is stored as the
        // treatment's first (implicit) session rather than on the
        // treatment/pivot directly — same storage path every later real
        // session uses, see CLAUDE.md "Domaine Treatment" for the
        // per-session-history reasoning. Gated on doesntExist() rather
        // than "care_item_ids non vide": confirm() can be reached again
        // on an already-started treatment (editing it after the fact),
        // and that must never spawn a second implicit session — care for
        // an already-started treatment only ever goes through
        // TreatmentSessionController from then on, so a resubmitted
        // care_item_ids payload here is silently ignored once a session
        // exists.
        if ($careItemIds !== [] && $treatment->sessions()->doesntExist()) {
            $session = $treatment->sessions()->create([
                'practitioner_id' => $treatment->practitioner_id,
                'session_date' => $treatment->started_at,
                'created_by' => $request->user()->id,
            ]);

            $session->careItems()->sync($careItemIds);
        }

        // Not the flat treatments list: whether this wizard was opened
        // standalone or from within a patient's file, the natural next
        // step (adding a session, tracking progress) only happens on the
        // patient's own page — landing there continues the workflow
        // instead of dropping the user back at an unrelated index.
        // ?tab=ongoing lands directly on the tab showing the treatment
        // just confirmed — unlike PatientController::confirm()'s ?tab=,
        // this one is always correct here regardless of whether the
        // wizard was creating or editing, since the confirmed treatment
        // is necessarily the one in that tab.
        return redirect()->route('admin.patients.edit', [
            'patient' => $treatment->patient_id,
            'tab' => 'ongoing',
        ]);
    }

    /**
     * Manual/early closure — see CloseTreatmentRequest for the reasons
     * accepted and CLAUDE.md "Statut global Treatment" for why this exists
     * alongside the automatic closure in Treatment::refreshClosureStatus().
     */
    public function close(CloseTreatmentRequest $request, Treatment $treatment): RedirectResponse
    {
        $treatment->manualClose($request->string('closure_reason')->toString(), $request->input('notes'));

        return redirect()->route('admin.patients.edit', $treatment->patient_id);
    }

    public function reopen(ReopenTreatmentRequest $request, Treatment $treatment): RedirectResponse
    {
        $treatment->reopen();

        return redirect()->route('admin.patients.edit', $treatment->patient_id);
    }

    public function destroy(Treatment $treatment): RedirectResponse
    {
        Gate::authorize('delete', $treatment);

        $treatment->delete();

        return redirect()->route('admin.treatments.index');
    }

    /** @return array<string, mixed> */
    protected function formOptions(Request $request): array
    {
        $centerId = $request->user()->isSuperAdmin() ? null : $request->user()->managedCenterId();

        return [
            'centers' => $this->centerOptions($request),
            'patients' => PatientOptionResource::collection(
                Patient::query()
                    ->when($centerId, fn ($query) => $query->where('intake_center_id', $centerId))
                    ->orderBy('last_name')
                    ->get(),
            ),
            'practitioners' => $this->practitionerOptions($request),
            'diseases' => DiseaseResource::collection(
                Disease::query()->where('active', true)->with('category')->orderBy('code')->get(),
            ),
            'diseaseCategories' => DiseaseCategoryResource::collection(
                DiseaseCategory::query()->where('active', true)->orderBy('order')->get(),
            ),
            'careCategories' => CareCategoryResource::collection(
                CareCategory::query()->where('active', true)->with('items')->orderBy('order')->get(),
            ),
        ];
    }
}
