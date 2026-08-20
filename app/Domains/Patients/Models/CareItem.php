<?php

namespace App\Domains\Patients\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class CareItem extends Model
{
    use HasFactory, HasTranslations;

    protected $table = 'patients_care_items';

    protected $guarded = ['id'];

    protected $casts = ['active' => 'bool', 'order' => 'int'];

    public array $translatable = ['label', 'description'];

    /** @return BelongsTo<CareCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CareCategory::class, 'care_category_id');
    }
}
