<?php

namespace App\Domains\Practitioners\Http\Concerns;

use App\Domains\Practitioners\Http\Resources\PractitionerOptionResource;
use App\Domains\Practitioners\Models\Practitioner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

trait ResolvesPractitionerOptions
{
    /**
     * A super_admin sees every practitioner (no center filter); a manager
     * only sees their own center's, regardless of the center picked
     * elsewhere in the same form.
     */
    protected function practitionerOptions(Request $request): AnonymousResourceCollection
    {
        $centerId = $request->user()->isSuperAdmin() ? null : $request->user()->managedCenterId();

        $practitioners = Practitioner::query()
            ->when($centerId, fn ($query) => $query->where('center_id', $centerId))
            ->orderBy('full_code')
            ->get();

        return PractitionerOptionResource::collection($practitioners);
    }
}
