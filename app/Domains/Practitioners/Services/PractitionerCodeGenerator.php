<?php

namespace App\Domains\Practitioners\Services;

use App\Domains\Practitioners\Models\Practitioner;

/**
 * Computes the practitioner's 7-digit code: country (2) + center (2) +
 * diploma (3). Never entered manually — recalculated whenever the
 * center or diploma number changes.
 */
class PractitionerCodeGenerator
{
    public function generate(Practitioner $practitioner): string
    {
        $practitioner->loadMissing('center.country');

        $countryCode = $practitioner->center->country->code;
        $centerCode = $practitioner->center->code;
        $diplomaNumber = str_pad((string) $practitioner->diploma_number, 3, '0', STR_PAD_LEFT);

        return "{$countryCode}{$centerCode}{$diplomaNumber}";
    }
}
