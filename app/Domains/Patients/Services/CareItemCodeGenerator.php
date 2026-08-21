<?php

namespace App\Domains\Patients\Services;

use App\Domains\Patients\Models\CareCategory;
use App\Domains\Patients\Models\CareItem;

/**
 * Next free 3-digit care item code within a category — a suggestion
 * only, the form field stays editable (same pattern as
 * CenterCodeGenerator::suggestNext() / DiseaseCodeGenerator::suggestNext()).
 */
class CareItemCodeGenerator
{
    public function suggestNext(CareCategory $category): string
    {
        $lastCode = CareItem::query()
            ->where('care_category_id', $category->id)
            ->orderByDesc('code')
            ->value('code');

        $next = $lastCode ? ((int) $lastCode) + 1 : 1;

        return str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
