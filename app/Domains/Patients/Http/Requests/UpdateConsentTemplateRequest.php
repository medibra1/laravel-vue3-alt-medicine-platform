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
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ];
    }
}
