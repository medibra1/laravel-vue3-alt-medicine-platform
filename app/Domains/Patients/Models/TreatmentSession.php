<?php

namespace App\Domains\Patients\Models;

use App\Domains\Auth\Models\User;
use App\Domains\Practitioners\Models\Practitioner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TreatmentSession extends Model
{
    use HasFactory;

    protected $table = 'treatment_sessions';

    protected $guarded = ['id'];

    protected $casts = [
        'session_date' => 'date',
        'duration_minutes' => 'int',
    ];

    /** @return BelongsTo<Treatment, $this> */
    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }

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

    /** @return HasMany<TreatmentSessionDiseaseProgress, $this> */
    public function diseaseProgress(): HasMany
    {
        return $this->hasMany(TreatmentSessionDiseaseProgress::class, 'treatment_session_id');
    }

    /** @return BelongsToMany<CareItem, $this> */
    public function careItems(): BelongsToMany
    {
        return $this->belongsToMany(CareItem::class, 'treatment_session_care_items', 'treatment_session_id', 'care_item_id');
    }
}
