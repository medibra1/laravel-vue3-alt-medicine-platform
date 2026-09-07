<?php

namespace App\Domains\Scheduling\Http\Requests;

use App\Domains\Scheduling\Rules\NoAppointmentConflict;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('appointment'));
    }

    /**
     * Reschedule only (date/duration/practitioner) — patient/treatment
     * are set once at creation and never moved to a different
     * appointment; cancelling/no-show are their own dedicated endpoints
     * (see AppointmentController::cancel()/markNoShow()).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'practitioner_id' => ['required', 'integer', 'exists:practitioners,id'],
            'starts_at' => ['required', 'date', new NoAppointmentConflict($this)],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'reason' => ['nullable', 'string'],
        ];
    }
}
