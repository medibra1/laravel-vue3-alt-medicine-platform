<?php

namespace App\Domains\Auth\Http\Resources;

use App\Domains\Auth\Models\User;
use App\Domains\Core\Models\Center;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $role = $this->resolveRole();
        $centerId = $role === 'manager' ? $this->managedCenterId() : null;
        $centerIds = $role === 'practitioner' ? $this->accessibleCenterIds() : [];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $role,
            'center_id' => $centerId,
            'center' => $centerId !== null
                ? Center::query()->find($centerId, ['id', 'name'])
                : null,
            // Only ever populated for a practitioner — a manager stays
            // on the single 'center' above (unchanged shape, so nothing
            // consuming that field before this needs to change).
            'centers' => $centerIds !== []
                ? Center::query()->whereIn('id', $centerIds)->orderBy('name')->get(['id', 'name'])
                : [],
            'is_active' => $this->is_active,
            // Direct-mode creation verifies the email immediately (the
            // admin vouches for the address); invite-mode leaves it null
            // until the user goes through Password::reset() — see
            // UserController::store()/AppServiceProvider's PasswordReset
            // listener. Reused as the "pending" signal rather than a
            // separate invited_at/invitation_accepted_at column.
            'invitation_pending' => $this->email_verified_at === null,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * roles() is team-scoped (filtered to the currently active
     * permissions team, not this row's own) — resolved via the same
     * raw, unscoped lookup already used by isSuperAdmin()/isAdmin()/
     * isManager() rather than $this->roles. A practitioner can hold
     * several 'practitioner' rows (one per center) but never mixes
     * roles across centers in this app, so picking any one row's name
     * here is enough to identify which role this account has.
     */
    private function resolveRole(): ?string
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $this->id)
            ->where('model_has_roles.model_type', User::class)
            ->value('roles.name');
    }
}
