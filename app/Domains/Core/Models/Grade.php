<?php

namespace App\Domains\Core\Models;

use App\Domains\Practitioners\Models\Practitioner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Grade extends Model
{
    use HasTranslations;

    protected $guarded = ['id'];

    protected $casts = ['active' => 'bool', 'order' => 'int', 'coefficient' => 'decimal:2'];

    public array $translatable = ['label'];

    /** @return HasMany<Practitioner, $this> */
    public function practitioners(): HasMany
    {
        return $this->hasMany(Practitioner::class);
    }
}
