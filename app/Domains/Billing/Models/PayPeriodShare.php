<?php

namespace App\Domains\Billing\Models;

use App\Domains\Practitioners\Models\Practitioner;
use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStatus\HasStatuses;

class PayPeriodShare extends Model
{
    use HasStatuses;

    protected $table = 'billing_pay_period_shares';

    protected $guarded = ['id'];

    protected $casts = [
        'attendance_days' => 'int',
        'grade_coefficient_snapshot' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function payPeriod()
    {
        return $this->belongsTo(PayPeriod::class);
    }

    public function practitioner()
    {
        return $this->belongsTo(Practitioner::class);
    }

    public function salaryAdvances()
    {
        return $this->hasMany(SalaryAdvance::class);
    }
}
