<?php

namespace App\Domains\Patients\Http\Resources;

use App\Domains\Patients\Models\Disease;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin CRUD variant — exposes raw {fr,en} translations (via
 * ->getTranslations()) rather than the locale-resolved string, since
 * this is consumed by an editable form. Do not confuse with the
 * read-only DiseaseResource used elsewhere in the app (e.g. the
 * Treatment wizard) — that one intentionally exposes only the
 * resolved label string.
 *
 * @mixin Disease
 */
class DiseaseAdminResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'disease_category_id' => $this->disease_category_id,
            'code' => $this->code,
            'label' => $this->getTranslations('label'),
            'description' => $this->getTranslations('description'),
            'default_duration_months' => $this->default_duration_months,
            'active' => $this->active,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'code' => $this->category->code,
                'label' => $this->category->label,
            ]),
        ];
    }
}
