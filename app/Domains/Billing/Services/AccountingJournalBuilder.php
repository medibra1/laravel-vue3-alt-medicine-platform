<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\CreditNote;
use App\Domains\Billing\Models\Expense;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use Carbon\CarbonInterface;
use Generator;

/**
 * Double-entry journal of a period: every invoice, credit note, payment,
 * refund and expense becomes two balanced rows (one debit, one credit).
 * Rows are produced lazily, so a long period never loads everything in
 * memory. Cancelled invoices are left out entirely.
 */
class AccountingJournalBuilder
{
    public const HEADER = ['date', 'journal', 'piece', 'compte', 'tiers', 'libelle', 'debit', 'credit', 'devise', 'centre'];

    /**
     * @return Generator<int, array<int, string>>
     */
    public function rows(?int $centerId, CarbonInterface $from, CarbonInterface $to): Generator
    {
        $accounts = config('billing.accounts');
        $journals = config('billing.journals');
        // whereDate, not whereBetween: a date column can be stored with a
        // time part, which would drop the last day of the period.
        $range = [$from->toDateString(), $to->toDateString()];

        $invoices = Invoice::query()->active()
            ->when($centerId, fn ($query) => $query->where('center_id', $centerId))
            ->whereDate('issued_on', '>=', $range[0])->whereDate('issued_on', '<=', $range[1])
            ->with(['patient', 'center'])
            ->orderBy('issued_on')->orderBy('id');

        foreach ($invoices->lazy(500) as $invoice) {
            $patient = $this->patientName($invoice);
            $base = [$invoice->issued_on->toDateString(), $journals['sales'], $invoice->number];
            $tail = [$invoice->currency, $invoice->center->name];

            yield [...$base, $accounts['customer'], $patient, "Facture {$invoice->number}", $this->money($invoice->total), '', ...$tail];
            yield [...$base, $accounts['revenue'], $patient, "Facture {$invoice->number}", '', $this->money($invoice->total), ...$tail];
        }

        $creditNotes = CreditNote::query()
            ->when($centerId, fn ($query) => $query->where('center_id', $centerId))
            ->whereDate('issued_on', '>=', $range[0])->whereDate('issued_on', '<=', $range[1])
            ->whereHas('invoice', fn ($query) => $query->whereNull('cancelled_at'))
            ->with(['invoice.patient', 'invoice.center'])
            ->orderBy('issued_on')->orderBy('id');

        foreach ($creditNotes->lazy(500) as $creditNote) {
            $invoice = $creditNote->invoice;
            $patient = $this->patientName($invoice);
            $label = "Avoir {$creditNote->number} sur {$invoice->number}";
            $base = [$creditNote->issued_on->toDateString(), $journals['sales'], $creditNote->number];
            $tail = [$invoice->currency, $invoice->center->name];

            yield [...$base, $accounts['revenue'], $patient, $label, $this->money($creditNote->amount), '', ...$tail];
            yield [...$base, $accounts['customer'], $patient, $label, '', $this->money($creditNote->amount), ...$tail];
        }

        $payments = Payment::query()
            ->whereDate('paid_on', '>=', $range[0])->whereDate('paid_on', '<=', $range[1])
            ->whereHas('invoice', fn ($query) => $query->whereNull('cancelled_at')->when($centerId, fn ($query) => $query->where('center_id', $centerId)))
            ->with(['invoice.patient', 'invoice.center'])
            ->orderBy('paid_on')->orderBy('id');

        foreach ($payments->lazy(500) as $payment) {
            $invoice = $payment->invoice;
            $patient = $this->patientName($invoice);
            $cash = $payment->method === 'cash';
            $refund = (float) $payment->amount < 0;
            $amount = $this->money(abs((float) $payment->amount));
            $label = ($refund ? 'Remboursement' : 'Règlement')." {$invoice->number}".($payment->reference ? " ({$payment->reference})" : '');
            $base = [$payment->paid_on->toDateString(), $journals[$cash ? 'cash' : 'bank'], ($refund ? 'RB-' : 'RG-').$payment->id];
            $tail = [$invoice->currency, $invoice->center->name];
            $treasury = $accounts[$cash ? 'cash' : 'bank'];

            // A refund is the mirror image of a payment.
            yield [...$base, $refund ? $accounts['customer'] : $treasury, $patient, $label, $amount, '', ...$tail];
            yield [...$base, $refund ? $treasury : $accounts['customer'], $patient, $label, '', $amount, ...$tail];
        }

        $expenses = Expense::query()
            ->when($centerId, fn ($query) => $query->where('center_id', $centerId))
            ->whereDate('spent_on', '>=', $range[0])->whereDate('spent_on', '<=', $range[1])
            ->with(['center', 'category'])
            ->orderBy('spent_on')->orderBy('id');

        foreach ($expenses->lazy(500) as $expense) {
            $cash = $expense->method === 'cash';
            $label = trim(($expense->category?->localizedLabel() ?? '').' — '.$expense->label, ' —');
            $base = [$expense->spent_on->toDateString(), $journals['purchases'], 'DEP-'.$expense->id];
            $tail = [$expense->currency, $expense->center->name];

            yield [...$base, $accounts['expense'], (string) $expense->supplier, $label, $this->money($expense->amount), '', ...$tail];
            yield [...$base, $accounts[$cash ? 'cash' : 'bank'], (string) $expense->supplier, $label, '', $this->money($expense->amount), ...$tail];
        }
    }

    private function patientName(Invoice $invoice): string
    {
        return trim($invoice->patient->first_name.' '.$invoice->patient->last_name);
    }

    private function money(float|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
