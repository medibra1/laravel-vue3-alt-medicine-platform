<?php

namespace App\Domains\Core\Http\Controllers\Admin;

use App\Domains\Core\Http\Requests\StoreZoneRequest;
use App\Domains\Core\Http\Requests\UpdateZoneRequest;
use App\Domains\Core\Http\Resources\ZoneResource;
use App\Domains\Core\Models\Zone;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ZoneController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Zone::class);

        $query = Zone::query();

        $zones = QueryBuilder::for($query)
            ->allowedFilters(
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($query) use ($value) {
                        $query->where('code', 'like', "%{$value}%")
                            ->orWhere('name', 'like', "%{$value}%");
                    });
                }),
            )
            ->allowedSorts('code', 'order', 'created_at')
            ->defaultSort('order')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        $zones->setCollection(
            ZoneResource::collection($zones->getCollection())->collection,
        );

        return Inertia::render('Admin/Zones/Index', [
            'zones' => $zones,
            'filters' => $request->only(['filter', 'sort']),
        ]);
    }

    public function store(StoreZoneRequest $request): RedirectResponse
    {
        Zone::create($request->validated());

        return redirect()->route('admin.zones.index');
    }

    public function update(UpdateZoneRequest $request, Zone $zone): RedirectResponse
    {
        $zone->update($request->validated());

        return redirect()->route('admin.zones.index');
    }

    public function destroy(Zone $zone): RedirectResponse
    {
        Gate::authorize('delete', $zone);

        $zone->delete();

        return redirect()->route('admin.zones.index');
    }
}
