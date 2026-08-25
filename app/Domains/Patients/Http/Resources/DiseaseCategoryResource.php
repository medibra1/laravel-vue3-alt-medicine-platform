<?php

namespace App\Domains\Patients\Http\Resources;

use App\Domains\Patients\Models\DiseaseCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DiseaseCategory
 */
class DiseaseCategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            // ->label goes through HasTranslations' accessor here (resource
            // toArray reads magic attributes, not raw column casts), unlike
            // a bare ->get()->toArray() which would serialize the raw
            // translatable JSON column instead of the resolved string.
            'label' => $this->label,
            'icon' => $this->icon,
        ];
    }
}
