<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Core\Models\Center;
use Illuminate\Support\Facades\DB;

/**
 * Per-center, per-year document numbers: F-2026-00042 (invoices),
 * AV-2026-00003 (credit notes). The counter lives in billing_sequences
 * and is read under a row lock, so two concurrent documents can never
 * get the same number. Must be called inside the transaction that
 * creates the document: a rollback then gives the number back.
 */
class InvoiceNumberGenerator
{
    public function next(Center $center, int $year): string
    {
        return $this->format('F', $this->increment($center, 'invoice', $year), $year);
    }

    public function nextCreditNote(Center $center, int $year): string
    {
        return $this->format('AV', $this->increment($center, 'credit_note', $year), $year);
    }

    private function format(string $prefix, int $sequence, int $year): string
    {
        return "{$prefix}-{$year}-".str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    private function increment(Center $center, string $type, int $year): int
    {
        return DB::transaction(function () use ($center, $type, $year) {
            $key = ['center_id' => $center->id, 'type' => $type, 'year' => $year];

            $row = DB::table('billing_sequences')->where($key)->lockForUpdate()->first();

            if ($row === null) {
                DB::table('billing_sequences')->insert([
                    ...$key,
                    'last_number' => $type === 'invoice' ? $this->lastLegacyInvoiceNumber($center, $year) : 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $row = DB::table('billing_sequences')->where($key)->lockForUpdate()->first();
            }

            $next = (int) $row->last_number + 1;

            DB::table('billing_sequences')->where('id', $row->id)->update(['last_number' => $next, 'updated_at' => now()]);

            return $next;
        });
    }

    /** Invoices numbered before the counter table existed. */
    private function lastLegacyInvoiceNumber(Center $center, int $year): int
    {
        $prefix = "F-{$year}-";
        $last = Invoice::query()->where('center_id', $center->id)->where('number', 'like', $prefix.'%')->max('number');

        return $last ? (int) substr($last, strlen($prefix)) : 0;
    }
}
