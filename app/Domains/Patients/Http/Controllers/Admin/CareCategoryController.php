<?php

namespace App\Domains\Patients\Http\Controllers\Admin;

use App\Domains\Patients\Http\Requests\StoreCareCategoryRequest;
use App\Domains\Patients\Http\Requests\UpdateCareCategoryRequest;
use App\Domains\Patients\Http\Resources\CareCategoryAdminResource;
use App\Domains\Patients\Models\CareCategory;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CareCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', CareCategory::class);

        $query = CareCategory::query();

        $careCategories = QueryBuilder::for($query)
            ->allowedFilters(
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($query) use ($value) {
                        $query->where('code', 'like', "%{$value}%")
                            ->orWhere('label', 'like', "%{$value}%");
                    });
                }),
            )
            ->allowedSorts('code', 'order', 'created_at')
            ->defaultSort('order')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        $careCategories->setCollection(
            CareCategoryAdminResource::collection($careCategories->getCollection())->collection,
        );

        return Inertia::render('Admin/CareCategories/Index', [
            'careCategories' => $careCategories,
            'filters' => (object) $request->only(['filter', 'sort']),
        ]);
    }

    public function store(StoreCareCategoryRequest $request): RedirectResponse
    {
        CareCategory::create($request->validated());

        return redirect()->route('admin.care-categories.index');
    }

    public function update(UpdateCareCategoryRequest $request, CareCategory $careCategory): RedirectResponse
    {
        $careCategory->update($request->validated());

        return redirect()->route('admin.care-categories.index');
    }

    public function destroy(CareCategory $careCategory): RedirectResponse
    {
        Gate::authorize('delete', $careCategory);

        $careCategory->delete();

        return redirect()->route('admin.care-categories.index');
    }
}
