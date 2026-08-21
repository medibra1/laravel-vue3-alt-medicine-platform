<?php

namespace App\Domains\Practitioners\Services;

use App\Domains\Core\Models\Center;
use App\Domains\Practitioners\Models\Practitioner;

/**
 * Computes the practitioner's 7-digit code: country (2) + center (2) +
 * matricule (3). Never entered manually — recalculated whenever the
 * center or matricule changes.
 */
class PractitionerCodeGenerator
{
    public function generate(Practitioner $practitioner): string
    {
        $practitioner->loadMissing('center.country');

        $countryCode = $practitioner->center->country->code;
        $centerCode = $practitioner->center->code;
        $matricule = str_pad((string) $practitioner->matricule, 3, '0', STR_PAD_LEFT);

        return "{$countryCode}{$centerCode}{$matricule}";
    }

    /**
     * Next free 3-digit matricule in this center — a suggestion only,
     * the form field stays editable (a manager may already have a real
     * diploma/registration number they want to use instead).
     */
    public function suggestNextMatricule(Center $center): string
    {
        $lastMatricule = Practitioner::query()
            ->where('center_id', $center->id)
            ->orderByDesc('matricule')
            ->value('matricule');

        $next = $lastMatricule ? ((int) $lastMatricule) + 1 : 1;

        return str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
