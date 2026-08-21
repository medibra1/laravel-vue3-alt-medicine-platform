<?php

namespace App\Domains\Patients\Http\Controllers\Admin;

use App\Domains\Common\Models\EnumOption;
use App\Domains\Patients\Http\Requests\StoreDiseaseCategoryRequest;
use App\Domains\Patients\Http\Requests\UpdateDiseaseCategoryRequest;
use App\Domains\Patients\Http\Resources\DiseaseCategoryAdminResource;
use App\Domains\Patients\Models\DiseaseCategory;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class DiseaseCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', DiseaseCategory::class);

        $query = DiseaseCategory::query()->with('type');

        $categories = QueryBuilder::for($query)
            ->allowedFilters(
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($query) use ($value) {
                        $query->where('code', 'like', "%{$value}%")
                            ->orWhere('label', 'like', "%{$value}%");
                    });
                }),
                AllowedFilter::exact('type_option_id'),
            )
            ->allowedSorts('code', 'order', 'created_at')
            ->defaultSort('order')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        $categories->setCollection(
            DiseaseCategoryAdminResource::collection($categories->getCollection())->collection,
        );

        return Inertia::render('Admin/DiseaseCategories/Index', [
            'categories' => $categories,
            'filters' => $request->only(['filter', 'sort']),
            'types' => EnumOption::query()
                ->where('enum_type', 'disease_category.type')
                ->orderBy('order')
                ->get(['id', 'code', 'label'])
                ->map(fn (EnumOption $option) => [
                    'id' => $option->id,
                    'code' => $option->code,
                    'label' => $option->label['fr'] ?? $option->code,
                ]),
        ]);
    }

    public function store(StoreDiseaseCategoryRequest $request): RedirectResponse
    {
        DiseaseCategory::create($request->validated());

        return redirect()->route('admin.disease-categories.index');
    }

    public function update(UpdateDiseaseCategoryRequest $request, DiseaseCategory $diseaseCategory): RedirectResponse
    {
        $diseaseCategory->update($request->validated());

        return redirect()->route('admin.disease-categories.index');
    }

    public function destroy(DiseaseCategory $diseaseCategory): RedirectResponse
    {
        Gate::authorize('delete', $diseaseCategory);

        $diseaseCategory->delete();

        return redirect()->route('admin.disease-categories.index');
    }
}
