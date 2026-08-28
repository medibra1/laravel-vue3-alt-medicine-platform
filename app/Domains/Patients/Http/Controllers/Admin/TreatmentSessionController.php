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
        $measurements = $validated['measurements'] ?? [];
        unset($validated['disease_progress'], $validated['care_item_ids'], $validated['measurements']);

        $session = $treatment->sessions()->create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        $session->careItems()->sync($careItemIds);
        $this->syncDiseaseProgress($session, $diseaseProgress);
        $this->syncMeasurements($session, $measurements);
        $treatment->refreshClosureStatus();

        return redirect()->route('admin.patients.edit', $treatment->patient_id);
    }

    public function update(UpdateTreatmentSessionRequest $request, Treatment $treatment, TreatmentSession $session): RedirectResponse
    {
        $validated = $request->validated();
        $diseaseProgress = $validated['disease_progress'] ?? [];
        $careItemIds = $validated['care_item_ids'] ?? [];
        $measurements = $validated['measurements'] ?? [];
        unset($validated['disease_progress'], $validated['care_item_ids'], $validated['measurements']);

        $session->update($validated);
        $session->careItems()->sync($careItemIds);
        $this->syncDiseaseProgress($session, $diseaseProgress);
        $this->syncMeasurements($session, $measurements);
        $treatment->refreshClosureStatus();

        return redirect()->route('admin.patients.edit', $treatment->patient_id);
    }

    public function destroy(Treatment $treatment, TreatmentSession $session): RedirectResponse
    {
        // {session} isn't a scoped route-model binding — a session id that
        // exists but belongs to a *different* treatment than the one in
        // the URL would otherwise still resolve. TreatmentSessionPolicy
        // already protects against cross-center deletion (it scopes on
        // the session's real parent treatment), but a mismatched
        // treatment/session pair in the URL is a data-consistency bug on
        // the caller's side, not a permission question — surfaced as 404
        // rather than silently deleting the right session under the
        // wrong URL.
        abort_unless($session->treatment_id === $treatment->id, 404);

        Gate::authorize('delete', $session);

        $session->delete();
        $treatment->refreshClosureStatus();

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

    /**
     * Upsert on [treatment_session_id, measurement_type_option_id] — same
     * reasoning as syncDiseaseProgress(): re-saving a session updates the
     * existing measurement row for that type instead of creating a
     * duplicate, mirroring the DB unique constraint.
     *
     * @param  array<int, array{measurement_type_option_id: int, value: string, unit?: ?string, notes?: ?string}>  $measurements
     */
    protected function syncMeasurements(TreatmentSession $session, array $measurements): void
    {
        foreach ($measurements as $row) {
            $session->measurements()->updateOrCreate(
                ['measurement_type_option_id' => $row['measurement_type_option_id']],
                [
                    'value' => $row['value'],
                    'unit' => $row['unit'] ?? null,
                    'notes' => $row['notes'] ?? null,
                ]
            );
        }
    }
}
