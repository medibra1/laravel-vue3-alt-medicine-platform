<?php

namespace App\Domains\Core\Models;

use App\Domains\Auth\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A public holiday or one-off closure of a whole center. Blocking for
 * every practitioner of the center — the center-wide counterpart of
 * PractitionerTimeOff (see NoCenterClosureConflict,
 * AvailableSlotsResolver). Unrelated to CenterOperatingHours, which only
 * sets the agenda's display range. Whole days only; ends_on is inclusive.
 */
class CenterClosure extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    /** @return BelongsTo<Center, $this> */
    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Closures of this center that include the given calendar day.
     *
     * @param  Builder<CenterClosure>  $query
     * @return Builder<CenterClosure>
     */
    public function scopeCovering(Builder $query, int $centerId, CarbonInterface $date): Builder
    {
        return $query->where('center_id', $centerId)
            ->whereDate('starts_on', '<=', $date->toDateString())
            ->whereDate('ends_on', '>=', $date->toDateString());
    }
}
