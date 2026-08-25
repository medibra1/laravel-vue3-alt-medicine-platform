<?php

namespace App\Domains\Patients\Http\Resources;

use App\Domains\Patients\Models\DiseaseCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin CRUD variant — exposes raw {fr,en} translations (via
 * ->getTranslations()) rather than the locale-resolved string, since
 * this is consumed by an editable form. Do not confuse with the
 * read-only DiseaseCategoryResource used elsewhere in the app (e.g.
 * the Treatment wizard dropdowns) — that one intentionally exposes
 * only the resolved label string.
 *
 * @mixin DiseaseCategory
 */
class DiseaseCategoryAdminResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type_option_id' => $this->type_option_id,
            'code' => $this->code,
            'label' => $this->getTranslations('label'),
            'icon' => $this->icon,
            'order' => $this->order,
            'active' => $this->active,
            'type' => $this->whenLoaded('type', fn () => [
                'id' => $this->type->id,
                'code' => $this->type->code,
                'label' => $this->type->label['fr'] ?? $this->type->code,
            ]),
        ];
    }
}
