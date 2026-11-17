<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Invoice;
use App\Domains\Core\Models\Center;

/**
 * Per-center, per-year sequence: F-2026-00042. Same MAX+1 approach as the
 * other number generators of this project; the unique(center_id, number)
 * index is the real guard against a concurrent duplicate.
 */
class InvoiceNumberGenerator
{
    public function next(Center $center, int $year): string
    {
        $prefix = "F-{$year}-";

        $last = Invoice::query()
            ->where('center_id', $center->id)
            ->where('number', 'like', $prefix.'%')
            ->max('number');

        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
