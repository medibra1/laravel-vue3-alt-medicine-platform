<?php

namespace App\Domains\Scheduling\Models;

use App\Domains\Auth\Models\User;
use App\Domains\Practitioners\Models\Practitioner;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A one-off, date-bound absence (vacation, sick leave, training...).
 * Unlike PractitionerAvailability — a recurring weekly pattern that is
 * never enforced against center opening hours — a time off is blocking:
 * no appointment can be booked on a covered day (see NoTimeOffConflict,
 * AvailableSlotsResolver). Whole days only; ends_on is inclusive.
 */
class PractitionerTimeOff extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    /** @return BelongsTo<Practitioner, $this> */
    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Time offs of this practitioner that include the given calendar day.
     *
     * @param  Builder<PractitionerTimeOff>  $query
     * @return Builder<PractitionerTimeOff>
     */
    public function scopeCovering(Builder $query, int $practitionerId, CarbonInterface $date): Builder
    {
        return $query->where('practitioner_id', $practitionerId)
            ->whereDate('starts_on', '<=', $date->toDateString())
            ->whereDate('ends_on', '>=', $date->toDateString());
    }
}
