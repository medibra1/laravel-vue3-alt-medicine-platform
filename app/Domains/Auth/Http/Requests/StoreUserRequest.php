<?php

namespace App\Domains\Auth\Http\Requests;

use App\Domains\Auth\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            // An admin may only ever hand out the 'manager' role —
            // 'admin' is reserved to super_admin.
            'role' => [
                'required',
                Rule::in($this->user()->isSuperAdmin() ? ['manager', 'admin'] : ['manager']),
            ],
            // A manager can now manage several centers at once
            // (extended 2026-08-26 from the original single-center
            // design) — center_ids[], not center_id. Separate branches
            // rather than 'prohibited' stacked with 'array'/'min' in one
            // array — the real Vue form always submits center_ids for
            // every role (an empty array for admin, not an absent key),
            // and 'prohibited' rejects a present-but-empty array just as
            // much as a non-empty one, so an admin submission would
            // fail validation on its own default value if this weren't
            // split (same class of bug already fixed on
            // StorePractitionerRequest/StoreTreatmentDraftRequest's
            // center_id).
            'center_ids' => $this->input('role') === 'manager'
                ? ['required', 'array', 'min:1']
                : ['array', 'max:0'],
            'center_ids.*' => ['integer', 'exists:centers,id'],
            'creation_mode' => ['required', Rule::in(['direct', 'invite'])],
            // In invite mode the frontend still submits empty
            // password/password_confirmation strings (unfilled form
            // fields) — 'prohibited' alone doesn't stop Password::defaults()
            // from also running against that empty value, so 'direct'
            // mode gets its own dedicated branch instead of stacking
            // both concerns in one array.
            'password' => $this->input('creation_mode') === 'direct'
                ? ['required', 'confirmed', Password::defaults()]
                : ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('role') !== 'manager') {
                return;
            }

            $centerIds = $this->input('center_ids', []);

            // Role assignments live in model_has_roles (team_id = center),
            // not on a users column — a plain Rule::unique can't express
            // "this center already has a manager", so it's checked here
            // against the raw pivot instead. Joined against users (not
            // just model_has_roles/roles) so a row left behind by a user
            // deleted outside the normal destroy() flow (e.g. directly in
            // the DB — destroy() itself only ever deactivates, never
            // deletes) can't silently block every future manager
            // assignment on that center with a "already has a manager"
            // error that no longer reflects reality. Checked per center
            // in the submitted array — a center can have at most one
            // manager, but this manager can be assigned to several
            // centers at once (see CLAUDE.md/RolePermissions).
            $alreadyManagedCenterIds = DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->join('users', 'users.id', '=', 'model_has_roles.model_id')
                ->where('roles.name', 'manager')
                ->whereIn('model_has_roles.team_id', $centerIds)
                ->pluck('model_has_roles.team_id')
                ->all();

            if ($alreadyManagedCenterIds !== []) {
                $validator->errors()->add('center_ids', __('Ce centre a déjà un manager.'));
            }
        });
    }
}
