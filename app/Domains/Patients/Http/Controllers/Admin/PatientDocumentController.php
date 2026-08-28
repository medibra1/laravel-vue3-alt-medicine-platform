<?php

namespace App\Domains\Patients\Http\Controllers\Admin;

use App\Domains\Patients\Http\Requests\StorePatientDocumentRequest;
use App\Domains\Patients\Models\Patient;
use App\Domains\Patients\Services\MergeImagesIntoPdfAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PatientDocumentController extends Controller
{
    /**
     * Multiple files uploaded at once into 'identity'/'medical' are merged
     * into a single PDF (a phone photographing several pages of one
     * document should produce one attachment, not one per photo) — 'other'
     * never merges, and neither does a single-file upload into any
     * collection, since there's nothing to combine.
     */
    public function store(StorePatientDocumentRequest $request, Patient $patient, MergeImagesIntoPdfAction $mergeImages): RedirectResponse
    {
        $collection = $request->string('collection')->toString();
        $files = $request->file('files');

        $shouldMerge = count($files) > 1 && in_array($collection, ['identity', 'medical'], true);

        if ($shouldMerge) {
            $mergedPath = $mergeImages($files);

            $patient->addMedia($mergedPath)
                ->usingFileName('merged-'.now()->format('Y-m-d-His').'.pdf')
                ->toMediaCollection($collection);
        } else {
            foreach ($files as $file) {
                $patient->addMedia($file)->toMediaCollection($collection);
            }
        }

        return redirect()->route('admin.patients.edit', ['patient' => $patient, 'tab' => 'documents']);
    }

    public function destroy(Patient $patient, Media $media): RedirectResponse
    {
        // $media isn't a scoped route-model binding — a media id that
        // exists but belongs to a *different* patient than the one in the
        // URL would otherwise still resolve. Same guard already used for
        // treatment/session pairs (TreatmentSessionController::destroy()).
        abort_unless($media->model_type === Patient::class && $media->model_id === $patient->id, 404);

        Gate::authorize('update', $patient);

        $media->delete();

        return redirect()->route('admin.patients.edit', ['patient' => $patient, 'tab' => 'documents']);
    }

    public function show(Patient $patient, Media $media): BinaryFileResponse
    {
        abort_unless($media->model_type === Patient::class && $media->model_id === $patient->id, 404);

        Gate::authorize('view', $patient);

        return response()->file($media->getPath(), [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="'.$media->file_name.'"',
        ]);
    }

    public function thumb(Patient $patient, Media $media): BinaryFileResponse
    {
        abort_unless($media->model_type === Patient::class && $media->model_id === $patient->id, 404);
        abort_unless($media->hasGeneratedConversion('thumb'), 404);

        Gate::authorize('view', $patient);

        return response()->file($media->getPath('thumb'), [
            'Content-Type' => $media->mime_type === 'application/pdf' ? 'image/jpeg' : $media->mime_type,
        ]);
    }
}
