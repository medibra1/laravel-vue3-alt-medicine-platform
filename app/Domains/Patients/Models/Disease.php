<?php

namespace App\Domains\Patients\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Disease extends Model
{
    use HasTranslations;

    protected $table = 'patients_diseases';

    protected $guarded = ['id'];

    protected $casts = ['active' => 'bool', 'default_duration_months' => 'int'];

    public array $translatable = ['label', 'description'];

    public function category()
    {
        return $this->belongsTo(DiseaseCategory::class, 'disease_category_id');
    }

    public function subcases()
    {
        return $this->hasMany(DiseaseSubcase::class);
    }
}
