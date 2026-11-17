<?php

namespace App\Domains\Billing\Models;

use App\Domains\Auth\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const METHODS = ['cash', 'card', 'mobile_money', 'bank_transfer', 'cheque', 'other'];

    protected $table = 'billing_payments';

    protected $guarded = ['id'];

    protected $casts = [
        'paid_on' => 'date',
        'amount' => 'decimal:2',
    ];

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<User, $this> */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
