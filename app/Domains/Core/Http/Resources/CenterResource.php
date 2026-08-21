<?php

namespace App\Domains\Core\Http\Resources;

use App\Domains\Core\Models\Center;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Center
 */
class CenterResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'country_id' => $this->country_id,
            'code' => $this->code,
            'name' => $this->name,
            'city' => $this->city,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'active' => $this->active,
            'payroll_mode' => $this->payroll_mode->value,
            'country' => $this->whenLoaded('country', fn () => [
                'id' => $this->country->id,
                'code' => $this->country->code,
                'name' => $this->country->name,
            ]),
        ];
    }
}
