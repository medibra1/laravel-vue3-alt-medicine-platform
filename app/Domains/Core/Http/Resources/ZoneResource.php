<?php

namespace App\Domains\Core\Http\Resources;

use App\Domains\Core\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Zone
 */
class ZoneResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            // ->getTranslations() (not ->name) — this record needs both
            // locales exposed for editing, not just the app-locale-
            // resolved string (see AppTranslatableInput.vue doc comment).
            'name' => $this->getTranslations('name'),
            'order' => $this->order,
            'active' => $this->active,
        ];
    }
}
