<?php

namespace App\Domains\Practitioners\Services;

use App\Domains\Auth\Models\User;
use App\Domains\Practitioners\Models\Practitioner;

/**
 * Resolves what an email means for the "grant access" flow on the
 * Practitioner creation form — shared between
 * PractitionerController::checkAccount() (frontend preview, debounced)
 * and store() (authoritative, re-checked server-side independently of
 * whatever the frontend last saw) so the two can never disagree.
 */
class PractitionerAccountResolver
{
    /**
     * @return array{status: 'new'|'existing'|'taken', user: User|null, practitioner: Practitioner|null}
     */
    public function resolve(string $email): array
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            return ['status' => 'new', 'user' => null, 'practitioner' => null];
        }

        $practitioner = Practitioner::query()->where('user_id', $user->id)->first();

        if ($practitioner) {
            return ['status' => 'existing', 'user' => $user, 'practitioner' => $practitioner];
        }

        // The email belongs to a real account (admin/manager/another
        // practitioner-less user) — never silently attach a new
        // Practitioner row to it, that account already means something
        // else in the system.
        return ['status' => 'taken', 'user' => $user, 'practitioner' => null];
    }
}
