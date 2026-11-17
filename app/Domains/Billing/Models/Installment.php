<?php

namespace App\Domains\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class Installment extends Model
{
    protected $table = 'billing_invoice_installments';

    protected $guarded = ['id'];

    protected $casts = [
        'due_on' => 'date',
        'amount' => 'decimal:2',
    ];
}
