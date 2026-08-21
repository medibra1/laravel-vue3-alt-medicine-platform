<?php

namespace App\Domains\Patients\Services;

use App\Domains\Patients\Models\Disease;
use App\Domains\Patients\Models\DiseaseCategory;

/**
 * Next free 3-digit disease code within a category — a suggestion
 * only, the form field stays editable (same pattern as
 * CenterCodeGenerator::suggestNext()).
 */
class DiseaseCodeGenerator
{
    public function suggestNext(DiseaseCategory $category): string
    {
        $lastCode = Disease::query()
            ->where('disease_category_id', $category->id)
            ->orderByDesc('code')
            ->value('code');

        $next = $lastCode ? ((int) $lastCode) + 1 : 1;

        return str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
