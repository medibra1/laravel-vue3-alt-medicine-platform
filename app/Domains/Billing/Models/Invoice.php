<?php

namespace App\Domains\Billing\Models;

use App\Domains\Auth\Models\User;
use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\Patient;
use App\Domains\Patients\Models\Treatment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $table = 'billing_invoices';

    protected $guarded = ['id'];

    protected $casts = [
        'issued_on' => 'date',
        'cancelled_at' => 'datetime',
        'total' => 'decimal:2',
        'paid_total' => 'decimal:2',
    ];

    /**
     * Derived, never stored: cancelled / paid / partial / unpaid.
     */
    public function status(): string
    {
        if ($this->cancelled_at !== null) {
            return 'cancelled';
        }

        $paid = (float) $this->paid_total;
        $total = (float) $this->total;

        return match (true) {
            $paid >= $total && $total > 0 => 'paid',
            $paid > 0 => 'partial',
            default => 'unpaid',
        };
    }

    public function balance(): float
    {
        return $this->cancelled_at !== null ? 0.0 : round((float) $this->total - (float) $this->paid_total, 2);
    }

    /**
     * Recomputes the cached totals from the lines and payments.
     */
    public function refreshTotals(): void
    {
        $this->update([
            'total' => $this->lines()->sum('total'),
            'paid_total' => $this->payments()->sum('amount'),
        ]);
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
}
