<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleInstallmentsAction
{
    /**
     * Replaces the payment schedule of an invoice. An empty list removes
     * it (the invoice is then simply due in one go).
     *
     * @param  array<int, array{due_on: string, amount: float|int|string}>  $installments
     */
    public function handle(Invoice $invoice, array $installments): void
    {
        if ($installments !== []) {
            if (count($installments) < 2) {
                throw ValidationException::withMessages(['installments' => __('billing.schedule_min_two')]);
            }

            $dates = array_column($installments, 'due_on');
            $sorted = $dates;
            sort($sorted);

            if ($dates !== $sorted || count(array_unique($dates)) !== count($dates)) {
                throw ValidationException::withMessages(['installments' => __('billing.schedule_dates_order')]);
            }

            $sum = round(array_sum(array_map(fn (array $row) => (float) $row['amount'], $installments)), 2);

            if (abs($sum - $invoice->netTotal()) > 0.005) {
                throw ValidationException::withMessages(['installments' => __('billing.schedule_sum_mismatch')]);
            }
        }

        DB::transaction(function () use ($invoice, $installments) {
            $invoice->installments()->delete();

            foreach (array_values($installments) as $index => $row) {
                $invoice->installments()->create(['due_on' => $row['due_on'], 'amount' => $row['amount'], 'position' => $index + 1]);
            }
        });
    }

    /**
     * After a credit note: brings the schedule back to what is still
     * owed by shrinking the LAST instalments first, so the next ones the
     * patient was expecting do not move.
     */
    public function fitToNetTotal(Invoice $invoice): void
    {
        $excess = round((float) $invoice->installments()->sum('amount') - $invoice->netTotal(), 2);

        foreach ($invoice->installments()->reorder()->orderByDesc('due_on')->get() as $installment) {
            if ($excess <= 0) {
                break;
            }

            $cut = min($excess, (float) $installment->amount);
            $excess = round($excess - $cut, 2);

            if ((float) $installment->amount - $cut <= 0) {
                $installment->delete();
            } else {
                $installment->update(['amount' => round((float) $installment->amount - $cut, 2)]);
            }
        }
    }
}
