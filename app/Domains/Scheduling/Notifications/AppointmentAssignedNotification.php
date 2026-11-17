<?php

namespace App\Domains\Scheduling\Notifications;

use App\Domains\Scheduling\Models\Appointment;
use Illuminate\Notifications\Notification;

/**
 * Sent (database channel only, same V1 scope as ManagerAssignedNotification
 * — see CLAUDE.md) to the practitioner assigned to an appointment, on
 * both creation and reschedule. Pre-appointment reminders are handled
 * separately by appointments:send-reminders (AppointmentReminderNotification).
 */
class AppointmentAssignedNotification extends Notification
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
            'type' => 'appointment_assigned',
            'title' => __('Nouveau rendez-vous'),
            'message' => __('Rendez-vous le :date avec :patient.', [
                'date' => $this->appointment->localStartsAt()->translatedFormat('d F Y à H:i'),
                'patient' => trim($this->appointment->patient->first_name.' '.$this->appointment->patient->last_name),
            ]),
            'action_url' => route('admin.patients.edit', $this->appointment->patient_id),
        ];
    }
}
