<?php

namespace App\Domains\Billing\Http\Controllers\Admin;

use App\Domains\Billing\Http\Requests\SaveExpenseRequest;
use App\Domains\Billing\Models\Expense;
use App\Domains\Common\Models\EnumOption;
use App\Domains\Core\Models\Center;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Expense::class);

        $user = $request->user();
        $centerId = $user->isGlobalAdmin() ? null : getPermissionsTeamId();
        $search = trim($request->string('search')->value());

        $expenses = Expense::query()
            ->when($centerId, fn ($query) => $query->where('center_id', $centerId))
            ->with(['category', 'center'])
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('label', 'like', "%{$search}%")
                ->orWhere('supplier', 'like', "%{$search}%")))
            ->when($request->integer('category'), fn ($query, $category) => $query->where('category_option_id', $category))
            ->orderByDesc('spent_on')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        $expenses->through(fn (Expense $expense) => [
            'id' => $expense->id,
            'center_id' => $expense->center_id,
            'center' => $expense->center->name,
            'category_option_id' => $expense->category_option_id,
            'category' => $expense->category?->localizedLabel(),
            'label' => $expense->label,
            'supplier' => $expense->supplier,
            'amount' => (float) $expense->amount,
            'currency' => $expense->currency,
            'spent_on' => $expense->spent_on->toDateString(),
            'method' => $expense->method,
            'reference' => $expense->reference,
            'notes' => $expense->notes,
        ]);

        return Inertia::render('Admin/Billing/Expenses', [
            'expenses' => $expenses,
            'filters' => (object) $request->only(['search', 'category']),
            'categories' => EnumOption::query()->where('enum_type', Expense::CATEGORY_TYPE)->where('active', true)->orderBy('order')->get()
                ->map(fn (EnumOption $option) => ['id' => $option->id, 'label' => $option->localizedLabel()])->all(),
            'centers' => $user->isGlobalAdmin()
                ? Center::query()->orderBy('name')->get(['id', 'name'])->map(fn (Center $center) => ['id' => $center->id, 'name' => $center->name])->all()
                : [],
            // Per currency: centers of different countries never add up.
            'summary' => [
                'this_month' => DB::table('billing_expenses')
                    ->when($centerId, fn ($query) => $query->where('center_id', $centerId))
                    ->where('spent_on', '>=', now()->startOfMonth()->toDateString())
                    ->groupBy('currency')
                    ->selectRaw('currency, SUM(amount) as amount')
                    ->get()
                    ->map(fn ($row) => ['currency' => (string) $row->currency, 'amount' => round((float) $row->amount, 2)])
                    ->all(),
            ],
            'can' => [
                'create' => Gate::allows('create', Expense::class),
                'update' => $user->isGlobalAdmin() || $user->can('expenses.update'),
                'delete' => $user->isGlobalAdmin() || $user->can('expenses.delete'),
            ],
        ]);
    }

    public function store(SaveExpenseRequest $request): RedirectResponse
    {
        $user = $request->user();
        $center = Center::query()->findOrFail($user->isGlobalAdmin() ? $request->validated('center_id') : getPermissionsTeamId());

        Expense::create([
            ...$request->safe()->except('center_id'),
            'center_id' => $center->id,
            'currency' => $center->currency,
            'created_by' => $user->id,
        ]);

        return back();
    }

    public function update(SaveExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $expense->update($request->safe()->except('center_id'));

        return back();
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        Gate::authorize('delete', $expense);

        $expense->delete();

        return back();
    }
}
