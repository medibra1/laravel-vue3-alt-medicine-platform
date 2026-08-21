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
            'category_id' => $this->disease_category_id,
            'category_label' => $this->whenLoaded('category', fn () => $this->category->label),
        ];
    }
}
