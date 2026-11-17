<?php

namespace App\Domains\Billing\Http\Requests;

use App\Domains\Billing\Models\Invoice;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SaveInstallmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('schedule', $this->route('invoice'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'installments' => ['present', 'array', 'max:36'],
            'installments.*.due_on' => ['required', 'date'],
            'installments.*.amount' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Invoice $invoice */
            $invoice = $this->route('invoice');

            if ($invoice->cancelled_at !== null) {
                $validator->errors()->add('installments', __('billing.invoice_cancelled'));
            }
        });
    }
}
