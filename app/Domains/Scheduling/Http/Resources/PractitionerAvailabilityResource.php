<?php

namespace App\Domains\Scheduling\Http\Resources;

use App\Domains\Scheduling\Models\PractitionerAvailability;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PractitionerAvailability
 */
class PractitionerAvailabilityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'practitioner_id' => $this->practitioner_id,
            'day_of_week' => $this->day_of_week,
            'start_time' => $this->start_time->format('H:i'),
            'end_time' => $this->end_time->format('H:i'),
            'practitioner' => $this->whenLoaded('practitioner', fn () => [
                'id' => $this->practitioner->id,
                'first_name' => $this->practitioner->first_name,
                'last_name' => $this->practitioner->last_name,
                'full_code' => $this->practitioner->full_code,
            ]),
        ];
    }
}
