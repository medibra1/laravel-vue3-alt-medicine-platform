<?php

namespace App\Domains\Billing\Services;

use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\CareCategory;

/**
 * What a center sells, with its own prices: the two session rates, one
 * entry per flat-priced care category and one per priced care item.
 * Never-billed categories and items without a price are left out — an
 * invoice line for anything else is a free line.
 */
class BillableCatalogResolver
{
    /**
     * @return array<int, array{key: string, label: string, group: string, unit_price: float, care_item_id: int|null, care_category_id: int|null}>
     */
    public function handle(Center $center): array
    {
        $catalog = [];
        $sessions = __('billing.sessions');

        foreach (['session_price_in_person' => __('billing.session_in_person'), 'session_price_remote' => __('billing.session_remote')] as $setting => $label) {
            $price = (float) $center->setting($setting, 0);

            if ($price > 0) {
                $catalog[] = ['key' => $setting, 'label' => $label, 'group' => $sessions, 'unit_price' => $price, 'care_item_id' => null, 'care_category_id' => null];
            }
        }

        $itemPrices = $center->careItemPrices()->pluck('price', 'care_item_id');
        $categoryPrices = $center->careCategoryPrices()->pluck('price', 'care_category_id');

        $categories = CareCategory::query()->where('active', true)->where('pricing_mode', '!=', CareCategory::PRICING_NONE)
            ->with(['items' => fn ($query) => $query->where('active', true)])->orderBy('order')->get();

        foreach ($categories as $category) {
            $group = (string) $category->label;

            if ($category->pricing_mode === CareCategory::PRICING_CATEGORY) {
                $price = (float) ($categoryPrices[$category->id] ?? 0);

                if ($price > 0) {
                    $catalog[] = ['key' => "category-{$category->id}", 'label' => $group, 'group' => $group, 'unit_price' => $price, 'care_item_id' => null, 'care_category_id' => $category->id];
                }

                continue;
            }

            foreach ($category->items as $item) {
                $price = (float) ($itemPrices[$item->id] ?? 0);

                if ($price > 0) {
                    $catalog[] = ['key' => "item-{$item->id}", 'label' => (string) $item->label, 'group' => $group, 'unit_price' => $price, 'care_item_id' => $item->id, 'care_category_id' => null];
                }
            }
        }

        return $catalog;
    }
}
