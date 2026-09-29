<?php

namespace App\Domains\Scheduling\Http\Controllers\Admin;

use App\Domains\Core\Http\Concerns\ResolvesCenterOptions;
use App\Domains\Patients\Http\Resources\PatientOptionResource;
use App\Domains\Patients\Models\Patient;
use App\Domains\Practitioners\Http\Concerns\ResolvesPractitionerOptions;
use App\Domains\Practitioners\Models\Practitioner;
use App\Domains\Scheduling\Http\Requests\CancelAppointmentRequest;
use App\Domains\Scheduling\Http\Requests\StoreAppointmentRequest;
use App\Domains\Scheduling\Http\Requests\UpdateAppointmentRequest;
use App\Domains\Scheduling\Http\Resources\AppointmentResource;
use App\Domains\Scheduling\Models\Appointment;
use App\Domains\Scheduling\Notifications\AppointmentAssignedNotification;
use App\Domains\Scheduling\Services\AvailableSlotsResolver;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    use ResolvesCenterOptions;
    use ResolvesPractitionerOptions;

    /**
     * The calendar shell itself — Inertia render once, then Agenda.vue
     * fetches events live from index() as the user navigates
     * days/weeks, same split already used elsewhere in this app between
     * a page's initial render and its live data (e.g. AppNotificationBell).
     */
    public function agenda(Request $request): Response
    {
        Gate::authorize('viewAny', Appointment::class);

        $centerId = $request->user()->isSuperAdmin() ? null : getPermissionsTeamId();

        return Inertia::render('Admin/Scheduling/Agenda', [
            'centers' => $this->centerOptions($request),
            'practitioners' => $this->practitionerOptions($request),
            'patients' => PatientOptionResource::collection(
                Patient::query()
                    ->when($centerId, fn ($query) => $query->where('intake_center_id', $centerId))
                    ->orderBy('last_name')
                    ->get(),
            ),
        ]);
    }

    /**
     * JSON, not Inertia — the calendar navigates between weeks/days on
     * its own (see Agenda.vue), and a full page reload for every step
     * would defeat the point of a live grid.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Appointment::class);

        $query = Appointment::query()->with(['patient', 'practitioner']);

        if (! $request->user()->isSuperAdmin()) {
            $query->where('center_id', getPermissionsTeamId());
        } elseif ($request->filled('center_id')) {
            $query->where('center_id', $request->integer('center_id'));
        }

        if ($request->filled('practitioner_id')) {
            $query->where('practitioner_id', $request->integer('practitioner_id'));
        }

        if ($request->filled('from')) {
            $query->where('starts_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            // `to` is an exclusive bound (the day after the last displayed
            // day) — a bare date parses as midnight, so `<=` would drop
            // everything after 00:00 on the last day.
            $query->where('starts_at', '<', $request->date('to'));
        }

        return AppointmentResource::collection($query->orderBy('starts_at')->get());
    }

    public function store(StoreAppointmentRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['center_id'] = $request->centerId();

        $this->assertPractitionerVisibleOnCenter($request, $validated['practitioner_id'], $validated['center_id']);

        $appointment = Appointment::create([
            ...$validated,
            'status' => 'scheduled',
            'created_by' => $request->user()->id,
        ]);

        $this->notifyPractitioner($appointment);

        return redirect()->route('admin.patients.edit', $appointment->patient_id);
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment): RedirectResponse
    {
        $validated = $request->validated();

        $this->assertPractitionerVisibleOnCenter($request, $validated['practitioner_id'], $appointment->center_id);

        $appointment->update($validated);

        $this->notifyPractitioner($appointment);

        return redirect()->route('admin.patients.edit', $appointment->patient_id);
    }

    public function cancel(CancelAppointmentRequest $request, Appointment $appointment): RedirectResponse
    {
        $appointment->update([
            'status' => 'cancelled',
            'cancellation_reason' => $request->string('cancellation_reason')->value(),
        ]);

        return redirect()->route('admin.patients.edit', $appointment->patient_id);
    }

    public function markNoShow(Appointment $appointment): RedirectResponse
    {
        Gate::authorize('update', $appointment);

        $appointment->update(['status' => 'no_show']);

        return redirect()->route('admin.patients.edit', $appointment->patient_id);
    }

    /**
     * Feeds the slot picker in AppointmentDialog.vue — no persistence,
     * pure read delegated to AvailableSlotsResolver.
     */
    public function availableSlots(Request $request, AvailableSlotsResolver $resolver): JsonResponse
    {
        Gate::authorize('viewAny', Appointment::class);

        $request->validate([
            'practitioner_id' => ['required', 'integer', 'exists:practitioners,id'],
            'date' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
        ]);

        $practitioner = Practitioner::query()->findOrFail($request->integer('practitioner_id'));

        $this->assertPractitionerVisibleOnCenter($request, $practitioner->id, getPermissionsTeamId());

        $slots = $resolver->resolve(
            $practitioner->id,
            CarbonImmutable::parse($request->date('date')),
            $request->integer('duration_minutes'),
        );

        return response()->json([
            'slots' => collect($slots)->map(fn (array $slot) => [
                'starts_at' => $slot['starts_at']->toIso8601String(),
                'ends_at' => $slot['ends_at']->toIso8601String(),
            ]),
        ]);
    }

    /**
     * A practitioner_id passing 'exists:practitioners,id' only proves the
     * row exists somewhere — not that it's actually assignable on this
     * particular center. super_admin isn't scoped to any one active
     * center (see EnsureCenterAccess), so it's exempt; everyone else must
     * pick from Practitioner::visibleOnCenter() for the center in play.
     */
    private function assertPractitionerVisibleOnCenter(Request $request, int $practitionerId, ?int $centerId): void
    {
        if ($request->user()->isSuperAdmin()) {
            return;
        }

        abort_unless(
            Practitioner::query()->whereKey($practitionerId)->visibleOnCenter((int) $centerId)->exists(),
            403,
        );
    }

    /**
     * Best-effort, same reasoning as ManagerAssignedNotification's
     * dispatch site (UserController::store()) — a mail/notification
     * failure must never surface as a booking failure, the appointment
     * is already committed by the time this runs.
     */
    private function notifyPractitioner(Appointment $appointment): void
    {
        try {
            $appointment->practitioner->user?->notify(new AppointmentAssignedNotification($appointment));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
