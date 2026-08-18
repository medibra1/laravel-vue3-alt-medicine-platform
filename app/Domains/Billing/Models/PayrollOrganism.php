<?php

namespace App\Domains\Billing\Models;

use App\Domains\Common\Models\EnumOption;
use App\Domains\Core\Models\Country;
use Illuminate\Database\Eloquent\Model;

class PayrollOrganism extends Model
{
    protected $table = 'billing_payroll_organisms';

    protected $guarded = ['id'];

    protected $casts = ['active' => 'bool'];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function type()
    {
        return $this->belongsTo(EnumOption::class, 'type_option_id');
    }

    public function charges()
    {
        return $this->hasMany(PayrollCharge::class, 'organism_id');
    }
}
