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
use Illuminate\Support\Collection;
use Spatie\ModelStatus\HasStatuses;

class Treatment extends Model
{
    use HasFactory, HasStatuses;

    protected $table = 'treatments';

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

    /**
     * True while at least one disease attached to this treatment has no
     * final outcome yet — no progress row at all, or its latest row (across
     * every session) is still 'ongoing'. Deliberately per-disease rather
     * than a single treatment-level flag: the source brief has each disease
     * resolving on its own délai, independently of the others in the same
     * treatment (see CLAUDE.md "Statut global Treatment").
     */
    public function hasUnresolvedDiseases(): bool
    {
        $diseaseIds = $this->diseases()->pluck('diseases.id');

        if ($diseaseIds->isEmpty()) {
            return true;
        }

        $latestOutcomeByDisease = $this->latestOutcomePerDisease();

        foreach ($diseaseIds as $diseaseId) {
            $outcome = $latestOutcomeByDisease->get($diseaseId)?->outcome;

            if ($outcome === null || $outcome === 'ongoing') {
                return true;
            }
        }

        return false;
    }

    /**
     * The most recent treatment_session_disease_progress row per disease,
     * across every session of this treatment (not just the one being
     * edited) — keyed by disease_id. Shared by hasUnresolvedDiseases()
     * (only needs ->outcome) and by callers that need the full row to
     * prefill a new session's starting values (outcome_percentage, notes)
     * or to know which diseases are "locked" from removal once they have
     * tracked history — one query, not duplicated per caller.
     *
     * @return Collection<int, TreatmentSessionDiseaseProgress>
     */
    public function latestOutcomePerDisease(): Collection
    {
        return TreatmentSessionDiseaseProgress::query()
            ->whereIn('treatment_session_id', $this->sessions()->pluck('id'))
            ->orderByDesc('created_at')
            ->get()
            ->unique('disease_id')
            ->keyBy('disease_id');
    }

    /**
     * Call after any disease-progress write (ConfirmTreatmentRequest's
     * implicit first session, TreatmentSessionController::store()/update())
     * — auto-closes the treatment the instant every disease it targets has
     * a final outcome, so a new treatment for the same patient never has to
     * wait on someone remembering to close this one by hand. No-op outside
     * `ongoing` (a draft/confirmed/already-closed treatment is untouched).
     */
    public function refreshClosureStatus(): void
    {
        if ($this->currentStatusName() !== 'ongoing' || $this->hasUnresolvedDiseases()) {
            return;
        }

        $this->update(['closure_reason' => 'resolved']);
        $this->setStatus('closed');
    }

    /**
     * Early/forced closure — abandon, patient lost to follow-up before
     * every disease resolved, or any other reason a manager needs to free
     * the patient up for a new treatment. Only reachable from `ongoing`,
     * enforced in CloseTreatmentRequest rather than here (same split as
     * every other authorize()-vs-model-method boundary in this domain).
     */
    public function manualClose(string $reason, ?string $notes = null): void
    {
        $this->update([
            'closure_reason' => $reason,
            'notes' => $notes ?? $this->notes,
        ]);
        $this->setStatus('closed');
    }

    /**
     * Undo a closure — a mistaken manual close, or a late session that
     * should have kept the treatment open. Only reachable from `closed`,
     * enforced in ReopenTreatmentRequest. Reserved to managers: today
     * that's implicit (every account able to reach this app at all is
     * already a manager or super_admin, see TreatmentPolicy::reopen()) —
     * flagged here so it stays that way once lower-privileged per-raqi
     * accounts exist (see CLAUDE.md "Statut global Treatment").
     */
    public function reopen(): void
    {
        $this->update(['closure_reason' => null]);
        $this->setStatus('ongoing');
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
        return $this->belongsToMany(Disease::class, 'treatment_diseases');
    }

    /**
     * Most recent session first everywhere this relation is loaded — not
     * just in the timeline UI, which used to re-sort on its own. Ordered
     * here once so every future caller gets it for free. `id` desc as a
     * tiebreaker: session_date alone doesn't disambiguate two sessions
     * logged on the same date — the one created last (higher id) should
     * still surface first.
     *
     * @return HasMany<TreatmentSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(TreatmentSession::class)->orderByDesc('session_date')->orderByDesc('id');
    }
}
