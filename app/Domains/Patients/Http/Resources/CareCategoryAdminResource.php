<?php

namespace App\Domains\Patients\Http\Resources;

use App\Domains\Patients\Models\CareCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin CRUD variant of CareCategoryResource — exposes raw
 * ->getTranslations() ({fr, en}) rather than the locale-resolved
 * string, since this is consumed by an editable form
 * (AppTranslatableInput), not a read-only dropdown. Do not reuse
 * CareCategoryResource for this: it intentionally stays read-only,
 * consumed elsewhere (see CLAUDE.md "Introduction des API Resources").
 *
 * @mixin CareCategory
 */
class CareCategoryAdminResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'label' => $this->getTranslations('label'),
            'order' => $this->order,
            'active' => $this->active,
        ];
    }
}
