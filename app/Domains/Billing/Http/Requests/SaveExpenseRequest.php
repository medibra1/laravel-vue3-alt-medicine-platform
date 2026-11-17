<?php

namespace App\Domains\Billing\Http\Requests;

use App\Domains\Billing\Models\Expense;
use App\Domains\Billing\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create and update share the same rules. The center is only chosen by a
 * global admin, and only at creation: a manager's expense always goes to
 * their active center, and an expense never moves to another center.
 */
class SaveExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expense = $this->route('expense');

        return $expense instanceof Expense
            ? $this->user()->can('update', $expense)
            : $this->user()->can('create', Expense::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $choosesCenter = $this->user()->isGlobalAdmin() && ! $this->route('expense') instanceof Expense;

        return [
            'center_id' => $choosesCenter ? ['required', 'integer', 'exists:centers,id'] : ['nullable'],
            'category_option_id' => [
                'required', 'integer',
                Rule::exists('enum_options', 'id')->where('enum_type', Expense::CATEGORY_TYPE),
            ],
            'label' => ['required', 'string', 'max:255'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'spent_on' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::in(Payment::METHODS)],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
