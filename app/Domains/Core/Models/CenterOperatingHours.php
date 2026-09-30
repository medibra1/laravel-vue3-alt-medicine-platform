<?php

namespace App\Domains\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * When a center receives the public — drives the agenda grid's display
 * range. Distinct from PractitionerAvailability (when a practitioner can
 * be booked): neither is derived from the other.
 */
class CenterOperatingHours extends Model
{
    use HasFactory;

    protected $table = 'center_operating_hours';

    protected $guarded = ['id'];

    protected $casts = [
        'day_of_week' => 'int',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    /** @return BelongsTo<Center, $this> */
    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }
}
