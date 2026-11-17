<?php

namespace App\Domains\Billing\Http\Requests;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recordPayment', $this->route('invoice'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in(Payment::METHODS)],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Invoice $invoice */
            $invoice = $this->route('invoice');

            if ($invoice->cancelled_at !== null) {
                $validator->errors()->add('amount', __('Cette facture est annulée.'));

                return;
            }

            // +0.005: float tolerance, an exact settlement must pass.
            if ((float) $this->input('amount') > $invoice->balance() + 0.005) {
                $validator->errors()->add('amount', __('Le montant dépasse le reste à payer.'));
            }
        });
    }
}
