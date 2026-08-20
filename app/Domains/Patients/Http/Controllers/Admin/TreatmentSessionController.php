<?php

namespace App\Domains\Patients\Http\Controllers\Admin;

use App\Domains\Patients\Http\Requests\StoreTreatmentSessionRequest;
use App\Domains\Patients\Http\Requests\UpdateTreatmentSessionRequest;
use App\Domains\Patients\Models\Treatment;
use App\Domains\Patients\Models\TreatmentSession;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class TreatmentSessionController extends Controller
{
    public function store(StoreTreatmentSessionRequest $request, Treatment $treatment): RedirectResponse
    {
        // The FormRequest's authorize() only checks the global
        // treatment_sessions.create permission (there's no TreatmentSession
        // instance yet to scope against, same reasoning as
        // TreatmentPolicy::create()) — the center check against the
        // *parent* Treatment happens here instead.
        if (! $request->user()->isSuperAdmin() && $treatment->center_id !== getPermissionsTeamId()) {
            abort(403);
        }

        $validated = $request->validated();
        $diseaseProgress = $validated['disease_progress'] ?? [];
        $careItemIds = $validated['care_item_ids'] ?? [];
        unset($validated['disease_progress'], $validated['care_item_ids']);

        $session = $treatment->sessions()->create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        $session->careItems()->sync($careItemIds);
        $this->syncDiseaseProgress($session, $diseaseProgress);

        return redirect()->route('admin.patients.edit', $treatment->patient_id);
    }

    public function update(UpdateTreatmentSessionRequest $request, Treatment $treatment, TreatmentSession $session): RedirectResponse
    {
        $validated = $request->validated();
        $diseaseProgress = $validated['disease_progress'] ?? [];
        $careItemIds = $validated['care_item_ids'] ?? [];
        unset($validated['disease_progress'], $validated['care_item_ids']);

        $session->update($validated);
        $session->careItems()->sync($careItemIds);
        $this->syncDiseaseProgress($session, $diseaseProgress);

        return redirect()->route('admin.patients.edit', $treatment->patient_id);
    }

    public function destroy(Treatment $treatment, TreatmentSession $session): RedirectResponse
    {
        Gate::authorize('delete', $session);

        $session->delete();

        return redirect()->route('admin.patients.edit', $treatment->patient_id);
    }

    /**
     * Upsert on [treatment_session_id, disease_id] so re-saving a
     * session (e.g. correcting a percentage) updates the existing
     * progress row instead of creating a duplicate for the same disease
     * at the same session — the DB unique constraint mirrors this intent.
     *
     * @param  array<int, array{disease_id: int, outcome?: ?string, outcome_percentage?: ?int, notes?: ?string}>  $diseaseProgress
     */
    protected function syncDiseaseProgress(TreatmentSession $session, array $diseaseProgress): void
    {
        foreach ($diseaseProgress as $row) {
            $session->diseaseProgress()->updateOrCreate(
                ['disease_id' => $row['disease_id']],
                [
                    'outcome' => $row['outcome'] ?? null,
                    'outcome_percentage' => $row['outcome_percentage'] ?? null,
                    'notes' => $row['notes'] ?? null,
                ]
            );
        }
    }
}
