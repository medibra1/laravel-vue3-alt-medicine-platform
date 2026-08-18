<?php

namespace App\Domains\Billing\Models;

use App\Domains\Auth\Models\User;
use App\Domains\Core\Models\Center;
use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStatus\HasStatuses;

class PayPeriod extends Model
{
    use HasStatuses;

    protected $table = 'billing_pay_periods';

    protected $guarded = ['id'];

    protected $casts = ['starts_at' => 'date', 'ends_at' => 'date'];

    public function center()
    {
        return $this->belongsTo(Center::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function shares()
    {
        return $this->hasMany(PayPeriodShare::class);
    }
}
