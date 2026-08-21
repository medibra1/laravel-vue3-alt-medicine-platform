<?php

namespace App\Domains\Patients\Models;

use App\Domains\Common\Models\EnumOption;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class DiseaseCategory extends Model
{
    use HasFactory, HasTranslations;

    protected $table = 'disease_categories';

    protected $guarded = ['id'];

    protected $casts = ['active' => 'bool', 'order' => 'int'];

    public array $translatable = ['label'];

    /** @return BelongsTo<EnumOption, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(EnumOption::class, 'type_option_id');
    }

    /** @return HasMany<Disease, $this> */
    public function diseases(): HasMany
    {
        return $this->hasMany(Disease::class);
    }
}
