<?php

namespace App\Domains\Patients\Http\Resources;

use App\Domains\Patients\Models\Disease;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Disease
 */
class DiseaseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'label' => $this->label,
            'description' => $this->description,
            'category_id' => $this->disease_category_id,
            'category_label' => $this->whenLoaded('category', fn () => $this->category->label),
            // Only present when this Disease came off a Treatment's
            // diseases() relation (pivot loaded) — absent on the plain
            // catalog usage (wizard's disease picker options), where there
            // is no treatment_diseases row to read a pivot from at all.
            'actively_tracked' => $this->whenPivotLoaded('treatment_diseases', fn () => (bool) $this->pivot->actively_tracked),
        ];
    }
}
