<?php

namespace App\Domains\Core\Http\Controllers\Admin;

use App\Domains\Core\Http\Requests\StoreCenterRequest;
use App\Domains\Core\Http\Requests\SyncCenterOperatingHoursRequest;
use App\Domains\Core\Http\Requests\UpdateCenterRequest;
use App\Domains\Core\Http\Resources\CenterResource;
use App\Domains\Core\Models\Center;
use App\Domains\Core\Models\Country;
use App\Domains\Core\Services\CenterCodeGenerator;
use App\Domains\Core\Services\SyncCenterOperatingHoursAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CenterController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Center::class);

        $query = Center::query()->with(['country', 'operatingHours']);

        $centers = QueryBuilder::for($query)
            ->allowedFilters(
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($query) use ($value) {
                        $query->where('name', 'like', "%{$value}%")
                            ->orWhere('code', 'like', "%{$value}%");
                    });
                }),
                AllowedFilter::exact('country_id'),
            )
            ->allowedSorts('code', 'name', 'created_at')
            ->defaultSort('-created_at')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        $centers->setCollection(
            CenterResource::collection($centers->getCollection())->collection,
        );

        return Inertia::render('Admin/Centers/Index', [
            'centers' => $centers,
            'filters' => (object) $request->only(['filter', 'sort']),
            'countries' => Country::query()->orderBy('code')->get(['id', 'name', 'code'])
                ->map(fn (Country $country) => ['id' => $country->id, 'name' => $country->name, 'code' => $country->code]),
        ]);
    }

    /**
     * Suggestion only — the form field stays editable.
     */
    public function nextCode(Request $request, CenterCodeGenerator $generator): JsonResponse
    {
        Gate::authorize('create', Center::class);

        $country = Country::query()->findOrFail($request->integer('country_id'));

        return response()->json(['code' => $generator->suggestNext($country)]);
    }

    public function store(StoreCenterRequest $request): RedirectResponse
    {
        Center::create($request->validated());

        return redirect()->route('admin.centers.index');
    }

    public function update(UpdateCenterRequest $request, Center $center): RedirectResponse
    {
        $center->update($request->validated());

        return redirect()->route('admin.centers.index');
    }

    public function syncOperatingHours(SyncCenterOperatingHoursRequest $request, Center $center, SyncCenterOperatingHoursAction $action): RedirectResponse
    {
        /** @var array<int, array{day_of_week: int, start_time: string, end_time: string}> $slots */
        $slots = $request->validated('slots');

        $action->handle($center, $slots);

        // Managers edit their hours from the availabilities page.
        return back();
    }

    public function destroy(Center $center): RedirectResponse
    {
        Gate::authorize('delete', $center);

        $center->delete();

        return redirect()->route('admin.centers.index');
    }
}
