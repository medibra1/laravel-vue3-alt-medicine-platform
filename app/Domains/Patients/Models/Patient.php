<?php

namespace App\Domains\Patients\Models;

use App\Domains\Auth\Models\User;
use App\Domains\Common\Models\EnumOption;
use App\Domains\Core\Models\Center;
use App\Domains\Core\Models\Country;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\ModelStatus\HasStatuses;

class Patient extends Model implements HasMedia
{
    use HasFactory, HasStatuses, InteractsWithMedia;

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

    /** @return BelongsTo<EnumOption, $this> */
    public function religion(): BelongsTo
    {
        return $this->belongsTo(EnumOption::class, 'religion_option_id');
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

    /** @return HasMany<Consent, $this> */
    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    /**
     * Same tiebreak rule already applied to Treatment::sessions() (most
     * recent first, `id` desc to disambiguate two treatments started the
     * same day) — kept here as a single query rather than relying on
     * `treatments` being eager-loaded in a particular order by the caller.
     */
    public function latestTreatment(): ?Treatment
    {
        return $this->treatments()->orderByDesc('started_at')->orderByDesc('id')->first();
    }

    /**
     * A patient's status is never stored on its own — it's entirely derived
     * from its most recent treatment, so the two can never drift apart the
     * way an independent status column would let them. `key` is a stable
     * machine value (for tests/future filtering), `label`/`color` are the
     * display pair, `color` chosen from the same Vuetify semantic palette
     * already used for closure_reason chips elsewhere in this domain.
     *
     * @return array{key: string, label: string, color: string}
     */
    public function derivedStatus(): array
    {
        $treatment = $this->latestTreatment();

        if ($treatment === null) {
            return ['key' => 'new', 'label' => 'Nouveau', 'color' => 'secondary'];
        }

        $status = $treatment->currentStatusName();

        if ($status !== 'closed') {
            return ['key' => 'active', 'label' => 'Actif', 'color' => 'success'];
        }

        return match ($treatment->closure_reason) {
            'resolved' => ['key' => 'completed', 'label' => 'Terminé', 'color' => 'info'],
            'lost_to_follow_up' => ['key' => 'unreachable', 'label' => 'Injoignable', 'color' => 'warning'],
            'protocol_not_followed' => ['key' => 'stopped', 'label' => 'Arrêté', 'color' => 'error'],
            default => ['key' => 'other', 'label' => 'Autre', 'color' => 'secondary'],
        };
    }

    /**
     * 'local' disk on purpose, not 'public' — identity documents and
     * medical files are sensitive, they must never be reachable through a
     * direct public URL. Downloads/thumbnails go through
     * PatientDocumentController, which authorizes against PatientPolicy
     * before streaming from this private disk.
     */
    public function registerMediaCollections(): void
    {
        $mimeTypes = ['image/jpeg', 'image/png', 'application/pdf'];

        $this->addMediaCollection('identity')
            ->useDisk('local')
            ->singleFile()
            ->acceptsMimeTypes($mimeTypes);

        $this->addMediaCollection('medical')
            ->useDisk('local')
            ->acceptsMimeTypes($mimeTypes);

        $this->addMediaCollection('other')
            ->useDisk('local')
            ->acceptsMimeTypes($mimeTypes);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // nonQueued() called before width()/height() on purpose: Conversion
        // is annotated `@mixin ImageDriver` for width()/height() (they're
        // resolved through __call() at runtime, always returning Conversion
        // itself) — but Larastan trusts the mixin's declared `static` return
        // type (ImageDriver), which doesn't have nonQueued(). Calling the
        // one real Conversion method first keeps the chain typed correctly
        // instead of ending it on a magic call.
        // QUEUE_CONNECTION is 'sync' in local/tests here, but explicit
        // beats implicit — the thumb must exist by the time the controller
        // responds, not whenever a queue worker gets to it.
        $this->addMediaConversion('thumb')
            ->performOnCollections('identity', 'medical', 'other')
            ->nonQueued()
            ->width(240)
            ->height(240);
    }
}
