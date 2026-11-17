<?php

namespace App\Domains\Core\Models;

use App\Domains\Patients\Models\CareItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CenterCareItemPrice extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['price' => 'decimal:2'];

    /** @return BelongsTo<Center, $this> */
    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    /** @return BelongsTo<CareItem, $this> */
    public function careItem(): BelongsTo
    {
        return $this->belongsTo(CareItem::class);
    }
}
