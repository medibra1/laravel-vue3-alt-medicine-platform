<?php

namespace App\Domains\Scheduling\Models;

use App\Domains\Practitioners\Models\Practitioner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PractitionerAvailability extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'day_of_week' => 'int',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    /** @return BelongsTo<Practitioner, $this> */
    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class);
    }
}
