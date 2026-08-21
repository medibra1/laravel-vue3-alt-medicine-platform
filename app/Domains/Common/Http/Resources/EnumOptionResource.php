<?php

namespace App\Domains\Common\Http\Resources;

use App\Domains\Common\Models\EnumOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin-editing shape — exposes the raw {fr, en} label rather than a
 * locale-resolved string, since the form needs both locales editable.
 *
 * @mixin EnumOption
 */
class EnumOptionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'enum_type' => $this->enum_type,
            'domain' => $this->domain,
            'code' => $this->code,
            'label' => [
                'fr' => $this->label['fr'] ?? '',
                'en' => $this->label['en'] ?? '',
            ],
            'order' => $this->order,
            'active' => $this->active,
        ];
    }
}
