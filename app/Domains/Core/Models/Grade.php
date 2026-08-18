<?php

namespace App\Domains\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Grade extends Model
{
    use HasTranslations;

    protected $guarded = ['id'];

    protected $casts = ['active' => 'bool', 'order' => 'int', 'coefficient' => 'decimal:2'];

    public array $translatable = ['label'];

    public function practitioners()
    {
        return $this->hasMany(\App\Domains\Practitioners\Models\Practitioner::class);
    }
}
