<?php

namespace App\Domains\Patients\Http\Resources;

use App\Domains\Patients\Models\CareItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CareItem
 */
class CareItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'label' => $this->label,
            'category_label' => $this->whenLoaded('category', fn () => $this->category->label),
        ];
    }
}
