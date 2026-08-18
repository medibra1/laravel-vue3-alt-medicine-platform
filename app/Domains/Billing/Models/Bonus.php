<?php

namespace App\Domains\Billing\Models;

use App\Domains\Auth\Models\User;
use App\Domains\Practitioners\Models\Practitioner;
use Illuminate\Database\Eloquent\Model;

class Bonus extends Model
{
    protected $table = 'billing_bonuses';

    protected $guarded = ['id'];

    protected $casts = ['granted_at' => 'date'];

    public function practitioner()
    {
        return $this->belongsTo(Practitioner::class);
    }

    public function payPeriod()
    {
        return $this->belongsTo(PayPeriod::class);
    }

    public function grantedBy()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
