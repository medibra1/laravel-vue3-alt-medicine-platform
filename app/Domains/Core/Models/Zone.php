<?php

namespace App\Domains\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Zone extends Model
{
    use HasTranslations;

    protected $guarded = ['id'];

    protected $casts = ['active' => 'bool', 'order' => 'int'];

    public array $translatable = ['name'];

    public function countries()
    {
        return $this->hasMany(Country::class);
    }
}
