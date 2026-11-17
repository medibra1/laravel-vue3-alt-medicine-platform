<?php

namespace App\Domains\Patients\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class CareCategory extends Model
{
    use HasFactory, HasTranslations;

    /** One price per care item. */
    public const PRICING_ITEM = 'item';

    /** One flat price per session, whatever the number of items used. */
    public const PRICING_CATEGORY = 'category';

    /** Never billed. */
    public const PRICING_NONE = 'none';

    public const PRICING_MODES = [self::PRICING_ITEM, self::PRICING_CATEGORY, self::PRICING_NONE];

    protected $table = 'care_categories';

    protected $guarded = ['id'];

    protected $casts = ['active' => 'bool', 'order' => 'int'];

    public array $translatable = ['label'];

    /** @return HasMany<CareItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CareItem::class);
    }
}
