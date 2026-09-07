<?php

namespace App\Domains\Scheduling\Http\Requests;

use App\Domains\Scheduling\Models\PractitionerAvailability;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePractitionerAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PractitionerAvailability $availability */
        $availability = $this->route('availability');

        return $this->user()->can('update', $availability);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ];
    }
}
