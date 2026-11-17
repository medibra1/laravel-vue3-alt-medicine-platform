<?php

namespace App\Domains\Billing\Services;

use App\Domains\Auth\Models\User;
use App\Domains\Billing\Models\CreditNote;
use App\Domains\Billing\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class IssueCreditNoteAction
{
    public function __construct(private readonly InvoiceNumberGenerator $numbers) {}

    public function handle(Invoice $invoice, float $amount, string $issuedOn, ?string $reason, ?User $author): CreditNote
    {
        return DB::transaction(function () use ($invoice, $amount, $issuedOn, $reason, $author) {
            $date = Carbon::parse($issuedOn);

            $creditNote = $invoice->creditNotes()->create([
                'center_id' => $invoice->center_id,
                'number' => $this->numbers->nextCreditNote($invoice->center, $date->year),
                'amount' => $amount,
                'issued_on' => $date->toDateString(),
                'reason' => $reason,
                'created_by' => $author?->id,
            ]);

            $invoice->refreshTotals();

            return $creditNote;
        });
    }
}
