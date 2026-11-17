<?php

namespace App\Domains\Billing\Services;

use App\Domains\Auth\Models\User;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\Patient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CreateInvoiceAction
{
    public function __construct(private readonly InvoiceNumberGenerator $numbers) {}

    /**
     * @param  array{issued_on: string, treatment_id?: int|null, notes?: string|null, lines: array<int, array{label: string, quantity: float|int|string, unit_price: float|int|string, treatment_session_id?: int|null, care_item_id?: int|null}>}  $data
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
            ]);

            foreach ($data['lines'] as $line) {
                $quantity = (float) $line['quantity'];
                $unitPrice = (float) $line['unit_price'];

                $invoice->lines()->create([
                    'label' => $line['label'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total' => round($quantity * $unitPrice, 2),
                    'treatment_session_id' => $line['treatment_session_id'] ?? null,
                    'care_item_id' => $line['care_item_id'] ?? null,
                ]);
            }

            $invoice->refreshTotals();

            return $invoice;
        });
    }
}
