<?php

namespace App\Domains\Billing\Services;

use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\Patient;
use App\Domains\Patients\Models\TreatmentSession;

/**
 * Sessions of a patient not yet on any non-cancelled invoice, each with
 * the invoice lines the center's price list suggests for it.
 */
class BillableSessionsResolver
{
    /**
     * @return array<int, array{session_id: int, treatment_id: int, session_date: string, modality: string, lines: array<int, array{label: string, quantity: int, unit_price: float, treatment_session_id: int|null, care_item_id: int|null}>}>
     */
    public function handle(Patient $patient, Center $center): array
    {
        $prices = $center->careItemPrices()->pluck('price', 'care_item_id');

        return TreatmentSession::query()
            ->whereHas('treatment', fn ($query) => $query->where('patient_id', $patient->id))
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('billing_invoice_lines')
                    ->join('billing_invoices', 'billing_invoices.id', '=', 'billing_invoice_lines.invoice_id')
                    ->whereColumn('billing_invoice_lines.treatment_session_id', 'treatment_sessions.id')
                    ->whereNull('billing_invoices.cancelled_at');
            })
            ->with('careItems')
            ->orderBy('session_date')
            ->get()
            ->map(function (TreatmentSession $session) use ($center, $prices) {
                $remote = $session->modality === 'remote';
                $date = $session->session_date->format('d/m/Y');

                $lines = [[
                    'label' => $remote ? __('Séance à distance du :date', ['date' => $date]) : __('Séance du :date', ['date' => $date]),
                    'quantity' => 1,
                    'unit_price' => (float) $center->setting($remote ? 'session_price_remote' : 'session_price_in_person', 0),
                    'treatment_session_id' => $session->id,
                    'care_item_id' => null,
                ]];

                foreach ($session->careItems as $careItem) {
                    if ((float) ($prices[$careItem->id] ?? 0) > 0) {
                        $lines[] = [
                            'label' => (string) $careItem->label,
                            'quantity' => 1,
                            'unit_price' => (float) $prices[$careItem->id],
                            'treatment_session_id' => null,
                            'care_item_id' => $careItem->id,
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
