<?php

namespace App\Domains\Scheduling\Http\Requests;

use App\Domains\Scheduling\Models\Appointment;
use Illuminate\Foundation\Http\FormRequest;

class CancelAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Appointment $appointment */
        $appointment = $this->route('appointment');

        return $this->user()->can('cancel', $appointment);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cancellation_reason' => ['required', 'string'],
        ];
    }
}
