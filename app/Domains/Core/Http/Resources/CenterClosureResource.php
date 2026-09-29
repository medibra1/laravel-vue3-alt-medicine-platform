<?php

namespace App\Domains\Core\Http\Resources;

use App\Domains\Core\Models\CenterClosure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CenterClosure */
class CenterClosureResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'center_id' => $this->center_id,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on->toDateString(),
            'label' => $this->label,
            'notes' => $this->notes,
        ];
    }
}
