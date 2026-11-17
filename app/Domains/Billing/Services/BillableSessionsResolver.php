<?php

namespace App\Domains\Billing\Services;

use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\CareCategory;
use App\Domains\Patients\Models\Patient;
use App\Domains\Patients\Models\TreatmentSession;
use Illuminate\Support\Str;

/**
 * Sessions of a patient not yet on any non-cancelled invoice, each with
 * the invoice lines the center's price list suggests for it.
 */
class BillableSessionsResolver
{
    /**
     * @return array<int, array{session_id: int, treatment_id: int, session_date: string, modality: string, lines: array<int, array{label: string, description: string|null, quantity: int, unit_price: float, treatment_session_id: int|null, care_item_id: int|null, care_category_id: int|null}>}>
     */
    public function handle(Patient $patient, Center $center): array
    {
        $prices = $center->careItemPrices()->pluck('price', 'care_item_id');
        $categoryPrices = $center->careCategoryPrices()->pluck('price', 'care_category_id');

        return TreatmentSession::query()
            ->whereHas('treatment', fn ($query) => $query->where('patient_id', $patient->id))
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('billing_invoice_lines')
                    ->join('billing_invoices', 'billing_invoices.id', '=', 'billing_invoice_lines.invoice_id')
                    ->whereColumn('billing_invoice_lines.treatment_session_id', 'treatment_sessions.id')
                    ->whereNull('billing_invoices.cancelled_at');
            })
            ->with('careItems.category')
            ->orderBy('session_date')
            ->get()
            ->map(function (TreatmentSession $session) use ($center, $prices, $categoryPrices) {
                $remote = $session->modality === 'remote';
                $date = $session->session_date->format('d/m/Y');

                // What was done during the session, spelled out on its
                // line — except flat-priced categories (cupping): the
                // zones treated are not listed on an invoice.
                $details = $session->careItems
                    ->filter(fn ($careItem) => ($careItem->category->pricing_mode ?? CareCategory::PRICING_ITEM) !== CareCategory::PRICING_CATEGORY)
                    ->map(fn ($careItem) => (string) $careItem->label)
                    ->implode(', ');

                $lines = [[
                    'description' => $details !== '' ? Str::limit($details, 990) : null,
                    'label' => $remote ? __('Séance à distance du :date', ['date' => $date]) : __('Séance du :date', ['date' => $date]),
                    'quantity' => 1,
                    'unit_price' => (float) $center->setting($remote ? 'session_price_remote' : 'session_price_in_person', 0),
                    'treatment_session_id' => $session->id,
                    'care_item_id' => null,
                    'care_category_id' => null,
                ]];

                $flatBilled = [];

                foreach ($session->careItems as $careItem) {
                    $category = $careItem->category;
                    $mode = $category->pricing_mode ?? CareCategory::PRICING_ITEM;

                    if ($mode === CareCategory::PRICING_NONE) {
                        continue;
                    }

                    // Flat price: one line per session for the whole
                    // category, however many of its items were used.
                    if ($mode === CareCategory::PRICING_CATEGORY) {
                        $price = (float) ($categoryPrices[$category->id] ?? 0);

                        if ($price > 0 && ! isset($flatBilled[$category->id])) {
                            $flatBilled[$category->id] = true;
                            $lines[] = [
                                'description' => null,
                                'label' => (string) $category->label,
                                'quantity' => 1,
                                'unit_price' => $price,
                                'treatment_session_id' => null,
                                'care_item_id' => null,
                                'care_category_id' => $category->id,
                            ];
                        }

                        continue;
                    }

                    if ((float) ($prices[$careItem->id] ?? 0) > 0) {
                        $lines[] = [
                            'description' => null,
                            'label' => (string) $careItem->label,
                            'quantity' => 1,
                            'unit_price' => (float) $prices[$careItem->id],
                            'treatment_session_id' => null,
                            'care_item_id' => $careItem->id,
                            'care_category_id' => null,
                        ];
                    }
                }

                return [
                    'session_id' => $session->id,
                    'treatment_id' => $session->treatment_id,
                    'session_date' => $session->session_date->toDateString(),
                    'modality' => $session->modality,
                    'lines' => $lines,
                ];
            })
            ->all();
    }
}
