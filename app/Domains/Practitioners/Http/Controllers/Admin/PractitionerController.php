<?php

namespace App\Domains\Practitioners\Http\Controllers\Admin;

use App\Domains\Auth\Models\User;
use App\Domains\Auth\Notifications\WelcomeSetPasswordNotification;
use App\Domains\Auth\Support\CenterScopedRoleAssigner;
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
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PractitionerController extends Controller
{
    public function __construct(private readonly CenterScopedRoleAssigner $roleAssigner) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Practitioner::class);

        $query = Practitioner::query()->with(['center.country', 'grade', 'user']);

        if (! $request->user()->isSuperAdmin()) {
            // visibleOnCenter(), not a plain center_id filter — a
            // manager/practitioner who has access to this center only
            // via a 'practitioner' role grant (not their Practitioner
            // row's own center_id) was previously invisible here even
            // though they could act on this center's patients, found
            // via real usage once a manager started managing more than
            // one center. See Practitioner::scopeVisibleOnCenter().
            $query->visibleOnCenter(getPermissionsTeamId());
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
            : getPermissionsTeamId();

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

            $this->roleAssigner->grant($resolution['user'], 'practitioner', $centerId);

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

                $this->roleAssigner->grant($user, 'practitioner', $centerId);

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
}
