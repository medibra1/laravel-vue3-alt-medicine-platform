<?php

namespace App\Domains\Patients\Http\Resources;

use App\Domains\Patients\Models\CareCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CareCategory
 */
class CareCategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'label' => $this->label,
            'items' => CareItemResource::collection($this->whenLoaded('items', fn () => $this->items->where('active', true)->values())),
        ];
    }
}
