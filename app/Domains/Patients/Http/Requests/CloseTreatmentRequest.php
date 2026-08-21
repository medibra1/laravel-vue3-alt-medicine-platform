<?php

namespace App\Domains\Patients\Http\Requests;

use App\Domains\Patients\Models\Treatment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CloseTreatmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Treatment $treatment */
        $treatment = $this->route('treatment');

        return $this->user()->can('close', $treatment);
    }

    /**
     * 'resolved' (all diseases reached a final outcome) is deliberately
     * excluded here — that value is only ever set by
     * Treatment::refreshClosureStatus(), never chosen by a person, so a
     * manual closure can only be one of the two reasons a treatment ends
     * *without* every disease being resolved.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'closure_reason' => ['required', Rule::in(['lost_to_follow_up', 'closed_manually'])],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Treatment $treatment */
            $treatment = $this->route('treatment');

            if ($treatment->currentStatusName() !== 'ongoing') {
                $validator->errors()->add('closure_reason', 'Seul un traitement en cours peut être clôturé.');
            }
        });
    }
}
