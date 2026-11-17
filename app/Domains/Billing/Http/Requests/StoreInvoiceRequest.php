<?php

namespace App\Domains\Billing\Http\Requests;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use App\Domains\Patients\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Patient $patient */
        $patient = $this->route('patient');

        return $this->user()->can('create', Invoice::class)
            && ($this->user()->isGlobalAdmin() || $patient->intake_center_id === getPermissionsTeamId());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Patient $patient */
        $patient = $this->route('patient');

        return [
            'issued_on' => ['required', 'date'],
            'treatment_id' => ['nullable', 'integer', Rule::exists('treatments', 'id')->where('patient_id', $patient->id)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.label' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'lines.*.treatment_session_id' => ['nullable', 'integer', 'exists:treatment_sessions,id'],
            'lines.*.care_item_id' => ['nullable', 'integer', 'exists:care_items,id'],
            // Optional payment taken on the spot.
            'payment' => ['nullable', 'array'],
            'payment.amount' => ['required_with:payment', 'numeric', 'gt:0'],
            'payment.method' => ['required_with:payment', Rule::in(Payment::METHODS)],
            'payment.reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
