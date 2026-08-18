<?php

namespace App\Domains\Practitioners\Models;

use App\Domains\Auth\Models\User;
use App\Domains\Core\Models\Center;
use Illuminate\Database\Eloquent\Model;

class PractitionerAttendance extends Model
{
    protected $table = 'practitioners_attendances';

    protected $guarded = ['id'];

    protected $casts = ['date' => 'date', 'present' => 'bool'];

    public function practitioner()
    {
        return $this->belongsTo(Practitioner::class);
    }

    public function center()
    {
        return $this->belongsTo(Center::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopePresent($query)
    {
        return $query->where('present', true);
    }

    public function scopeBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }
}
