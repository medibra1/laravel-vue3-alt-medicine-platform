<?php

namespace App\Domains\Scheduling\Http\Controllers\Admin;

use App\Domains\Core\Http\Concerns\ResolvesCenterOptions;
use App\Domains\Core\Http\Resources\CenterClosureResource;
use App\Domains\Core\Http\Resources\CenterOperatingHoursResource;
use App\Domains\Core\Models\Center;
use App\Domains\Core\Models\CenterClosure;
use App\Domains\Core\Models\CenterOperatingHours;
use App\Domains\Patients\Http\Resources\PatientOptionResource;
use App\Domains\Patients\Models\Patient;
use App\Domains\Practitioners\Http\Concerns\ResolvesPractitionerOptions;
use App\Domains\Practitioners\Models\Practitioner;
use App\Domains\Scheduling\Http\Requests\CancelAppointmentRequest;
use App\Domains\Scheduling\Http\Requests\StoreAppointmentRequest;
use App\Domains\Scheduling\Http\Requests\UpdateAppointmentRequest;
use App\Domains\Scheduling\Http\Resources\AppointmentResource;
use App\Domains\Scheduling\Http\Resources\PractitionerTimeOffResource;
use App\Domains\Scheduling\Models\Appointment;
use App\Domains\Scheduling\Models\PractitionerTimeOff;
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

        // Opening hours of every center the user can display, shipped once
        // so switching weeks/centers never needs another request. Keyed by
        // center_id; they only set the grid's display range (see Agenda.vue).
        $visibleCenterIds = $centerId !== null
            ? [$centerId]
            : Center::query()->pluck('id')->all();

        $practitioners = $this->practitionerOptions($request);

        return Inertia::render('Admin/Scheduling/Agenda', [
            'activeCenterId' => $centerId,
            // Time offs of the listed practitioners that end within the last
            // 3 months or later — enough for normal back-and-forth navigation
            // without another request. Greys out covered days in the grid.
            'timeOffs' => PractitionerTimeOffResource::collection(
                PractitionerTimeOff::query()
                    ->whereIn('practitioner_id', $practitioners->collection->pluck('id'))
                    ->whereDate('ends_on', '>=', today()->subMonths(3))
                    ->get(),
            ),
            // Same window, for whole-center closures — they grey out every
            // column of the displayed center, not just one practitioner's.
            'centerClosures' => CenterClosureResource::collection(
                CenterClosure::query()
                    ->whereIn('center_id', $visibleCenterIds)
                    ->whereDate('ends_on', '>=', today()->subMonths(3))
                    ->get(),
            ),
            'centerOperatingHours' => (object) CenterOperatingHours::query()
                ->whereIn('center_id', $visibleCenterIds)
                ->get()
                ->groupBy('center_id')
                ->map(fn ($hours) => CenterOperatingHoursResource::collection($hours)->resolve())
                ->all(),
            'centers' => $this->centerOptions($request),
            'practitioners' => $practitioners,
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

        // A reminder already sent was for the old time slot — send a new one.
        if (! CarbonImmutable::parse($validated['starts_at'])->eq($appointment->starts_at)) {
            $validated['reminder_sent_at'] = null;
        }

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
