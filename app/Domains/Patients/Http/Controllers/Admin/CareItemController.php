<?php

namespace App\Domains\Patients\Http\Controllers\Admin;

use App\Domains\Patients\Http\Requests\StoreCareItemRequest;
use App\Domains\Patients\Http\Requests\UpdateCareItemRequest;
use App\Domains\Patients\Http\Resources\CareCategoryAdminResource;
use App\Domains\Patients\Http\Resources\CareItemAdminResource;
use App\Domains\Patients\Models\CareCategory;
use App\Domains\Patients\Models\CareItem;
use App\Domains\Patients\Services\CareItemCodeGenerator;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CareItemController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', CareItem::class);

        $query = CareItem::query()->with('category');

        $careItems = QueryBuilder::for($query)
            ->allowedFilters(
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($query) use ($value) {
                        $query->where('code', 'like', "%{$value}%")
                            ->orWhere('label', 'like', "%{$value}%");
                    });
                }),
                AllowedFilter::exact('care_category_id'),
            )
            ->allowedSorts('code', 'order', 'created_at')
            ->defaultSort('order')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        $careItems->setCollection(
            CareItemAdminResource::collection($careItems->getCollection())->collection,
        );

        return Inertia::render('Admin/CareItems/Index', [
            'careItems' => $careItems,
            'filters' => (object) $request->only(['filter', 'sort']),
            'categories' => CareCategoryAdminResource::collection(
                CareCategory::query()->orderBy('order')->get(),
            ),
        ]);
    }

    /**
     * Suggestion only — the form field stays editable.
     */
    public function nextCode(Request $request, CareItemCodeGenerator $generator): JsonResponse
    {
        Gate::authorize('create', CareItem::class);

        $category = CareCategory::query()->findOrFail($request->integer('category_id'));

        return response()->json(['code' => $generator->suggestNext($category)]);
    }

    public function store(StoreCareItemRequest $request): RedirectResponse
    {
        CareItem::create($request->validated());

        return redirect()->route('admin.care-items.index');
    }

    public function update(UpdateCareItemRequest $request, CareItem $careItem): RedirectResponse
    {
        $careItem->update($request->validated());

        return redirect()->route('admin.care-items.index');
    }

    public function destroy(CareItem $careItem): RedirectResponse
    {
        Gate::authorize('delete', $careItem);

        $careItem->delete();

        return redirect()->route('admin.care-items.index');
    }
}
