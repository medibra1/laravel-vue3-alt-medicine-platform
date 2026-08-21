<?php

namespace App\Domains\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Zone extends Model
{
    use HasFactory, HasTranslations;

    protected $guarded = ['id'];

    protected $casts = ['active' => 'bool', 'order' => 'int'];

    public array $translatable = ['name'];

    /** @return HasMany<Country, $this> */
    public function countries(): HasMany
    {
        return $this->hasMany(Country::class);
    }
}
