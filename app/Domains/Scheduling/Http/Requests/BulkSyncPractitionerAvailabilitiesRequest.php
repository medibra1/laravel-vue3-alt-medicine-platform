<?php

namespace App\Domains\Scheduling\Http\Requests;

use App\Domains\Practitioners\Models\Practitioner;
use App\Domains\Scheduling\Http\Requests\Concerns\ValidatesWeeklySlots;
use App\Domains\Scheduling\Models\PractitionerAvailability;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Applies one weekly schedule to several practitioners. Each one still
 * ends up with its own independent rows — there is no shared template.
 */
class BulkSyncPractitionerAvailabilitiesRequest extends FormRequest
{
    use ValidatesWeeklySlots;

    public function authorize(): bool
    {
        return $this->user()->can('create', PractitionerAvailability::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'practitioner_ids' => ['required', 'array', 'min:1'],
            'practitioner_ids.*' => ['integer', 'distinct', 'exists:practitioners,id'],
            ...$this->slotRules(),
        ];
    }

    /**
     * Any practitioner outside the manager's active center rejects the
     * whole request — nothing is applied partially.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateNoOverlappingSlots($validator);

            if ($this->user()->isSuperAdmin()) {
                return;
            }

            $ids = array_map('intval', (array) $this->input('practitioner_ids', []));

            $outsideCenter = Practitioner::query()
                ->whereIn('id', $ids)
                ->where('center_id', '!=', getPermissionsTeamId())
                ->exists();

            if ($outsideCenter) {
                $validator->errors()->add('practitioner_ids', __('Un ou plusieurs praticiens n\'appartiennent pas à votre centre.'));
            }
        });
    }
}
