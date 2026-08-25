<?php

namespace App\Domains\Auth\Notifications;

use App\Domains\Core\Models\Center;
use Illuminate\Notifications\Notification;

/**
 * Sent (database channel only, see CLAUDE.md Phase 1 scope) the moment
 * a user is assigned as a center's manager — both creation modes fire
 * it immediately, so it's already there at the user's first login even
 * when they arrived through the invite flow.
 */
class ManagerAssignedNotification extends Notification
{
    public function __construct(private readonly Center $center) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'manager_assigned',
            'title' => __('Nouveau centre géré'),
            'message' => __('Vous avez été ajouté comme manager sur :center.', ['center' => $this->center->name]),
            'action_url' => route('admin.patients.index'),
        ];
    }
}
