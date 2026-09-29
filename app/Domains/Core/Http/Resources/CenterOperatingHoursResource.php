<?php

namespace App\Domains\Core\Http\Resources;

use App\Domains\Core\Models\CenterOperatingHours;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CenterOperatingHours
 */
class CenterOperatingHoursResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'center_id' => $this->center_id,
            'day_of_week' => $this->day_of_week,
            'start_time' => $this->start_time->format('H:i'),
            'end_time' => $this->end_time->format('H:i'),
        ];
    }
}
