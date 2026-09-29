<?php

namespace App\Domains\Scheduling\Console\Commands;

use App\Domains\Scheduling\Models\Appointment;
use App\Domains\Scheduling\Notifications\AppointmentReminderNotification;
use App\Domains\Scheduling\Notifications\PatientAppointmentReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Scheduled every 15 minutes (bootstrap/app.php). Requires the server cron
 * `* * * * * php artisan schedule:run` — nothing runs without it.
 * Notifications are sent synchronously (no ShouldQueue), consistent with
 * QUEUE_CONNECTION=sync used elsewhere (media conversions).
 */
class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders';

    protected $description = 'Send the reminder email/notification for appointments entering the reminder window, once each.';

    public function handle(): int
    {
        $hoursBefore = (int) config('scheduling.appointment_reminder_hours_before');
        $threshold = now()->addHours($hoursBefore);

        $appointments = Appointment::query()
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->whereNull('reminder_sent_at')
            ->where('starts_at', '>', now()) // never remind for a slot already past
            ->where('starts_at', '<=', $threshold)
            ->with(['patient', 'practitioner.user', 'center'])
            ->get();

        $sent = 0;
        foreach ($appointments as $appointment) {
            $sent += $this->remind($appointment) ? 1 : 0;
        }

        $this->info("Sent reminders for {$sent} appointment(s).");

        return self::SUCCESS;
    }

    private function remind(Appointment $appointment): bool
    {
        try {
            if ($appointment->patient->email) {
                Notification::route('mail', $appointment->patient->email)
                    ->notify(new PatientAppointmentReminderNotification($appointment));
            }

            $appointment->practitioner->user?->notify(new AppointmentReminderNotification($appointment));

            // reminder_sent_at is the idempotency lock: only set once
            // everything went through, so a failure is retried next run
            // (until starts_at passes, which excludes it naturally).
            $appointment->update(['reminder_sent_at' => now()]);

            return true;
        } catch (\Throwable $e) {
            // One bad appointment must never stop the rest of the batch.
            report($e);

            return false;
        }
    }
}
