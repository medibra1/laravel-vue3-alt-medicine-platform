<?php

namespace App\Domains\Core\Services;

use App\Domains\Core\Models\Center;
use App\Domains\Core\Models\Country;

/**
 * Next free 2-digit center code within a country — a suggestion only,
 * the form field stays editable (some countries already hand out their
 * own center numbers, see CLAUDE.md "identifiant du compte du centre").
 */
class CenterCodeGenerator
{
    public function suggestNext(Country $country): string
    {
        $lastCode = Center::query()
            ->where('country_id', $country->id)
            ->orderByDesc('code')
            ->value('code');

        $next = $lastCode ? ((int) $lastCode) + 1 : 1;

        return str_pad((string) $next, 2, '0', STR_PAD_LEFT);
    }
}
