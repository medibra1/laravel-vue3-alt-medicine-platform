<?php

namespace App\Domains\Patients\Services;

use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\Patient;

/**
 * patient_number is the 4-digit segment appended after country+center
 * (see docs/schema-donnees.md and CLAUDE.md "identifiant du compte du
 * centre"), auto-generated per intake_center_id — unlike matricule/
 * centers.code, it is never edited manually.
 */
class PatientNumberGenerator
{
    public function next(Center $center): string
    {
        $lastNumber = Patient::query()
            ->where('intake_center_id', $center->id)
            ->orderByDesc('patient_number')
            ->value('patient_number');

        $next = $lastNumber ? ((int) $lastNumber) + 1 : 1;

        return str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
