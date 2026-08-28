<?php

namespace App\Domains\Patients\Http\Controllers\Admin;

use App\Domains\Patients\Http\Requests\StorePatientConsentRequest;
use App\Domains\Patients\Models\Consent;
use App\Domains\Patients\Models\Patient;
use App\Domains\Patients\Services\RecordPatientConsentAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PatientConsentController extends Controller
{
    public function store(StorePatientConsentRequest $request, Patient $patient, RecordPatientConsentAction $recordConsent): RedirectResponse
    {
        $recordConsent(
            $patient,
            $request->string('type')->toString(),
            [
                'signer_name' => $request->string('signer_name')->toString(),
                'signature_svg' => $request->string('signature_svg')->toString() ?: null,
            ],
            $request->user(),
            $request->ip(),
        );

        return redirect()->route('admin.patients.edit', ['patient' => $patient, 'tab' => 'consent']);
    }

    public function show(Patient $patient, Consent $consent): BinaryFileResponse
    {
        // $consent isn't a scoped route-model binding — a valid consent
        // id belonging to a *different* patient would otherwise still
        // resolve. Same guard already used for documents/session pairs.
        abort_unless($consent->patient_id === $patient->id, 404);

        Gate::authorize('view', $patient);

        $media = $consent->getFirstMedia('document');
        abort_unless($media !== null, 404);

        return response()->file($media->getPath(), [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="'.$media->file_name.'"',
        ]);
    }
}
