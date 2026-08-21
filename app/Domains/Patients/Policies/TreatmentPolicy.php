<?php

namespace App\Domains\Patients\Policies;

use App\Domains\Auth\Models\User;
use App\Domains\Patients\Models\Treatment;

class TreatmentPolicy
{
    /**
     * super_admin bypasses every ability below — see User::isSuperAdmin().
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('treatments.viewAny');
    }

    public function view(User $user, Treatment $treatment): bool
    {
        return $user->can('treatments.view') && $this->managesCenter($treatment->center_id);
    }

    /**
     * Center scoping for create() is enforced by StoreTreatmentDraftRequest
     * (it forces center_id to the manager's own team) rather than here —
     * there's no target Treatment instance yet to check against.
     */
    public function create(User $user): bool
    {
        return $user->can('treatments.create');
    }

    public function update(User $user, Treatment $treatment): bool
    {
        return $user->can('treatments.update') && $this->managesCenter($treatment->center_id);
    }

    public function confirm(User $user, Treatment $treatment): bool
    {
        return $user->can('treatments.update') && $this->managesCenter($treatment->center_id);
    }

    public function close(User $user, Treatment $treatment): bool
    {
        return $user->can('treatments.update') && $this->managesCenter($treatment->center_id);
    }

    /**
     * Same gate as close() today — there's no separate lower-privileged
     * raqi login yet to actually exclude (see CLAUDE.md "Statut global
     * Treatment"), so every account that can reach this at all is
     * already a manager or super_admin. Kept as its own ability rather
     * than folded into close() so this stays the one place to tighten
     * once per-raqi accounts exist.
     */
    public function reopen(User $user, Treatment $treatment): bool
    {
        return $user->can('treatments.update') && $this->managesCenter($treatment->center_id);
    }

    public function delete(User $user, Treatment $treatment): bool
    {
        return $user->can('treatments.delete') && $this->managesCenter($treatment->center_id);
    }

    /**
     * A manager only acts on treatments of the center that
     * EnsureCenterAccess resolved as the request's active team. A draft
     * treatment may not have a center_id yet (relaxed validation, same
     * as Patient) — null never matches a real team_id, so a manager
     * simply can't reach it via update()/confirm() until it's set,
     * which StoreTreatmentDraftRequest guarantees happens on the very
     * first save for a manager (center_id is forced, never left null).
     */
    protected function managesCenter(?int $centerId): bool
    {
        return $centerId !== null && getPermissionsTeamId() === $centerId;
    }
}
