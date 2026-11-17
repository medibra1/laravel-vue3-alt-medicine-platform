<?php

namespace App\Domains\Billing\Models;

use App\Domains\Common\Models\EnumOption;
use App\Domains\Core\Models\Center;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    public const CATEGORY_TYPE = 'expense.category';

    protected $table = 'billing_expenses';

    protected $guarded = ['id'];

    protected $casts = [
        'spent_on' => 'date',
        'amount' => 'decimal:2',
    ];

    /** @return BelongsTo<Center, $this> */
    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    /** @return BelongsTo<EnumOption, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(EnumOption::class, 'category_option_id');
    }
}
