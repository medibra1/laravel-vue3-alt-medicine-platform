<?php

namespace App\Domains\Practitioners\Http\Controllers\Admin;

use App\Domains\Core\Models\Center;
use App\Domains\Core\Models\Grade;
use App\Domains\Practitioners\Http\Requests\StorePractitionerRequest;
use App\Domains\Practitioners\Http\Requests\UpdatePractitionerRequest;
use App\Domains\Practitioners\Models\Practitioner;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PractitionerController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Practitioner::class);

        $query = Practitioner::query()->with(['center.country', 'grade', 'user']);

        if (! $request->user()->isSuperAdmin()) {
            $query->where('center_id', getPermissionsTeamId());
        }

        $practitioners = QueryBuilder::for($query)
            ->allowedFilters(
                AllowedFilter::partial('full_code'),
                AllowedFilter::partial('diploma_number'),
                AllowedFilter::exact('grade_id'),
                AllowedFilter::exact('center_id'),
            )
            ->allowedSorts('full_code', 'diploma_number', 'hired_at', 'created_at')
            ->defaultSort('-created_at')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return Inertia::render('Admin/Practitioners/Index', [
            'practitioners' => $practitioners,
            'filters' => $request->only(['filter', 'sort']),
            'centers' => $request->user()->isSuperAdmin() ? Center::query()->orderBy('code')->get(['id', 'name', 'code']) : [],
            'grades' => Grade::query()->orderBy('order')->get(['id', 'label', 'coefficient']),
        ]);
    }

    public function store(StorePractitionerRequest $request): RedirectResponse
    {
        Practitioner::create([
            ...$request->validated(),
            'center_id' => $request->centerId(),
        ]);

        return redirect()->route('admin.practitioners.index');
    }

    public function update(UpdatePractitionerRequest $request, Practitioner $practitioner): RedirectResponse
    {
        $practitioner->update($request->validated());

        return redirect()->route('admin.practitioners.index');
    }

    public function destroy(Practitioner $practitioner): RedirectResponse
    {
        Gate::authorize('delete', $practitioner);

        $practitioner->delete();

        return redirect()->route('admin.practitioners.index');
    }
}
