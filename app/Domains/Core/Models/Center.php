<?php

namespace App\Domains\Core\Models;

use App\Domains\Core\Enums\PayrollMode;
use Illuminate\Database\Eloquent\Model;

class Center extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['active' => 'bool', 'payroll_mode' => PayrollMode::class];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function practitioners()
    {
        return $this->hasMany(\App\Domains\Practitioners\Models\Practitioner::class);
    }

    public function employments()
    {
        return $this->hasMany(\App\Domains\Billing\Models\Employment::class);
    }
}
