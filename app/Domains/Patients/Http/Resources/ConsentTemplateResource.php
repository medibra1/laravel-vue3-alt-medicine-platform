<?php

namespace App\Domains\Patients\Http\Resources;

use App\Domains\Patients\Models\ConsentTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ConsentTemplate
 */
class ConsentTemplateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'version' => $this->version,
            'title' => $this->title,
            'content' => $this->content,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
