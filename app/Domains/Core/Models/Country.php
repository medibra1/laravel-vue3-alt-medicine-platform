<?php

namespace App\Domains\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Country extends Model
{
    use HasTranslations;

    protected $guarded = ['id'];

    protected $casts = ['active' => 'bool'];

    public array $translatable = ['name'];

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function centers()
    {
        return $this->hasMany(Center::class);
    }
}
