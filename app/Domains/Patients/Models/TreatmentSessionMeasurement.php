<?php

namespace App\Domains\Patients\Models;

use App\Domains\Common\Models\EnumOption;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreatmentSessionMeasurement extends Model
{
    use HasFactory;

    protected $table = 'treatment_session_measurements';

    protected $guarded = ['id'];

    /** @return BelongsTo<TreatmentSession, $this> */
    public function treatmentSession(): BelongsTo
    {
        return $this->belongsTo(TreatmentSession::class);
    }

    /** @return BelongsTo<EnumOption, $this> */
    public function measurementType(): BelongsTo
    {
        return $this->belongsTo(EnumOption::class, 'measurement_type_option_id');
    }
}
