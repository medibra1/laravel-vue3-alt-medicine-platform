<?php

namespace App\Domains\Billing\Http\Requests;

use App\Domains\Billing\Models\Invoice;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('credit', $this->route('invoice'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'issued_on' => ['required', 'date', 'before_or_equal:today'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Invoice $invoice */
            $invoice = $this->route('invoice');

            if ($invoice->cancelled_at !== null) {
                $validator->errors()->add('amount', __('billing.invoice_cancelled'));

                return;
            }

            // Never credit more than what was invoiced.
            if ((float) $this->input('amount') > $invoice->netTotal() + 0.005) {
                $validator->errors()->add('amount', __('billing.credit_exceeds_balance'));
            }
        });
    }
}
