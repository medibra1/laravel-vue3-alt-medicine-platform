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
            'lines.*.description' => ['nullable', 'string', 'max:1000'],
            'lines.*.care_item_id' => ['nullable', 'integer', 'exists:care_items,id'],
            'lines.*.care_category_id' => ['nullable', 'integer', 'exists:care_categories,id'],
            // Ignored for a user without invoices.discount: the patient's
            // standing discount is applied instead (see InvoiceController).
            'discount' => ['nullable', 'array'],
            'discount.percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount.amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'discount.reason_option_id' => [
                Rule::requiredIf(fn () => (float) $this->input('discount.percent') > 0 || (float) $this->input('discount.amount') > 0),
                'nullable', 'integer',
                Rule::exists('enum_options', 'id')->where('enum_type', Invoice::DISCOUNT_REASON_TYPE),
            ],
            'discount.note' => ['nullable', 'string', 'max:255'],
            // Optional payment schedule, checked against the net total
            // once the invoice exists.
            'installments' => ['nullable', 'array', 'max:36'],
            'installments.*.due_on' => ['required', 'date'],
            'installments.*.amount' => ['required', 'numeric', 'gt:0'],
            // Optional payment taken on the spot.
            'payment' => ['nullable', 'array'],
            'payment.amount' => ['required_with:payment', 'numeric', 'gt:0'],
            'payment.method' => ['required_with:payment', Rule::in(Payment::METHODS)],
            'payment.reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
