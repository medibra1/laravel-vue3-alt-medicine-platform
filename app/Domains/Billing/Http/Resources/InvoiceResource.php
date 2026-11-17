<?php

namespace App\Domains\Billing\Http\Resources;

use App\Domains\Billing\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'center_id' => $this->center_id,
            'patient_id' => $this->patient_id,
            'treatment_id' => $this->treatment_id,
            'currency' => $this->currency,
            'issued_on' => $this->issued_on->toDateString(),
            'total' => (float) $this->total,
            'paid_total' => (float) $this->paid_total,
            'credited_total' => (float) $this->credited_total,
            'balance' => $this->balance(),
            'status' => $this->status(),
            'notes' => $this->notes,
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient->id,
                'name' => trim($this->patient->first_name.' '.$this->patient->last_name),
            ]),
            'center' => $this->whenLoaded('center', fn () => ['id' => $this->center->id, 'name' => $this->center->name]),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line) => [
                'id' => $line->id,
                'label' => $line->label,
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'total' => (float) $line->total,
                'treatment_session_id' => $line->treatment_session_id,
            ])->all()),
            'credit_notes' => $this->whenLoaded('creditNotes', fn () => $this->creditNotes->map(fn ($creditNote) => [
                'id' => $creditNote->id,
                'number' => $creditNote->number,
                'amount' => (float) $creditNote->amount,
                'issued_on' => $creditNote->issued_on->toDateString(),
                'reason' => $creditNote->reason,
            ])->all()),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($payment) => [
                'id' => $payment->id,
                'amount' => (float) $payment->amount,
                'method' => $payment->method,
                'paid_on' => $payment->paid_on->toDateString(),
                'reference' => $payment->reference,
            ])->all()),
        ];
    }
}
