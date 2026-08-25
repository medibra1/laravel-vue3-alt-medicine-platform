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
            'center_id' => ['required', 'integer', 'exists:centers,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('center_id')) {
                return;
            }

            /** @var User $target */
            $target = $this->route('user');

            $alreadyManaged = DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('roles.name', 'manager')
                ->where('model_has_roles.team_id', $this->integer('center_id'))
                ->where('model_has_roles.model_id', '!=', $target->getKey())
                ->exists();

            if ($alreadyManaged) {
                $validator->errors()->add('center_id', __('Ce centre a déjà un manager.'));
            }
        });
    }
}
