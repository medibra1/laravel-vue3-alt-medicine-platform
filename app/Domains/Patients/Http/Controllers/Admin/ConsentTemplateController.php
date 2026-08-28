<?php

namespace App\Domains\Patients\Http\Controllers\Admin;

use App\Domains\Patients\Http\Requests\StoreConsentTemplateRequest;
use App\Domains\Patients\Http\Requests\UpdateConsentTemplateRequest;
use App\Domains\Patients\Http\Resources\ConsentTemplateResource;
use App\Domains\Patients\Models\ConsentTemplate;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ConsentTemplateController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', ConsentTemplate::class);

        return Inertia::render('Admin/ConsentTemplates/Index', [
            'templates' => ConsentTemplateResource::collection(
                ConsentTemplate::query()->orderBy('type')->orderByDesc('version')->get(),
            ),
        ]);
    }

    /**
     * Only ever creates version 1 of a brand-new type — see
     * StoreConsentTemplateRequest's docblock. A type that already has an
     * active template must go through update() instead.
     */
    public function store(StoreConsentTemplateRequest $request): RedirectResponse
    {
        ConsentTemplate::create([
            ...$request->validated(),
            'version' => 1,
            'is_active' => true,
        ]);

        return redirect()->route('admin.consent-templates.index');
    }

    /**
     * Never edits the existing row: a template a patient may have
     * already signed against must stay frozen exactly as it was (its
     * text lives on independently in Consent::content_snapshot anyway,
     * but the template row itself must also never retroactively change
     * out from under a version number already referenced by a Consent).
     * Creates version = old + 1, active; deactivates the old one; both
     * writes in one transaction so a template is never briefly without
     * an active version.
     */
    public function update(UpdateConsentTemplateRequest $request, ConsentTemplate $consentTemplate): RedirectResponse
    {
        DB::transaction(function () use ($request, $consentTemplate) {
            $consentTemplate->update(['is_active' => false]);

            ConsentTemplate::create([
                ...$request->validated(),
                'type' => $consentTemplate->type,
                'version' => $consentTemplate->version + 1,
                'is_active' => true,
            ]);
        });

        return redirect()->route('admin.consent-templates.index');
    }
}
