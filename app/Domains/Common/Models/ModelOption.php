<?php

namespace App\Domains\Common\Models;

use Illuminate\Database\Eloquent\Model;

class ModelOption extends Model
{
    protected $guarded = ['id'];

    public function model()
    {
        return $this->morphTo();
    }

    public function option()
    {
        return $this->belongsTo(EnumOption::class, 'option_id');
    }
}
