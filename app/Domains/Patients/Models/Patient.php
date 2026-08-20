<?php

namespace App\Domains\Patients\Models;

use App\Domains\Auth\Models\User;
use App\Domains\Core\Models\Center;
use App\Domains\Core\Models\Country;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\ModelStatus\HasStatuses;

class Patient extends Model
{
    use HasFactory, HasStatuses;

    protected $guarded = ['id'];

    protected $casts = ['birth_date' => 'date'];

    /**
     * First real HasStatuses usage in the codebase (see CLAUDE.md
     * "Statuts") — 'draft' is set right after PatientController::storeDraft()
     * creates the row, 'confirmed' after PatientController::confirm()
     * passes full validation. Other domains (Treatment, Appointment,
     * Invoice...) should copy this two-state shape, not necessarily
     * reuse 'confirmed' itself if their domain needs a different name.
     */
    public function currentStatusName(): ?string
    {
        return $this->latestStatus()?->name;
    }

    /** @return BelongsTo<Center, $this> */
    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class, 'intake_center_id');
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<Treatment, $this> */
    public function treatments(): HasMany
    {
        return $this->hasMany(Treatment::class);
    }
}
