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
            // 'admin' is reserved to super_admin, same reasoning as
            // center_id below (prohibited rather than silently ignored,
            // so a non-super_admin submitting it gets a real validation
            // error instead of a silently downgraded role).
            'role' => [
                'required',
                Rule::in($this->user()->isSuperAdmin() ? ['manager', 'admin'] : ['manager']),
            ],
            'center_id' => [
                $this->input('role') === 'manager' ? 'required' : 'prohibited',
                'integer',
                'exists:centers,id',
            ],
            'creation_mode' => ['required', Rule::in(['direct', 'invite'])],
            // In invite mode the frontend still submits empty
            // password/password_confirmation strings (unfilled form
            // fields) — 'prohibited' alone doesn't stop Password::defaults()
            // from also running against that empty value, so 'direct'
            // mode gets its own dedicated branch instead of stacking
            // both concerns in one rule array.
            'password' => $this->input('creation_mode') === 'direct'
                ? ['required', 'confirmed', Password::defaults()]
                : ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('role') !== 'manager' || ! $this->filled('center_id')) {
                return;
            }

            // Role assignments live in model_has_roles (team_id = center),
            // not on a users column — a plain Rule::unique can't express
            // "this center already has a manager", so it's checked here
            // against the raw pivot instead.
            $alreadyManaged = DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('roles.name', 'manager')
                ->where('model_has_roles.team_id', $this->integer('center_id'))
                ->exists();

            if ($alreadyManaged) {
                $validator->errors()->add('center_id', __('Ce centre a déjà un manager.'));
            }
        });
    }
}
