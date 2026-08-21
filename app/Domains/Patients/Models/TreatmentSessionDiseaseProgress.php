<?php

namespace App\Domains\Patients\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreatmentSessionDiseaseProgress extends Model
{
    use HasFactory;

    protected $table = 'treatment_session_disease_progress';

    protected $guarded = ['id'];

    protected $casts = ['outcome_percentage' => 'int'];

    /** @return BelongsTo<TreatmentSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(TreatmentSession::class, 'treatment_session_id');
    }

    /** @return BelongsTo<Disease, $this> */
    public function disease(): BelongsTo
    {
        return $this->belongsTo(Disease::class);
    }
}
