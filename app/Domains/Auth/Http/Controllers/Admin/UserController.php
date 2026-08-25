<?php

namespace App\Domains\Auth\Http\Controllers\Admin;

use App\Domains\Auth\Http\Requests\StoreUserRequest;
use App\Domains\Auth\Http\Requests\UpdateUserRequest;
use App\Domains\Auth\Http\Resources\UserResource;
use App\Domains\Auth\Models\User;
use App\Domains\Auth\Notifications\ManagerAssignedNotification;
use App\Domains\Auth\Notifications\WelcomeSetPasswordNotification;
use App\Domains\Core\Models\Center;
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
        $centerId = $role === 'manager' ? $request->integer('center_id') : null;

        $user = DB::transaction(function () use ($request, $creationMode, $role, $centerId) {
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

            $this->assignRole($user, $role, $centerId);

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

        if ($centerId !== null) {
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
        $centerId = $request->integer('center_id');

        DB::transaction(function () use ($request, $user, $centerId) {
            $user->update([
                'name' => $request->string('name')->value(),
                'email' => $request->string('email')->value(),
            ]);

            $this->assignRole($user, 'manager', $centerId);
        });

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
     * Assigns a global (admin) or center-scoped (manager) role,
     * switching the active permissions team just long enough for the
     * assignment — same sentinel-team pattern already used by
     * RolesAndPermissionsSeeder. Restores the acting request's own
     * active team afterwards so nothing about the current request's
     * authorization context leaks past this call.
     */
    private function assignRole(User $user, string $role, ?int $centerId): void
    {
        $requestTeamId = getPermissionsTeamId();

        // roles()->detach() is team-scoped to whatever team is active
        // *right now* — it would silently miss the user's existing
        // assignment under a different team_id (e.g. reassigning a
        // manager to a new center). Deleted unscoped instead, same
        // reasoning as every other raw model_has_roles query on User.
        DB::table('model_has_roles')
            ->where('model_id', $user->getKey())
            ->where('model_type', User::class)
            ->delete();

        // model_has_roles.team_id is NOT NULL at the DB level — global
        // roles use the sentinel team 0 (never a real center id), same
        // convention as RolesAndPermissionsSeeder/actingAsSuperAdmin().
        setPermissionsTeamId($role === 'manager' ? $centerId : 0);

        $roleModel = Role::query()->where('name', $role)->where('guard_name', 'web')
            ->when($role !== 'manager', fn ($query) => $query->whereNull('team_id'))
            ->when($role === 'manager', fn ($query) => $query->where('team_id', $centerId))
            ->first();

        if (! $roleModel) {
            $roleModel = Role::query()->create([
                'name' => $role,
                'guard_name' => 'web',
                'team_id' => $role === 'manager' ? $centerId : null,
            ]);
        }

        $user->assignRole($roleModel);

        setPermissionsTeamId($requestTeamId);
    }
}
