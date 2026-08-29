<?php

namespace App\Domains\Auth\Http\Requests;

use App\Domains\Auth\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User $target */
        $target = $this->route('user');

        return $this->user()->can('update', $target);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target->getKey())],
            // UserPolicy::update() already confines an admin's target to
            // an existing manager — role itself isn't re-assignable here
            // (no admin-created-by-admin escalation path, no manager ->
            // admin promotion either, both out of Phase 1 scope).
            'role' => ['required', Rule::in(['manager'])],
            // A manager can now manage several centers at once
            // (extended 2026-08-26) — center_ids[], not center_id.
            'center_ids' => ['required', 'array', 'min:1'],
            'center_ids.*' => ['integer', 'exists:centers,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $centerIds = $this->input('center_ids', []);

            if ($centerIds === []) {
                return;
            }

            /** @var User $target */
            $target = $this->route('user');

            // Same per-center check as StoreUserRequest — a center can
            // have at most one manager, checked against every other
            // user's assignments (this target's own rows are excluded,
            // otherwise re-submitting their existing centers would
            // always "conflict" with themselves).
            $alreadyManagedCenterIds = DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('roles.name', 'manager')
                ->whereIn('model_has_roles.team_id', $centerIds)
                ->where('model_has_roles.model_id', '!=', $target->getKey())
                ->pluck('model_has_roles.team_id')
                ->all();

            if ($alreadyManagedCenterIds !== []) {
                $validator->errors()->add('center_ids', __('Ce centre a déjà un manager.'));
            }
        });
    }
}
