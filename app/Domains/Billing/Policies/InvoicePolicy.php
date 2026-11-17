<?php

namespace App\Domains\Billing\Policies;

use App\Domains\Auth\Models\User;
use App\Domains\Billing\Models\Invoice;

class InvoicePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isGlobalAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('invoices.viewAny');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.view') && $invoice->center_id === getPermissionsTeamId();
    }

    public function create(User $user): bool
    {
        return $user->can('invoices.create');
    }

    /** Cancelling an invoice. */
    public function cancel(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.cancel') && $invoice->center_id === getPermissionsTeamId();
    }

    public function recordPayment(User $user, Invoice $invoice): bool
    {
        return $user->can('payments.create') && $invoice->center_id === getPermissionsTeamId();
    }

    public function deletePayment(User $user, Invoice $invoice): bool
    {
        return $user->can('payments.delete') && $invoice->center_id === getPermissionsTeamId();
    }

    public function credit(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.credit') && $invoice->center_id === getPermissionsTeamId();
    }

    public function refund(User $user, Invoice $invoice): bool
    {
        return $user->can('payments.refund') && $invoice->center_id === getPermissionsTeamId();
    }

    public function schedule(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.schedule') && $invoice->center_id === getPermissionsTeamId();
    }
}
