<?php

namespace App\Domains\Billing\Models;

use App\Domains\Auth\Models\User;
use App\Domains\Common\Models\EnumOption;
use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\Patient;
use App\Domains\Patients\Models\Treatment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    public const DISCOUNT_REASON_TYPE = 'invoice.discount_reason';

    protected $table = 'billing_invoices';

    protected $guarded = ['id'];

    protected $casts = [
        'issued_on' => 'date',
        'cancelled_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_total' => 'decimal:2',
        'credited_total' => 'decimal:2',
    ];

    /**
     * Derived, never stored: cancelled / offered / refund_due / credited /
     * paid / partial / unpaid.
     */
    public function status(): string
    {
        if ($this->cancelled_at !== null) {
            return 'cancelled';
        }

        $total = (float) $this->total;
        $paid = (float) $this->paid_total;
        $net = $this->netTotal();

        return match (true) {
            $this->balance() < 0 => 'refund_due',
            // Fully discounted (social case...): nothing to collect.
            (float) $this->subtotal > 0 && $total <= 0 => 'offered',
            $total > 0 && $net <= 0 => 'credited',
            $total > 0 && $paid >= $net => 'paid',
            $paid > 0 => 'partial',
            default => 'unpaid',
        };
    }

    /** What is really owed once credit notes are deducted. */
    public function netTotal(): float
    {
        return round((float) $this->total - (float) $this->credited_total, 2);
    }

    /** Negative when the patient paid more than is owed (refund due). */
    public function balance(): float
    {
        return $this->cancelled_at !== null ? 0.0 : round($this->netTotal() - (float) $this->paid_total, 2);
    }

    /**
     * Recomputes the cached totals from the lines, the discount,
     * payments (refunds are negative payments) and credit notes.
     * `total` is the net to pay: lines minus discount.
     */
    public function refreshTotals(): void
    {
        $subtotal = round((float) $this->lines()->sum('total'), 2);
        $discount = $this->discount_percent !== null
            ? round($subtotal * (float) $this->discount_percent / 100, 2)
            : (float) $this->discount_amount;
        $discount = max(0.0, min($discount, $subtotal));

        $this->update([
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'total' => round($subtotal - $discount, 2),
            'paid_total' => $this->payments()->sum('amount'),
            'credited_total' => $this->creditNotes()->sum('amount'),
        ]);
    }

    /**
     * The schedule with what is paid allocated to the oldest instalment
     * first. An instalment is paid, overdue (past its date and not fully
     * paid), partial or upcoming.
     *
     * @return array<int, array{id: int, due_on: string, amount: float, paid: float, status: string}>
     */
    public function installmentsWithStatus(): array
    {
        $remaining = max(0.0, (float) $this->paid_total);
        $today = now()->toDateString();

        return $this->installments->map(function (Installment $installment) use (&$remaining, $today) {
            $amount = (float) $installment->amount;
            $paid = round(min($amount, $remaining), 2);
            $remaining = round($remaining - $paid, 2);
            $dueOn = $installment->due_on->toDateString();

            return [
                'id' => $installment->id,
                'due_on' => $dueOn,
                'amount' => $amount,
                'paid' => $paid,
                'status' => match (true) {
                    $paid >= $amount => 'paid',
                    $dueOn < $today => 'overdue',
                    $paid > 0 => 'partial',
                    default => 'upcoming',
                },
            ];
        })->all();
    }

    /** What should already have been paid according to the schedule. */
    public function overdueAmount(): float
    {
        if ($this->cancelled_at !== null) {
            return 0.0;
        }

        return round(array_sum(array_map(
            fn (array $row) => $row['status'] === 'overdue' ? $row['amount'] - $row['paid'] : 0.0,
            $this->installmentsWithStatus(),
        )), 2);
    }

    /**
     * @param  Builder<Invoice>  $query
     * @return Builder<Invoice>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('cancelled_at');
    }

    /** @return BelongsTo<Center, $this> */
    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Treatment, $this> */
    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<InvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderByDesc('paid_on')->orderByDesc('id');
    }

    /** @return HasMany<CreditNote, $this> */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class)->orderBy('issued_on')->orderBy('id');
    }

    /** @return HasMany<Installment, $this> */
    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class)->orderBy('due_on')->orderBy('id');
    }

    /** @return BelongsTo<EnumOption, $this> */
    public function discountReason(): BelongsTo
    {
        return $this->belongsTo(EnumOption::class, 'discount_reason_option_id');
    }
}
