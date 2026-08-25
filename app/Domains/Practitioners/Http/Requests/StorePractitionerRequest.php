<?php

namespace App\Domains\Practitioners\Http\Requests;

use App\Domains\Practitioners\Models\Practitioner;
use App\Domains\Practitioners\Services\PractitionerAccountResolver;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StorePractitionerRequest extends FormRequest
{
    private ?string $resolvedAccountStatus = null;

    public function authorize(): bool
    {
        return $this->user()->can('create', Practitioner::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $grantAccess = $this->boolean('grant_access');
        $isJoiningExisting = $grantAccess && $this->accountStatus() === 'existing';

        // isJoiningExisting fields below are 'nullable' rather than
        // 'prohibited' — Inertia's useForm() always submits every field
        // it knows about, even ones hidden behind v-if on the frontend,
        // so leftover values from whatever was last typed (a matricule,
        // a creation_mode) always arrive in the payload regardless of
        // what the UI shows. 'prohibited' rejected those leftovers with
        // a redirect-back that looked like a false success (302 to the
        // same index URL a real create also redirects to) rather than a
        // visible error — found twice in a row on two different fields
        // during browser verification. store() never reads any of these
        // fields in the isJoiningExisting branch (it returns immediately
        // before touching them), so accepting-but-ignoring is correct
        // here, not just convenient.
        return [
            // Not applicable when joining an already-registered
            // practitioner — their name is already on file, nothing new
            // is created (see PractitionerController::store()).
            'first_name' => $isJoiningExisting ? ['nullable', 'string'] : ['required', 'string', 'max:255'],
            'last_name' => $isJoiningExisting ? ['nullable', 'string'] : ['required', 'string', 'max:255'],
            // A manager may not choose the center at all — it's forced to
            // the one EnsureCenterAccess resolved for them, see centerId().
            'center_id' => [$this->user()->isSuperAdmin() ? 'required' : 'prohibited', 'integer', 'exists:centers,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            // full_code = country + center + matricule, so within one
            // center the matricule is what actually drives uniqueness.
            // Auto-suggested by PractitionerCodeGenerator::suggestNextMatricule()
            // on the frontend, but this field stays editable/overridable.
            // Not applicable at all when joining an already-registered
            // practitioner: no new Practitioner row is created, so
            // there's no matricule to assign — see
            // PractitionerController::store().
            'matricule' => $isJoiningExisting
                ? ['nullable', 'string']
                : ['required', 'digits:3', Rule::unique('practitioners')->where('center_id', $this->centerId())],
            'level' => ['nullable', 'integer', 'min:0'],
            'hired_at' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            // Doubles as the account's login email once grant_access is
            // true — a single field, not a separate "account email",
            // by explicit decision (see CLAUDE.md).
            'email' => [$grantAccess ? 'required' : 'nullable', 'email', 'max:255'],
            'grant_access' => ['sometimes', 'boolean'],
            // creation_mode/password only matter when grant_access is
            // true AND the email resolves to a genuinely new account —
            // an 'existing' match (auto-join) never touches password at
            // all. Kept as their own conditional branches rather than
            // stacked with 'prohibited' in the same array — see
            // CLAUDE.md's Phase 1 'prohibited' gotcha.
            'creation_mode' => match (true) {
                $isJoiningExisting => ['nullable', Rule::in(['direct', 'invite'])],
                $grantAccess => ['required', Rule::in(['direct', 'invite'])],
                default => ['prohibited'],
            },
            'password' => $grantAccess && ! $isJoiningExisting && $this->input('creation_mode') === 'direct'
                ? ['required', 'confirmed', Password::defaults()]
                : ['nullable'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->user()->isSuperAdmin() && $this->centerId() === null) {
                $validator->errors()->add('center_id', __('Vous ne gérez aucun centre.'));
            }

            if ($this->boolean('grant_access') && $this->filled('email') && $this->accountStatus() === 'taken') {
                $validator->errors()->add('email', __('Cet email est déjà utilisé par un autre compte.'));
            }
        });
    }

    public function centerId(): ?int
    {
        return $this->user()->isSuperAdmin()
            ? $this->integer('center_id')
            : $this->user()->managedCenterId();
    }

    /**
     * Resolved once per request (rules() and withValidator() both need
     * it) rather than re-querying — the email doesn't change mid-request.
     */
    private function accountStatus(): ?string
    {
        if (! $this->filled('email')) {
            return null;
        }

        return $this->resolvedAccountStatus ??= app(PractitionerAccountResolver::class)
            ->resolve($this->string('email')->value())['status'];
    }
}
