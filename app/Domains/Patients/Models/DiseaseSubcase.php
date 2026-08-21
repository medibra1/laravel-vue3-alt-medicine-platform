<?php

namespace App\Domains\Patients\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class DiseaseSubcase extends Model
{
    use HasTranslations;

    protected $table = 'disease_subcases';

    protected $guarded = ['id'];

    protected $casts = ['active' => 'bool', 'order' => 'int'];

    public array $translatable = ['label', 'description'];

    public function disease()
    {
        return $this->belongsTo(Disease::class);
    }
}
