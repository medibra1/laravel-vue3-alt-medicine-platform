<?php

namespace App\Domains\Practitioners\Models;

use App\Domains\Auth\Models\User;
use App\Domains\Billing\Models\Bonus;
use App\Domains\Billing\Models\Employment;
use App\Domains\Billing\Models\PayPeriodShare;
use App\Domains\Billing\Models\SalaryAdvance;
use App\Domains\Core\Models\Center;
use App\Domains\Core\Models\Grade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\ModelStatus\HasStatuses;

class Practitioner extends Model
{
    use HasStatuses;

    protected $guarded = ['id', 'full_code'];

    protected $casts = ['level' => 'int', 'hired_at' => 'date'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Center, $this> */
    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    /** @return BelongsTo<Grade, $this> */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    /** @return HasMany<PractitionerAttendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(PractitionerAttendance::class);
    }

    /** @return HasMany<SalaryAdvance, $this> */
    public function salaryAdvances(): HasMany
    {
        return $this->hasMany(SalaryAdvance::class);
    }

    /** @return HasMany<PayPeriodShare, $this> */
    public function payPeriodShares(): HasMany
    {
        return $this->hasMany(PayPeriodShare::class);
    }

    /** @return HasMany<Employment, $this> */
    public function employments(): HasMany
    {
        return $this->hasMany(Employment::class);
    }

    /** @return HasMany<Bonus, $this> */
    public function bonuses(): HasMany
    {
        return $this->hasMany(Bonus::class);
    }

    // treatments(): HasMany<Treatment> — added once App\Domains\Patients\Models\Treatment exists.
}
