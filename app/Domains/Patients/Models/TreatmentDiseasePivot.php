<?php

namespace App\Domains\Patients\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Typed pivot for treatment_diseases — only exists so `->pivot->actively_tracked`
 * is a known property to static analysis (Eloquent's default anonymous
 * Pivot has no declared properties at all). See Treatment::diseases() for
 * where this is wired in via ->using().
 */
class TreatmentDiseasePivot extends Pivot
{
    protected $table = 'treatment_diseases';

    protected $casts = ['actively_tracked' => 'bool'];
}
