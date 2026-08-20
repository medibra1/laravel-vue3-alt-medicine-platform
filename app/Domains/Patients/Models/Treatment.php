<?php

namespace App\Domains\Patients\Models;

use App\Domains\Auth\Models\User;
use App\Domains\Core\Models\Center;
use App\Domains\Practitioners\Models\Practitioner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\ModelStatus\HasStatuses;

class Treatment extends Model
{
    use HasFactory, HasStatuses;

    protected $table = 'patients_treatments';

    protected $guarded = ['id'];

    protected $casts = [
        'started_at' => 'date',
        'ended_at' => 'date',
        'outcome_percentage' => 'int',
    ];

    /**
     * Same two-state-plus shape as Patient (see Patient::currentStatusName()
     * docblock) but with two extra values (ongoing/closed) — draft/confirmed
     * covers the resilient-wizard lifecycle, ongoing/closed track the actual
     * care progress after confirmation.
     */
    public function currentStatusName(): ?string
    {
        return $this->latestStatus()?->name;
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Practitioner, $this> */
    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class);
    }

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

    /** @return BelongsToMany<Disease, $this> */
    public function diseases(): BelongsToMany
    {
        return $this->belongsToMany(Disease::class, 'patients_treatment_diseases');
    }

    /** @return HasMany<TreatmentSession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(TreatmentSession::class);
    }
}
