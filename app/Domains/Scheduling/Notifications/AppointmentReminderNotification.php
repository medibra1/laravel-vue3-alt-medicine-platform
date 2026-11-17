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
            // Translation keys + params, translated when displayed (see
            // NotificationResource). `date` is kept as an ISO string in
            // the center's time zone and formatted for the reader's locale.
            'title' => 'notifications.appointment_reminder.title',
            'message' => 'notifications.appointment.message',
            'params' => [
                'date' => $this->appointment->localStartsAt()->toIso8601String(),
                'patient' => trim($this->appointment->patient->first_name.' '.$this->appointment->patient->last_name),
            ],
            'date_params' => ['date'],
            'action_url' => route('admin.patients.edit', $this->appointment->patient_id),
        ];
    }
}
