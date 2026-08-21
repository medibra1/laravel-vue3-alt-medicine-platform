<?php

namespace App\Domains\Core\Http\Concerns;

use App\Domains\Core\Http\Resources\CenterOptionResource;
use App\Domains\Core\Models\Center;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Only super_admin picks a center explicitly (a manager is always
 * scoped to their own, forced server-side) — repeated identically
 * across every controller that offers a center dropdown.
 */
trait ResolvesCenterOptions
{
    protected function centerOptions(Request $request): AnonymousResourceCollection
    {
        $centers = $request->user()->isSuperAdmin()
            ? Center::query()->orderBy('code')->get()
            : collect();

        return CenterOptionResource::collection($centers);
    }
}
