<?php

namespace App\Domains\Auth\Listeners;

use App\Domains\Auth\Models\User;
use Illuminate\Auth\Events\PasswordReset;

/**
 * An invited user (created with email_verified_at = null, see
 * UserController::store()) proves ownership of their email by clicking
 * the emailed password.reset link and successfully setting a password
 * — that act stands in for a separate email-verification step, so this
 * marks the address verified instead of forcing yet another link.
 */
class MarkEmailVerifiedOnPasswordReset
{
    public function handle(PasswordReset $event): void
    {
        /** @var User $user */
        $user = $event->user;

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }
    }
}
