<?php

namespace Database\Seeders;

use App\Domains\Patients\Models\CareCategory;
use App\Domains\Patients\Models\CareItem;
use Illuminate\Database\Seeder;

class CareCategorySeeder extends Seeder
{
    /**
     * Care/remedy catalog used at TreatmentSession level (what was
     * administered during a session) — hand-rolled tables mirroring
     * DiseaseCategory/Disease rather than EnumOption.parent_id, per the
     * decision documented in CLAUDE.md "Wizard Treatment..." (parent_id
     * has zero real usage anywhere in the codebase).
     *
     * ⚠️ PLACEHOLDER content — no source document exists for this
     * catalog yet (unlike the 8+1 disease categories, which come from
     * LISTES_DES_MALADIES.docx). Item lists below are minimal examples
     * to exercise the session-logging flow end-to-end; replace with
     * real content once provided.
     *
     * "Verset" (Quranic verse) is a translatable label/content value,
     * not a code/table/column identifier — consistent with the project's
     * terminology policy (see CLAUDE.md "Terminologie"), which targets
     * how entities are NAMED in code/schema, not user-facing seeded
     * content (disease 408 "Blocage religion" is existing precedent).
     */
    public function run(): void
    {
        $categories = [
            ['code' => 'ointment', 'label' => ['fr' => 'Pommade', 'en' => 'Ointment'], 'order' => 1, 'items' => [
                ['code' => '001', 'label' => ['fr' => 'Pommade au miel (placeholder)', 'en' => 'Honey ointment (placeholder)']],
            ]],
            ['code' => 'bath', 'label' => ['fr' => 'Bain', 'en' => 'Bath'], 'order' => 2, 'items' => [
                ['code' => '001', 'label' => ['fr' => 'Bain aux plantes (placeholder)', 'en' => 'Herbal bath (placeholder)']],
            ]],
            ['code' => 'incense', 'label' => ['fr' => 'Encens', 'en' => 'Incense'], 'order' => 3, 'items' => [
                ['code' => '001', 'label' => ['fr' => 'Encens naturel (placeholder)', 'en' => 'Natural incense (placeholder)']],
            ]],
            ['code' => 'tea', 'label' => ['fr' => 'Tisane', 'en' => 'Herbal tea'], 'order' => 4, 'items' => [
                ['code' => '001', 'label' => ['fr' => 'Tisane apaisante (placeholder)', 'en' => 'Soothing tea (placeholder)']],
            ]],
            ['code' => 'verse', 'label' => ['fr' => 'Verset', 'en' => 'Verse'], 'order' => 5, 'items' => [
                ['code' => '001', 'label' => ['fr' => 'Verset 1 (placeholder)', 'en' => 'Verse 1 (placeholder)']],
            ]],
        ];

        foreach ($categories as $categoryData) {
            $category = CareCategory::query()->firstOrCreate(
                ['code' => $categoryData['code']],
                ['label' => $categoryData['label'], 'order' => $categoryData['order'], 'active' => true]
            );

            foreach ($categoryData['items'] as $itemData) {
                CareItem::query()->firstOrCreate(
                    ['care_category_id' => $category->id, 'code' => $itemData['code']],
                    ['label' => $itemData['label'], 'active' => true]
                );
            }
        }
    }
}
