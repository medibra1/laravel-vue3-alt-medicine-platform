<?php

namespace App\Domains\Patients\Models;

use App\Domains\Auth\Models\User;
use App\Domains\Practitioners\Models\Practitioner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\ModelStatus\HasStatuses;

class TreatmentSession extends Model
{
    use HasFactory, HasStatuses;

    protected $table = 'patients_treatment_sessions';

    protected $guarded = ['id'];

    protected $casts = [
        'session_date' => 'date',
        'duration_minutes' => 'int',
    ];

    public function currentStatusName(): ?string
    {
        return $this->latestStatus()?->name;
    }

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
}
