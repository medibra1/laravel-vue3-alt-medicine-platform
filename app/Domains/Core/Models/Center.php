<?php

namespace App\Domains\Core\Models;

use App\Domains\Billing\Models\Employment;
use App\Domains\Core\Enums\PayrollMode;
use App\Domains\Core\Support\Modules;
use App\Domains\Practitioners\Models\Practitioner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Center extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'active' => 'bool',
        'payroll_mode' => PayrollMode::class,
        'modules' => 'array',
        'settings' => 'array',
    ];

    /**
     * Modules switched on for this center — null in base means all.
     *
     * @return array<int, string>
     */
    public function enabledModules(): array
    {
        return Modules::normalize($this->modules) ?? Modules::all();
    }

    /**
     * A per-center default (session prices, durations...) stored in the
     * `settings` JSON column.
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /** @return HasMany<CenterCareItemPrice, $this> */
    public function careItemPrices(): HasMany
    {
        return $this->hasMany(CenterCareItemPrice::class);
    }

    /** @return HasMany<CenterCareCategoryPrice, $this> */
    public function careCategoryPrices(): HasMany
    {
        return $this->hasMany(CenterCareCategoryPrice::class);
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return HasMany<Practitioner, $this> */
    public function practitioners(): HasMany
    {
        return $this->hasMany(Practitioner::class);
    }

    /** @return HasMany<CenterOperatingHours, $this> */
    public function operatingHours(): HasMany
    {
        return $this->hasMany(CenterOperatingHours::class);
    }

    /** @return HasMany<CenterClosure, $this> */
    public function closures(): HasMany
    {
        return $this->hasMany(CenterClosure::class);
    }

    /** @return HasMany<Employment, $this> */
    public function employments(): HasMany
    {
        return $this->hasMany(Employment::class);
    }
}
