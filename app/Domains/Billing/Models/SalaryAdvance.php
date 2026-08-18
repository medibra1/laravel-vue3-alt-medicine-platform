<?php

namespace App\Domains\Billing\Models;

use App\Domains\Auth\Models\User;
use App\Domains\Practitioners\Models\Practitioner;
use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStatus\HasStatuses;

class SalaryAdvance extends Model
{
    use HasStatuses;

    protected $table = 'billing_salary_advances';

    protected $guarded = ['id'];

    protected $casts = ['granted_at' => 'date'];

    public function practitioner()
    {
        return $this->belongsTo(Practitioner::class);
    }

    public function grantedBy()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function payPeriodShare()
    {
        return $this->belongsTo(PayPeriodShare::class);
    }

    public function scopeOutstanding($query)
    {
        return $query->whereNull('pay_period_share_id');
    }
}
