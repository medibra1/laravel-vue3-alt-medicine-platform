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
            ->subject(__('Rappel — rendez-vous du :date', ['date' => $appointment->starts_at->translatedFormat('d F Y')]))
            ->greeting(__('Bonjour :name', ['name' => trim($appointment->patient->first_name.' '.$appointment->patient->last_name)]))
            ->line(__('Nous vous rappelons votre rendez-vous le :date à :time.', [
                'date' => $appointment->starts_at->translatedFormat('d F Y'),
                'time' => $appointment->starts_at->format('H:i'),
            ]))
            ->line(__('Centre : :center', ['center' => $appointment->center->name]))
            ->line(__('Praticien : :practitioner', ['practitioner' => trim($practitioner->first_name.' '.$practitioner->last_name)]));

        if ($appointment->modality === 'remote' && $appointment->meeting_link) {
            $mail->action(__('Rejoindre la consultation'), $appointment->meeting_link);
        }

        return $mail;
    }
}
