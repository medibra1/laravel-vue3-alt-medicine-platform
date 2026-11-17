<?php

namespace App\Domains\Billing\Policies;

use App\Domains\Auth\Models\User;
use App\Domains\Billing\Models\Expense;

class ExpensePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isGlobalAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('expenses.viewAny');
    }

    public function create(User $user): bool
    {
        return $user->can('expenses.create');
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->can('expenses.update') && $expense->center_id === getPermissionsTeamId();
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->can('expenses.delete') && $expense->center_id === getPermissionsTeamId();
    }
}
