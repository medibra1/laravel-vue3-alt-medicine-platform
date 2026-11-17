<?php

namespace App\Domains\Patients\Http\Requests;

use App\Domains\Patients\Models\ConsentTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConsentTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ConsentTemplate::class);
    }

    /**
     * store() only ever creates the very first template of a type
     * (version 1) — bumping an existing type to a new version is
     * update()'s job (see ConsentTemplateController::update()), never
     * store()'s. A type that already has an active template is
     * rejected here rather than silently producing a second active row.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => [
                'required',
                Rule::in(['treatment', 'data_privacy', 'image_rights']),
                Rule::unique('consent_templates', 'type')->where('is_active', true),
            ],
            // French required; English/Arabic optional, but a language is
            // either complete (title + content) or absent.
            'title' => ['required', 'array'],
            'title.fr' => ['required', 'string', 'max:255'],
            'title.en' => ['nullable', 'string', 'max:255', 'required_with:content.en'],
            'title.ar' => ['nullable', 'string', 'max:255', 'required_with:content.ar'],
            'content' => ['required', 'array'],
            'content.fr' => ['required', 'string'],
            'content.en' => ['nullable', 'string', 'required_with:title.en'],
            'content.ar' => ['nullable', 'string', 'required_with:title.ar'],
        ];
    }
}
