<?php

namespace App\Domains\Scheduling\Http\Requests;

use App\Domains\Scheduling\Rules\NoAppointmentConflict;
use App\Domains\Scheduling\Rules\NoCenterClosureConflict;
use App\Domains\Scheduling\Rules\NoTimeOffConflict;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'starts_at' => ['required', 'date', new NoAppointmentConflict($this), new NoTimeOffConflict($this), new NoCenterClosureConflict($this)],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'modality' => ['required', Rule::in(['in_person', 'remote'])],
            'meeting_link' => ['nullable', 'url', 'max:255'],
            'reason' => ['nullable', 'string'],
        ];
    }
}
