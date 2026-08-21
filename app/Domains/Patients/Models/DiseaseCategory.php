<?php

namespace App\Domains\Patients\Models;

use App\Domains\Common\Models\EnumOption;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class DiseaseCategory extends Model
{
    use HasFactory, HasTranslations;

    protected $table = 'disease_categories';

    protected $guarded = ['id'];

    protected $casts = ['active' => 'bool', 'order' => 'int'];

    public array $translatable = ['label'];

    public function type()
    {
        return $this->belongsTo(EnumOption::class, 'type_option_id');
    }

    public function diseases()
    {
        return $this->hasMany(Disease::class);
    }
}
