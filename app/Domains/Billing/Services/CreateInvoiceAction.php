<?php

namespace App\Domains\Billing\Services;

use App\Domains\Auth\Models\User;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\Patient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateInvoiceAction
{
    public function __construct(private readonly InvoiceNumberGenerator $numbers) {}

    /**
     * @param  array{issued_on: string, treatment_id?: int|null, notes?: string|null, discount?: array{percent?: float|int|string|null, amount?: float|int|string|null, reason_option_id?: int|null, note?: string|null}|null, lines: array<int, array{label: string, description?: string|null, care_category_id?: int|null, quantity: float|int|string, unit_price: float|int|string, treatment_session_id?: int|null, care_item_id?: int|null}>}  $data
     */
    public function handle(Patient $patient, Center $center, array $data, ?User $author): Invoice
    {
        return DB::transaction(function () use ($patient, $center, $data, $author) {
            $issuedOn = Carbon::parse($data['issued_on']);

            $invoice = Invoice::create([
                'center_id' => $center->id,
                'patient_id' => $patient->id,
                'treatment_id' => $data['treatment_id'] ?? null,
                'number' => $this->numbers->next($center, $issuedOn->year),
                'currency' => $center->currency,
                'issued_on' => $issuedOn->toDateString(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $author?->id,
                ...$this->discount($data['discount'] ?? null),
            ]);

            foreach ($data['lines'] as $line) {
                $quantity = (float) $line['quantity'];
                $unitPrice = (float) $line['unit_price'];

                $invoice->lines()->create([
                    'label' => $line['label'],
                    'description' => $line['description'] ?? null,
                    'care_category_id' => $line['care_category_id'] ?? null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total' => round($quantity * $unitPrice, 2),
                    'treatment_session_id' => $line['treatment_session_id'] ?? null,
                    'care_item_id' => $line['care_item_id'] ?? null,
                ]);
            }

            // A fixed-amount discount can never exceed what is billed.
            if ($invoice->discount_percent === null && (float) $invoice->discount_amount > (float) $invoice->lines()->sum('total') + 0.005) {
                throw ValidationException::withMessages(['discount.amount' => __('billing.discount_exceeds_total')]);
            }

            $invoice->refreshTotals();

            return $invoice;
        });
    }

    /**
     * A percentage wins over an amount; no reason is kept without a
     * discount.
     *
     * @param  array{percent?: float|int|string|null, amount?: float|int|string|null, reason_option_id?: int|null, note?: string|null}|null  $discount
     * @return array<string, mixed>
     */
    private function discount(?array $discount): array
    {
        $percent = (float) ($discount['percent'] ?? 0);
        $amount = (float) ($discount['amount'] ?? 0);

        if ($percent <= 0 && $amount <= 0) {
            return [];
        }

        return [
            'discount_percent' => $percent > 0 ? $percent : null,
            'discount_amount' => $percent > 0 ? 0 : $amount,
            'discount_reason_option_id' => $discount['reason_option_id'] ?? null,
            'discount_note' => $discount['note'] ?? null,
        ];
    }
}
