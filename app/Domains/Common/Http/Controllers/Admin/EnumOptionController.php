<?php

namespace App\Domains\Common\Http\Controllers\Admin;

use App\Domains\Common\Http\Requests\StoreEnumOptionRequest;
use App\Domains\Common\Http\Requests\UpdateEnumOptionRequest;
use App\Domains\Common\Http\Resources\EnumOptionResource;
use App\Domains\Common\Models\EnumOption;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class EnumOptionController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', EnumOption::class);

        $options = QueryBuilder::for(EnumOption::query())
            ->allowedFilters(
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($query) use ($value) {
                        $query->where('code', 'like', "%{$value}%")
                            ->orWhere('label', 'like', "%{$value}%");
                    });
                }),
                AllowedFilter::exact('enum_type'),
            )
            ->allowedSorts('enum_type', 'code', 'order', 'created_at')
            ->defaultSort('enum_type', 'order')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        $options->setCollection(
            EnumOptionResource::collection($options->getCollection())->collection,
        );

        return Inertia::render('Admin/EnumOptions/Index', [
            'options' => $options,
            'filters' => (object) $request->only(['filter', 'sort']),
            // Existing enum_type values, for the filter dropdown — not an
            // exhaustive fixed list, new types appear as soon as a domain
            // creates its first option under one (see StoreEnumOptionRequest).
            'enumTypes' => EnumOption::query()
                ->select('enum_type')
                ->distinct()
                ->orderBy('enum_type')
                ->pluck('enum_type'),
        ]);
    }

    public function store(StoreEnumOptionRequest $request): RedirectResponse
    {
        EnumOption::create($request->validated());

        return redirect()->route('admin.enum-options.index');
    }

    public function update(UpdateEnumOptionRequest $request, EnumOption $enumOption): RedirectResponse
    {
        $enumOption->update($request->validated());

        return redirect()->route('admin.enum-options.index');
    }

    public function destroy(EnumOption $enumOption): RedirectResponse
    {
        Gate::authorize('delete', $enumOption);

        $enumOption->delete();

        return redirect()->route('admin.enum-options.index');
    }
}
