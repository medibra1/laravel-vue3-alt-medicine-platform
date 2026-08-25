<?php

namespace App\Domains\Patients\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * @property-read TreatmentDiseasePivot|null $pivot Only set when loaded off
 *   Treatment::diseases() (see that relation's ->using()) — null on every
 *   other way of loading a Disease (the plain catalog, DiseaseCategory::diseases()...).
 */
class Disease extends Model
{
    use HasFactory, HasTranslations;

    protected $table = 'diseases';

    protected $guarded = ['id'];

    protected $casts = ['active' => 'bool', 'default_duration_months' => 'int'];

    public array $translatable = ['label', 'description'];

    /** @return BelongsTo<DiseaseCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(DiseaseCategory::class, 'disease_category_id');
    }

    /** @return HasMany<DiseaseSubcase, $this> */
    public function subcases(): HasMany
    {
        return $this->hasMany(DiseaseSubcase::class);
    }
}
