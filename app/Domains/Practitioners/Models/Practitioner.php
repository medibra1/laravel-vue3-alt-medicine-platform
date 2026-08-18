<?php

namespace App\Domains\Practitioners\Models;

use App\Domains\Auth\Models\User;
use App\Domains\Core\Models\Center;
use App\Domains\Core\Models\Grade;
use App\Domains\Patients\Models\Treatment;
use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStatus\HasStatuses;

class Practitioner extends Model
{
    use HasStatuses;

    protected $guarded = ['id', 'full_code'];

    protected $casts = ['level' => 'int', 'hired_at' => 'date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function center()
    {
        return $this->belongsTo(Center::class);
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function attendances()
    {
        return $this->hasMany(\App\Domains\Practitioners\Models\PractitionerAttendance::class);
    }

    public function salaryAdvances()
    {
        return $this->hasMany(\App\Domains\Billing\Models\SalaryAdvance::class);
    }

    public function payPeriodShares()
    {
        return $this->hasMany(\App\Domains\Billing\Models\PayPeriodShare::class);
    }

    public function employments()
    {
        return $this->hasMany(\App\Domains\Billing\Models\Employment::class);
    }

    public function bonuses()
    {
        return $this->hasMany(\App\Domains\Billing\Models\Bonus::class);
    }

    public function treatments()
    {
        return $this->hasMany(Treatment::class);
    }
}
