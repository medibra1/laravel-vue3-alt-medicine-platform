<?php

namespace App\Domains\Patients\Http\Requests;

use App\Domains\Patients\Models\ConsentTemplate;
use Illuminate\Foundation\Http\FormRequest;

class UpdateConsentTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ConsentTemplate $consentTemplate */
        $consentTemplate = $this->route('consent_template');

        return $this->user()->can('update', $consentTemplate);
    }

    /**
     * type/version are never accepted here — update() always derives
     * them from the template being edited (see
     * ConsentTemplateController::update()), it never edits a row in
     * place. Only title/content are ever user-supplied on an edit.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
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
