<?php

namespace App\Domains\Scheduling\Notifications;

use App\Domains\Scheduling\Models\Appointment;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Mail reminder sent on demand to the patient's email (Patient is not
 * Notifiable — not a user account). Email only in this V1: no SMS
 * gateway is configured, `phone` stays unused for reminders.
 */
class PatientAppointmentReminderNotification extends Notification
{
    public function __construct(private readonly Appointment $appointment) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appointment = $this->appointment;
        $practitioner = $appointment->practitioner;

        $mail = (new MailMessage)
            ->subject(__('notifications.patient_reminder.subject', ['date' => $appointment->localStartsAt()->translatedFormat('d F Y')]))
            ->greeting(__('notifications.patient_reminder.greeting', ['name' => trim($appointment->patient->first_name.' '.$appointment->patient->last_name)]))
            ->line(__('notifications.patient_reminder.line', [
                'date' => $appointment->localStartsAt()->translatedFormat('d F Y'),
                'time' => $appointment->localStartsAt()->format('H:i'),
            ]))
            ->line(__('notifications.patient_reminder.center', ['center' => $appointment->center->name]))
            ->line(__('notifications.patient_reminder.practitioner', ['practitioner' => trim($practitioner->first_name.' '.$practitioner->last_name)]));

        if ($appointment->modality === 'remote' && $appointment->meeting_link) {
            $mail->action(__('notifications.patient_reminder.join'), $appointment->meeting_link);
        }

        return $mail;
    }
}
