<?php

namespace App\Domains\Core\Http\Controllers\Admin;

use App\Domains\Core\Http\Requests\StoreCountryRequest;
use App\Domains\Core\Http\Requests\UpdateCountryRequest;
use App\Domains\Core\Http\Resources\CountryResource;
use App\Domains\Core\Http\Resources\ZoneResource;
use App\Domains\Core\Models\Country;
use App\Domains\Core\Models\Zone;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CountryController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Country::class);

        $query = Country::query()->with('zone');

        $countries = QueryBuilder::for($query)
            ->allowedFilters(
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($query) use ($value) {
                        $query->where('code', 'like', "%{$value}%")
                            ->orWhere('name', 'like', "%{$value}%");
                    });
                }),
                AllowedFilter::exact('zone_id'),
            )
            ->allowedSorts('code', 'name', 'created_at')
            ->defaultSort('code')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        $countries->setCollection(
            CountryResource::collection($countries->getCollection())->collection,
        );

        return Inertia::render('Admin/Countries/Index', [
            'countries' => $countries,
            'filters' => $request->only(['filter', 'sort']),
            'zones' => ZoneResource::collection(Zone::query()->orderBy('order')->get()),
        ]);
    }

    public function store(StoreCountryRequest $request): RedirectResponse
    {
        Country::create($request->validated());

        return redirect()->route('admin.countries.index');
    }

    public function update(UpdateCountryRequest $request, Country $country): RedirectResponse
    {
        $country->update($request->validated());

        return redirect()->route('admin.countries.index');
    }

    public function destroy(Country $country): RedirectResponse
    {
        Gate::authorize('delete', $country);

        $country->delete();

        return redirect()->route('admin.countries.index');
    }
}
