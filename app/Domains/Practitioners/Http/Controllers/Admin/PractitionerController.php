<?php

namespace App\Domains\Practitioners\Http\Controllers\Admin;

use App\Domains\Auth\Models\User;
use App\Domains\Auth\Notifications\WelcomeSetPasswordNotification;
use App\Domains\Auth\Support\RolePermissions;
use App\Domains\Core\Models\Center;
use App\Domains\Core\Models\Grade;
use App\Domains\Practitioners\Http\Requests\StorePractitionerRequest;
use App\Domains\Practitioners\Http\Requests\UpdatePractitionerRequest;
use App\Domains\Practitioners\Models\Practitioner;
use App\Domains\Practitioners\Notifications\PractitionerJoinedCenterNotification;
use App\Domains\Practitioners\Services\PractitionerAccountResolver;
use App\Domains\Practitioners\Services\PractitionerCodeGenerator;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PractitionerController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Practitioner::class);

        $query = Practitioner::query()->with(['center.country', 'grade', 'user']);

        if (! $request->user()->isSuperAdmin()) {
            $query->where('center_id', getPermissionsTeamId());
        }

        $practitioners = QueryBuilder::for($query)
            ->allowedFilters(
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($query) use ($value) {
                        $query->where('full_code', 'like', "%{$value}%")
                            ->orWhere('matricule', 'like', "%{$value}%")
                            ->orWhere('first_name', 'like', "%{$value}%")
                            ->orWhere('last_name', 'like', "%{$value}%");
                    });
                }),
                AllowedFilter::exact('grade_id'),
                AllowedFilter::exact('center_id'),
            )
            ->allowedSorts('full_code', 'matricule', 'hired_at', 'created_at')
            ->defaultSort('-created_at')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return Inertia::render('Admin/Practitioners/Index', [
            'practitioners' => $practitioners,
            'filters' => (object) $request->only(['filter', 'sort']),
            'centers' => $request->user()->isSuperAdmin() ? Center::query()->orderBy('code')->get(['id', 'name', 'code']) : [],
            'grades' => Grade::query()->orderBy('order')->get(['id', 'label', 'coefficient']),
        ]);
    }

    /**
     * Suggestion only — the form field stays editable, a manager may
     * want to enter a real diploma/registration number instead.
     */
    public function nextMatricule(Request $request, PractitionerCodeGenerator $generator): JsonResponse
    {
        Gate::authorize('create', Practitioner::class);

        $centerId = $request->user()->isSuperAdmin()
            ? $request->integer('center_id')
            : $request->user()->managedCenterId();

        $center = Center::query()->findOrFail($centerId);

        return response()->json(['matricule' => $generator->suggestNextMatricule($center)]);
    }

    /**
     * Backs the "grant access" toggle's live email check on the
     * creation form — same resolution StorePractitionerRequest
     * re-validates authoritatively on submit, this is only a preview.
     */
    public function checkAccount(Request $request, PractitionerAccountResolver $resolver): JsonResponse
    {
        Gate::authorize('create', Practitioner::class);

        $request->validate(['email' => ['required', 'email']]);

        $resolution = $resolver->resolve($request->string('email')->value());

        $practitioner = $resolution['practitioner'];

        return response()->json([
            'status' => $resolution['status'],
            'practitioner_name' => $practitioner ? "{$practitioner->first_name} {$practitioner->last_name}" : null,
            'current_centers' => $practitioner
                ? Practitioner::query()
                    ->where('user_id', $practitioner->user_id)
                    ->with('center:id,name')
                    ->get()
                    ->pluck('center.name')
                : [],
        ]);
    }

    public function store(StorePractitionerRequest $request, PractitionerAccountResolver $resolver): RedirectResponse
    {
        $centerId = $request->centerId();
        $grantAccess = $request->boolean('grant_access');
        $email = $request->string('email')->value();

        $resolution = $grantAccess && $email !== ''
            ? $resolver->resolve($email)
            : ['status' => 'new', 'user' => null, 'practitioner' => null];

        if ($grantAccess && $resolution['status'] === 'existing') {
            // Auto-join: the person already has a Practitioner row (on a
            // different center) — never create a second one for the
            // same person, just extend their access to this center too.
            $center = Center::query()->findOrFail($centerId);

            $this->grantPractitionerAccessToCenter($resolution['user'], $centerId);

            try {
                $resolution['user']->notify(new PractitionerJoinedCenterNotification($center));
            } catch (\Throwable $e) {
                report($e);
            }

            return redirect()->route('admin.practitioners.index');
        }

        DB::transaction(function () use ($request, $centerId, $grantAccess, $email) {
            $practitioner = Practitioner::create([
                ...$request->safe()->except(['grant_access', 'creation_mode', 'password', 'password_confirmation']),
                'center_id' => $centerId,
            ]);

            if ($grantAccess) {
                $creationMode = $request->string('creation_mode')->value();

                $user = User::create([
                    'name' => "{$practitioner->first_name} {$practitioner->last_name}",
                    'email' => $email,
                    'password' => $creationMode === 'invite' ? Str::password(32) : $request->string('password')->value(),
                    'email_verified_at' => $creationMode === 'direct' ? now() : null,
                    'is_active' => true,
                ]);

                $practitioner->update(['user_id' => $user->id]);

                $this->grantPractitionerAccessToCenter($user, $centerId);

                if ($creationMode === 'invite') {
                    try {
                        $user->notify(new WelcomeSetPasswordNotification(Password::createToken($user)));
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            }
        });

        return redirect()->route('admin.practitioners.index');
    }

    public function update(UpdatePractitionerRequest $request, Practitioner $practitioner): RedirectResponse
    {
        $practitioner->update($request->validated());

        return redirect()->route('admin.practitioners.index');
    }

    public function destroy(Practitioner $practitioner): RedirectResponse
    {
        Gate::authorize('delete', $practitioner);

        $practitioner->delete();

        return redirect()->route('admin.practitioners.index');
    }

    /**
     * Additive only — unlike UserController::assignRole() (which
     * detaches every existing role before assigning the new one, fine
     * for admin/manager who only ever hold one role), a practitioner
     * must be able to accumulate 'practitioner' assignments across
     * several centers without ever losing the earlier ones.
     */
    private function grantPractitionerAccessToCenter(User $user, int $centerId): void
    {
        $requestTeamId = getPermissionsTeamId();

        $alreadyGranted = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->getKey())
            ->where('model_has_roles.model_type', User::class)
            ->where('roles.name', 'practitioner')
            ->where('model_has_roles.team_id', $centerId)
            ->exists();

        if ($alreadyGranted) {
            return;
        }

        setPermissionsTeamId($centerId);

        $roleModel = Role::query()
            ->where('name', 'practitioner')
            ->where('guard_name', 'web')
            ->where('team_id', $centerId)
            ->first();

        if (! $roleModel) {
            $roleModel = Role::query()->create([
                'name' => 'practitioner',
                'guard_name' => 'web',
                'team_id' => $centerId,
            ]);
            $roleModel->syncPermissions(RolePermissions::practitioner());
        }

        $user->assignRole($roleModel);

        setPermissionsTeamId($requestTeamId);
    }
}
