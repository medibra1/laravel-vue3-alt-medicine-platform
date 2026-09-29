<?php

namespace App\Domains\Scheduling\Notifications;

use App\Domains\Scheduling\Models\Appointment;
use Illuminate\Notifications\Notification;

/**
 * Sent by appointments:send-reminders to the assigned practitioner
 * (database channel only, same V1 scope as AppointmentAssignedNotification).
 * Not queued on purpose — QUEUE_CONNECTION=sync by default in this project.
 */
class AppointmentReminderNotification extends Notification
{
    public function __construct(private readonly Appointment $appointment) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'appointment_reminder',
            'title' => __('Rappel de rendez-vous'),
            'message' => __('Rendez-vous le :date avec :patient.', [
                'date' => $this->appointment->starts_at->translatedFormat('d F Y à H:i'),
                'patient' => trim($this->appointment->patient->first_name.' '.$this->appointment->patient->last_name),
            ]),
            'action_url' => route('admin.patients.edit', $this->appointment->patient_id),
        ];
    }
}
