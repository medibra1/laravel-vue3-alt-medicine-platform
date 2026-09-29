<?php

namespace App\Domains\Scheduling\Http\Resources;

use App\Domains\Scheduling\Models\PractitionerTimeOff;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PractitionerTimeOff */
class PractitionerTimeOffResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'practitioner_id' => $this->practitioner_id,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on->toDateString(),
            'reason' => $this->reason,
            'notes' => $this->notes,
        ];
    }
}
