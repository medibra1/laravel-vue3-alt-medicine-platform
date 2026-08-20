<?php

namespace App\Domains\Patients\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class CareCategory extends Model
{
    use HasFactory, HasTranslations;

    protected $table = 'patients_care_categories';

    protected $guarded = ['id'];

    protected $casts = ['active' => 'bool', 'order' => 'int'];

    public array $translatable = ['label'];

    /** @return HasMany<CareItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CareItem::class);
    }
}
