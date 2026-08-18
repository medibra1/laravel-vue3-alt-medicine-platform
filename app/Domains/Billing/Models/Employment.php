<?php

namespace App\Domains\Billing\Models;

use App\Domains\Core\Models\Center;
use App\Domains\Practitioners\Models\Practitioner;
use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStatus\HasStatuses;

class Employment extends Model
{
    use HasStatuses;

    protected $table = 'billing_employments';

    protected $guarded = ['id'];

    protected $casts = ['started_at' => 'date', 'ended_at' => 'date'];

    public function practitioner()
    {
        return $this->belongsTo(Practitioner::class);
    }

    public function center()
    {
        return $this->belongsTo(Center::class);
    }
}
