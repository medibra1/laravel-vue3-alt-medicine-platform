<?php

namespace App\Domains\Practitioners\Http\Resources;

use App\Domains\Practitioners\Models\Practitioner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Practitioner
 */
class PractitionerOptionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_code' => $this->full_code,
        ];
    }
}
