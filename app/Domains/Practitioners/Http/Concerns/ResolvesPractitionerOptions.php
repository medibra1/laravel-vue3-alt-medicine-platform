<?php

namespace App\Domains\Practitioners\Http\Concerns;

use App\Domains\Practitioners\Http\Resources\PractitionerOptionResource;
use App\Domains\Practitioners\Models\Practitioner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

trait ResolvesPractitionerOptions
{
    /**
     * A super_admin sees every practitioner (no center filter); anyone
     * else sees who can actually be picked as "Praticien" on their
     * currently active center — see Practitioner::scopeVisibleOnCenter()
     * for what "eligible on a center" means (not just Practitioner.
     * center_id).
     */
    protected function practitionerOptions(Request $request): AnonymousResourceCollection
    {
        $centerId = $request->user()->isSuperAdmin() ? null : getPermissionsTeamId();

        $practitioners = Practitioner::query()
            ->when($centerId, fn ($query) => $query->visibleOnCenter($centerId))
            ->orderBy('full_code')
            ->get();

        return PractitionerOptionResource::collection($practitioners);
    }
}
