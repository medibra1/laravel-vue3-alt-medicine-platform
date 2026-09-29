<?php

use App\Domains\Auth\Models\User;
use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\Patient;
use App\Domains\Practitioners\Models\Practitioner;
use App\Domains\Scheduling\Models\Appointment;
use App\Domains\Scheduling\Notifications\AppointmentReminderNotification;
use App\Domains\Scheduling\Notifications\PatientAppointmentReminderNotification;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config(['scheduling.appointment_reminder_hours_before' => 24]);
    $this->center = Center::factory()->create();
    $this->practitionerUser = User::factory()->create();
    $this->practitioner = Practitioner::factory()->for($this->center, 'center')->create(['user_id' => $this->practitionerUser->id]);
    $this->makeAppointment = function (array $attributes = []): Appointment {
        $patient = $attributes['patient'] ?? Patient::factory()->create(['intake_center_id' => $this->center->id, 'email' => 'patient@example.test']);
        unset($attributes['patient']);

        return Appointment::query()->create(array_merge([
            'center_id' => $this->center->id,
            'practitioner_id' => $this->practitioner->id,
            'patient_id' => $patient->id,
            'starts_at' => now()->addHours(3),
            'duration_minutes' => 30,
            'status' => 'scheduled',
            'created_by' => $this->practitionerUser->id,
        ], $attributes));
    };
});

test('an appointment inside the window is reminded once and marked', function () {
    Notification::fake();
    $appointment = ($this->makeAppointment)();

    $this->artisan('appointments:send-reminders')->assertSuccessful();

    Notification::assertSentOnDemand(PatientAppointmentReminderNotification::class,
        fn ($n, $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'patient@example.test');
    Notification::assertSentTo($this->practitionerUser, AppointmentReminderNotification::class);
    expect($appointment->fresh()->reminder_sent_at)->not->toBeNull();
});

test('running the command twice does not send again', function () {
    Notification::fake();
    ($this->makeAppointment)();

    $this->artisan('appointments:send-reminders');
    $this->artisan('appointments:send-reminders');

    Notification::assertSentTimes(AppointmentReminderNotification::class, 1);
    Notification::assertSentOnDemandTimes(PatientAppointmentReminderNotification::class, 1);
});

test('appointments outside the window are ignored', function () {
    Notification::fake();
    $tooFar = ($this->makeAppointment)(['starts_at' => now()->addHours(30)]);
    $past = ($this->makeAppointment)(['starts_at' => now()->subHour()]);

    $this->artisan('appointments:send-reminders');

    Notification::assertNothingSent();
    expect($tooFar->fresh()->reminder_sent_at)->toBeNull()
        ->and($past->fresh()->reminder_sent_at)->toBeNull();
});

test('cancelled, no-show and completed appointments are ignored', function (string $status) {
    Notification::fake();
    $appointment = ($this->makeAppointment)(['status' => $status]);

    $this->artisan('appointments:send-reminders');

    Notification::assertNothingSent();
    expect($appointment->fresh()->reminder_sent_at)->toBeNull();
})->with(['cancelled', 'no_show', 'completed']);

test('a patient without email gets no mail but the practitioner is still notified', function () {
    Notification::fake();
    $patient = Patient::factory()->create(['intake_center_id' => $this->center->id, 'email' => null]);
    $appointment = ($this->makeAppointment)(['patient' => $patient]);

    $this->artisan('appointments:send-reminders')->assertSuccessful();

    Notification::assertSentOnDemandTimes(PatientAppointmentReminderNotification::class, 0);
    Notification::assertSentTo($this->practitionerUser, AppointmentReminderNotification::class);
    expect($appointment->fresh()->reminder_sent_at)->not->toBeNull();
});

test('a failing notification leaves reminder_sent_at null and does not stop the batch', function () {
    $failingPatient = Patient::factory()->create(['intake_center_id' => $this->center->id, 'email' => 'fail@example.test']);
    $failing = ($this->makeAppointment)(['patient' => $failingPatient, 'starts_at' => now()->addHours(2)]);
    $ok = ($this->makeAppointment)(['starts_at' => now()->addHours(4)]);

    // Real mail channel with a transport that throws for one recipient only.
    config(['mail.default' => 'array']);
    app('events')->listen(MessageSending::class, function ($event) {
        foreach ($event->message->getTo() as $address) {
            if ($address->getAddress() === 'fail@example.test') {
                throw new RuntimeException('Mail transport down');
            }
        }
    });

    $this->artisan('appointments:send-reminders')->assertSuccessful();

    expect($failing->fresh()->reminder_sent_at)->toBeNull()
        ->and($ok->fresh()->reminder_sent_at)->not->toBeNull();
});
