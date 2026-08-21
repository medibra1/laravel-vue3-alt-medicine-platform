<?php

namespace App\Domains\Patients\Http\Controllers\Admin;

use App\Domains\Patients\Http\Requests\StoreDiseaseRequest;
use App\Domains\Patients\Http\Requests\UpdateDiseaseRequest;
use App\Domains\Patients\Http\Resources\DiseaseAdminResource;
use App\Domains\Patients\Models\Disease;
use App\Domains\Patients\Models\DiseaseCategory;
use App\Domains\Patients\Services\DiseaseCodeGenerator;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class DiseaseController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Disease::class);

        $query = Disease::query()->with('category');

        $diseases = QueryBuilder::for($query)
            ->allowedFilters(
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($query) use ($value) {
                        $query->where('code', 'like', "%{$value}%")
                            ->orWhere('label', 'like', "%{$value}%");
                    });
                }),
                AllowedFilter::exact('disease_category_id'),
            )
            ->allowedSorts('code', 'created_at')
            ->defaultSort('code')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        $diseases->setCollection(
            DiseaseAdminResource::collection($diseases->getCollection())->collection,
        );

        return Inertia::render('Admin/Diseases/Index', [
            'diseases' => $diseases,
            'filters' => (object) $request->only(['filter', 'sort']),
            'categories' => DiseaseCategory::query()->orderBy('order')->get(['id', 'code', 'label'])
                ->map(fn (DiseaseCategory $category) => [
                    'id' => $category->id,
                    'code' => $category->code,
                    'label' => $category->label,
                ]),
        ]);
    }

    /**
     * Suggestion only — the form field stays editable.
     */
    public function nextCode(Request $request, DiseaseCodeGenerator $generator): JsonResponse
    {
        Gate::authorize('create', Disease::class);

        $category = DiseaseCategory::query()->findOrFail($request->integer('category_id'));

        return response()->json(['code' => $generator->suggestNext($category)]);
    }

    public function store(StoreDiseaseRequest $request): RedirectResponse
    {
        Disease::create($request->validated());

        return redirect()->route('admin.diseases.index');
    }

    public function update(UpdateDiseaseRequest $request, Disease $disease): RedirectResponse
    {
        $disease->update($request->validated());

        return redirect()->route('admin.diseases.index');
    }

    public function destroy(Disease $disease): RedirectResponse
    {
        Gate::authorize('delete', $disease);

        $disease->delete();

        return redirect()->route('admin.diseases.index');
    }
}
