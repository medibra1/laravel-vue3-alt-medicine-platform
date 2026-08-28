<?php

namespace App\Domains\Patients\Models;

use App\Domains\Auth\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A recorded acceptance of one ConsentTemplate by a patient — a real
 * model (not just a Media entry on Patient like the rest of "documents
 * patient") because it needs relational data to query (who consented,
 * to which template version, when), not just a file to store. The
 * generated PDF is still attached via HasMedia ('document', single
 * file) — no reinvented storage, same package already used on Patient.
 */
class Consent extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $guarded = ['id'];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<ConsentTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ConsentTemplate::class, 'consent_template_id');
    }

    /** @return BelongsTo<User, $this> */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    /**
     * Same 'local' (private) disk as Patient's documents — a signed
     * consent PDF is at least as sensitive, served only through an
     * authenticated route, never a direct public URL.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('document')
            ->useDisk('local')
            ->singleFile();
    }
}
