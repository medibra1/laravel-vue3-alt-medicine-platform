<?php

namespace App\Domains\Practitioners\Notifications;

use App\Domains\Core\Models\Center;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent the moment an already-registered practitioner is auto-joined to
 * a new center (same email matched an existing Practitioner.user_id —
 * see PractitionerAccountResolver/PractitionerController::store()).
 * No acceptance step: access is granted immediately, this just informs
 * them. Both mail and database channels, unlike ManagerAssignedNotification
 * (database only) — this is the one place in the app where a manager's
 * action on one center has a direct, unprompted effect on an account
 * that may currently be logged in and working from a different one.
 */
class PractitionerJoinedCenterNotification extends Notification
{
    public function __construct(private readonly Center $center) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.practitioner_joined.mail_subject'))
            ->greeting(__('notifications.practitioner_joined.mail_greeting'))
            ->line(__('notifications.practitioner_joined.mail_line', ['center' => $this->center->name]))
            ->line(__('notifications.practitioner_joined.mail_hint'))
            ->action(__('notifications.practitioner_joined.mail_action'), route('dashboard'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'practitioner_joined_center',
            // Translation keys + params, translated when displayed (see
            // NotificationResource) so the reader's language applies.
            'title' => 'notifications.practitioner_joined.title',
            'message' => 'notifications.practitioner_joined.message',
            'params' => ['center' => $this->center->name],
            'action_url' => route('dashboard'),
        ];
    }
}
