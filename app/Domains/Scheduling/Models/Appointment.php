<?php

namespace App\Domains\Scheduling\Models;

use App\Domains\Auth\Models\User;
use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\Patient;
use App\Domains\Patients\Models\Treatment;
use App\Domains\Patients\Models\TreatmentSession;
use App\Domains\Practitioners\Models\Practitioner;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'starts_at' => 'datetime',
        'duration_minutes' => 'int',
        'reminder_sent_at' => 'datetime',
    ];

    /**
     * Used by AppointmentConflictChecker/AvailableSlotsResolver — kept as
     * a single accessor so both compute the same window the same way.
     *
     * @return Attribute<CarbonInterface, never>
     */
    protected function endsAt(): Attribute
    {
        return Attribute::make(
            get: fn (): CarbonInterface => $this->starts_at->copy()->addMinutes($this->duration_minutes),
        );
    }

    /** @return BelongsTo<Center, $this> */
    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    /** @return BelongsTo<Practitioner, $this> */
    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class);
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Treatment, $this> */
    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }

    /** @return BelongsTo<TreatmentSession, $this> */
    public function treatmentSession(): BelongsTo
    {
        return $this->belongsTo(TreatmentSession::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
