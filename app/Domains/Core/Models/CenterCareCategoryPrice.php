<?php

namespace App\Domains\Core\Models;

use Illuminate\Database\Eloquent\Model;

class CenterCareCategoryPrice extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['price' => 'decimal:2'];
}
