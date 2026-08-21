<?php

namespace App\Domains\Patients\Http\Requests;

use App\Domains\Patients\Models\Treatment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReopenTreatmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Treatment $treatment */
        $treatment = $this->route('treatment');

        return $this->user()->can('reopen', $treatment);
    }

    /** No body fields — reopening carries no reason/notes, only the status transition. */
    public function rules(): array
    {
        return [];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Treatment $treatment */
            $treatment = $this->route('treatment');

            if ($treatment->currentStatusName() !== 'closed') {
                $validator->errors()->add('status', 'Seul un traitement fermé peut être rouvert.');
            }
        });
    }
}
