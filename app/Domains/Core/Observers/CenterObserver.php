<?php

namespace App\Domains\Core\Observers;

use App\Domains\Core\Models\Center;
use Illuminate\Validation\ValidationException;

class CenterObserver
{
    /**
     * The center code is unique per country, never globally — checked
     * here in addition to the DB constraint for a clear admin-facing
     * error message.
     */
    public function saving(Center $center): void
    {
        $exists = Center::query()
            ->where('country_id', $center->country_id)
            ->where('code', $center->code)
            ->when($center->exists, fn ($q) => $q->whereKeyNot($center->getKey()))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'code' => __('Ce code centre est déjà utilisé pour ce pays.'),
            ]);
        }
    }
}
