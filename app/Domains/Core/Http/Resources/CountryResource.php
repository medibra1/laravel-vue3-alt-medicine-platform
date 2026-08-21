<?php

namespace App\Domains\Core\Http\Resources;

use App\Domains\Core\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Country
 */
class CountryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'zone_id' => $this->zone_id,
            'code' => $this->code,
            // ->getTranslations() (not ->name) — this record needs both
            // locales exposed for editing, not just the app-locale-
            // resolved string (see AppTranslatableInput.vue doc comment).
            'name' => $this->getTranslations('name'),
            'active' => $this->active,
            'zone' => $this->whenLoaded('zone', fn () => [
                'id' => $this->zone->id,
                'code' => $this->zone->code,
                'name' => $this->zone->name,
            ]),
        ];
    }
}
