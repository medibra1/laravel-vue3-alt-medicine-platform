<?php

namespace App\Domains\Practitioners\Models;

use App\Domains\Auth\Models\User;
use App\Domains\Billing\Models\Bonus;
use App\Domains\Billing\Models\Employment;
use App\Domains\Billing\Models\PayPeriodShare;
use App\Domains\Billing\Models\SalaryAdvance;
use App\Domains\Core\Models\Center;
use App\Domains\Core\Models\Grade;
use App\Domains\Patients\Models\Treatment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStatus\HasStatuses;

class Practitioner extends Model
{
    use HasFactory, HasStatuses;

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

    /** @return HasMany<Treatment, $this> */
    public function treatments(): HasMany
    {
        return $this->hasMany(Treatment::class);
    }

    /**
     * A practitioner is "visible" on a center either by
     * Practitioner.center_id (the common case: a row with no linked
     * user_id, or whose only center is the one it was created on) OR
     * by holding a 'practitioner' role assignment on that center via
     * model_has_roles (a real multi-center practitioner, or a manager
     * who also has practitioner access there — see
     * RolePermissions::manager()'s docblock,
     * UserController::ensurePractitionerAccess()).
     *
     * center_id alone made a real multi-center practitioner invisible
     * on every center but the one their row happens to point to — found
     * via real usage both in the Treatment wizard's "Praticien" select
     * (ResolvesPractitionerOptions) and in the admin Praticiens list
     * itself (PractitionerController::index()), which both used to
     * filter on center_id directly before switching to this scope.
     * Only the role-based check needs user_id (nullable on Practitioner
     * — most rows predate any linked account), so it's scoped with
     * whereNotNull rather than assumed present.
     *
     * @param  Builder<Practitioner>  $query
     * @return Builder<Practitioner>
     */
    public function scopeVisibleOnCenter(Builder $query, int $centerId): Builder
    {
        return $query->where(function (Builder $query) use ($centerId) {
            $query->where('center_id', $centerId)
                ->orWhere(fn (Builder $query) => $query->whereNotNull('user_id')
                    ->whereIn('user_id', self::userIdsWithPractitionerAccess($centerId)));
        });
    }

    /**
     * @return array<int, int>
     */
    private static function userIdsWithPractitionerAccess(int $centerId): array
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'practitioner')
            ->where('model_has_roles.team_id', $centerId)
            ->pluck('model_has_roles.model_id')
            ->all();
    }
}
