<?php

namespace App\Domains\Billing\Models;

use App\Domains\Core\Models\Country;
use Illuminate\Database\Eloquent\Model;

class PayrollCharge extends Model
{
    protected $table = 'billing_payroll_charges';

    protected $guarded = ['id'];

    protected $casts = ['active' => 'bool', 'rate_percent' => 'decimal:2'];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function organism()
    {
        return $this->belongsTo(PayrollOrganism::class, 'organism_id');
    }

    public function scopeEmployer($query)
    {
        return $query->where('charge_type', 'employer');
    }

    public function scopeEmployee($query)
    {
        return $query->where('charge_type', 'employee');
    }
}
