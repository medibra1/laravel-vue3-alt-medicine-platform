<?php

namespace App\Domains\Auth\Http\Controllers\Admin;

use App\Domains\Auth\Http\Requests\StoreUserRequest;
use App\Domains\Auth\Http\Requests\UpdateUserRequest;
use App\Domains\Auth\Http\Resources\UserResource;
use App\Domains\Auth\Models\User;
use App\Domains\Auth\Notifications\ManagerAssignedNotification;
use App\Domains\Auth\Notifications\WelcomeSetPasswordNotification;
use App\Domains\Auth\Support\CenterScopedRoleAssigner;
use App\Domains\Core\Models\Center;
use App\Domains\Practitioners\Models\Practitioner;
use App\Domains\Practitioners\Services\PractitionerCodeGenerator;
use App\Http\Controllers\Controller;
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

class UserController extends Controller
{
    public function __construct(
        private readonly PractitionerCodeGenerator $codeGenerator,
        private readonly CenterScopedRoleAssigner $roleAssigner,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $query = User::query();

        $users = QueryBuilder::for($query)
            ->allowedFilters(
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($query) use ($value) {
                        $query->where('name', 'like', "%{$value}%")
                            ->orWhere('email', 'like', "%{$value}%");
                    });
                }),
            )
            ->allowedSorts('name', 'email', 'created_at')
            ->defaultSort('-created_at')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        $users->setCollection(
            UserResource::collection($users->getCollection())->collection,
        );

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => (object) $request->only(['filter', 'sort']),
            'centers' => Center::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $creationMode = $request->string('creation_mode')->value();
        $role = $request->string('role')->value();
        // A manager can now manage several centers at once (extended
        // 2026-08-26 from the original single-center design) — the
        // form submits center_ids[], center_id (kept for admin/global
        // roles, which have none) stays null for a manager.
        $centerIds = $role === 'manager' ? $request->input('center_ids', []) : [];

        $user = DB::transaction(function () use ($request, $creationMode, $role, $centerIds) {
            $user = User::create([
                'name' => $request->string('name')->value(),
                'email' => $request->string('email')->value(),
                // Invite mode: a throwaway random password the user never
                // sees — they set their real one through the emailed
                // password.reset link (WelcomeSetPasswordNotification),
                // which also verifies the email (see
                // MarkEmailVerifiedOnPasswordReset). Direct mode: the
                // admin-chosen password, email trusted immediately.
                'password' => $creationMode === 'invite' ? Str::password(32) : $request->string('password')->value(),
                'email_verified_at' => $creationMode === 'direct' ? now() : null,
                'is_active' => true,
            ]);

            if ($role === 'manager') {
                $this->syncManagerCenters($user, $centerIds);
            } else {
                $this->assignGlobalRole($user, $role);
            }

            return $user;
        });

        // The account is already committed above, so a notification
        // transport failure (SMTP unreachable, etc.) must not surface as
        // a creation failure — send best-effort and log on failure
        // rather than letting it bubble into a 500 for a request that,
        // from the data's perspective, already succeeded. Each
        // notification is caught independently so a mail transport
        // outage can never also swallow the (transport-independent)
        // database notification below it.
        if ($creationMode === 'invite') {
            try {
                $user->notify(new WelcomeSetPasswordNotification(Password::createToken($user)));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        foreach ($centerIds as $centerId) {
            try {
                $center = Center::query()->findOrFail($centerId);
                $user->notify(new ManagerAssignedNotification($center));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('admin.users.index');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $newCenterIds = $request->input('center_ids', []);
        $previousCenterIds = $user->managedCenterIds();

        DB::transaction(function () use ($request, $user, $newCenterIds) {
            $user->update([
                'name' => $request->string('name')->value(),
                'email' => $request->string('email')->value(),
            ]);

            $this->syncManagerCenters($user, $newCenterIds);
        });

        // Only notify for genuinely new centers on this edit — a center
        // already managed before this request stays silent, same
        // reasoning as store() only notifying once per center at
        // creation time.
        foreach (array_diff($newCenterIds, $previousCenterIds) as $centerId) {
            try {
                $center = Center::query()->findOrFail($centerId);
                $user->notify(new ManagerAssignedNotification($center));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('admin.users.index');
    }

    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        // Accounts are deactivated, never actually deleted — see
        // CLAUDE.md conventions already applied to other domains
        // (Treatment/Patient statuses).
        $user->update(['is_active' => false]);

        return redirect()->route('admin.users.index');
    }

    /**
     * A manager very often also treats patients themselves, but the
     * "Praticien" select on the Treatment wizard reads from the real
     * `practitioners` table (treatments.practitioner_id is a genuine FK,
     * not just a permission check) — a manager with no Practitioner row
     * of their own could never appear there. Gives every newly created
     * manager one automatically, using the exact same auto-generation
     * the standalone Practitioner form already relies on
     * (PractitionerCodeGenerator via PractitionerObserver::saving() —
     * full_code is computed there, not here).
     *
     * The Practitioner row itself has a single center_id (its
     * matricule/full_code are scoped to that one center) — created on
     * the first center this manager is ever assigned, skipped
     * afterwards (practitioners.user_id is unique at the DB level;
     * also reachable if this person already had a Practitioner row from
     * the ordinary Practitioner "join an existing account" flow). What
     * makes a manager actually *selectable* as "Praticien" on each of
     * their other managed centers is a separate 'practitioner' role
     * grant per center (see ResolvesPractitionerOptions, which reads
     * that role, not just Practitioner.center_id) — granted here for
     * every center passed in, independent of whether the row itself was
     * just created or already existed.
     *
     * @param  array<int, int>  $centerIds
     */
    private function ensurePractitionerAccess(User $user, array $centerIds): void
    {
        if (! Practitioner::query()->where('user_id', $user->id)->exists()) {
            $firstCenterId = $centerIds[0] ?? null;

            if ($firstCenterId !== null) {
                $center = Center::query()->findOrFail($firstCenterId);
                [$firstName, $lastName] = $this->splitName($user->name);

                $practitioner = new Practitioner([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'user_id' => $user->id,
                    'center_id' => $firstCenterId,
                    'matricule' => $this->codeGenerator->suggestNextMatricule($center),
                    'email' => $user->email,
                ]);
                $practitioner->save();
            }
        }

        foreach ($centerIds as $centerId) {
            $this->roleAssigner->grant($user, 'practitioner', $centerId);
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $name): array
    {
        $parts = explode(' ', trim($name), 2);

        return [$parts[0], $parts[1] ?? $parts[0]];
    }

    /**
     * A manager can manage several centers at once (extended
     * 2026-08-26 from the original single-center design) — additions
     * and removals in one call via CenterScopedRoleAssigner::
     * syncCenters(), the same accumulate-or-revoke shape practitioner
     * already had. Also keeps this manager's automatic practitioner
     * access in step (see ensurePractitionerAccess()) — every managed
     * center is also a center they can be picked as "Praticien" on.
     *
     * @param  array<int, int>  $centerIds
     */
    private function syncManagerCenters(User $user, array $centerIds): void
    {
        $this->roleAssigner->syncCenters($user, 'manager', $centerIds);

        if ($centerIds !== []) {
            $this->ensurePractitionerAccess($user, $centerIds);
        }
    }

    /**
     * Assigns a global role (admin/super_admin) — these hold exactly
     * one role, so unlike syncManagerCenters() this stays destructive
     * (detaches every existing assignment first). Switches the active
     * permissions team just long enough for the assignment — same
     * sentinel-team pattern already used by RolesAndPermissionsSeeder.
     * Restores the acting request's own active team afterwards so
     * nothing about the current request's authorization context leaks
     * past this call.
     */
    private function assignGlobalRole(User $user, string $role): void
    {
        $requestTeamId = getPermissionsTeamId();

        DB::table('model_has_roles')
            ->where('model_id', $user->getKey())
            ->where('model_type', User::class)
            ->delete();

        // model_has_roles.team_id is NOT NULL at the DB level — global
        // roles use the sentinel team 0 (never a real center id), same
        // convention as RolesAndPermissionsSeeder/actingAsSuperAdmin().
        setPermissionsTeamId(0);

        $roleModel = Role::query()->where('name', $role)->where('guard_name', 'web')
            ->whereNull('team_id')
            ->first();

        if (! $roleModel) {
            $roleModel = Role::query()->create([
                'name' => $role,
                'guard_name' => 'web',
                'team_id' => null,
            ]);
        }

        $user->assignRole($roleModel);

        setPermissionsTeamId($requestTeamId);
    }
}
