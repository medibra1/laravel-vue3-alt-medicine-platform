<?php

namespace App\Domains\Patients\Http\Resources;

use App\Domains\Patients\Models\CareItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin CRUD variant of CareItemResource — exposes raw
 * ->getTranslations() ({fr, en}) rather than the locale-resolved
 * string, since this is consumed by an editable form
 * (AppTranslatableInput), not a read-only dropdown. Do not reuse
 * CareItemResource for this: it intentionally stays read-only,
 * consumed elsewhere (see CLAUDE.md "Introduction des API Resources").
 *
 * @mixin CareItem
 */
class CareItemAdminResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'care_category_id' => $this->care_category_id,
            'code' => $this->code,
            'label' => $this->getTranslations('label'),
            'description' => $this->getTranslations('description'),
            'order' => $this->order,
            'active' => $this->active,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'code' => $this->category->code,
                'label' => $this->category->label,
            ]),
        ];
    }
}
