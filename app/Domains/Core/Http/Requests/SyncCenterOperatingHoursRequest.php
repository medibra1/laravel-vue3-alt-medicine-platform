<?php

namespace App\Domains\Core\Http\Requests;

use App\Domains\Scheduling\Http\Requests\Concerns\ValidatesWeeklySlots;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SyncCenterOperatingHoursRequest extends FormRequest
{
    use ValidatesWeeklySlots;

    public function authorize(): bool
    {
        return $this->user()?->can('manageOperatingHours', $this->route('center')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->slotRules();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $v) => $this->validateNoOverlappingSlots($v));
    }
}
