<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Auth\Models\User;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use App\Domains\Core\Models\Center;
use App\Domains\Core\Support\Modules;
use App\Domains\Patients\Models\Disease;
use App\Domains\Patients\Models\Patient;
use App\Domains\Patients\Models\Treatment;
use App\Domains\Patients\Models\TreatmentSession;
use App\Domains\Practitioners\Models\Practitioner;
use App\Domains\Scheduling\Models\Appointment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Everything the dashboard shows, for one center or (centerId null) all
 * of them. Each block is only computed when its module is enabled.
 * Month bucketing is done in PHP so the same code runs on sqlite and MySQL.
 */
class DashboardStats
{
    private const MONTHS = 12;

    /**
     * @param  array<int, string>  $modules
     * @return array<string, mixed>
     */
    public function handle(User $user, ?int $centerId, array $modules): array
    {
        $has = fn (string $module): bool => in_array($module, $modules, true);
        $months = $this->months();
        $data = ['months' => $months->map(fn (Carbon $month) => $month->format('Y-m'))->all()];

        if ($has(Modules::PATIENTS)) {
            $patients = fn () => Patient::query()->when($centerId, fn ($query) => $query->where('intake_center_id', $centerId));
            $thisMonth = $patients()->where('created_at', '>=', now()->startOfMonth())->count();
            $lastMonth = $patients()->whereBetween('created_at', [now()->subMonthNoOverflow()->startOfMonth(), now()->startOfMonth()])->count();

            $data['patients'] = [
                'total' => $patients()->count(),
                'new_this_month' => $thisMonth,
                'new_last_month' => $lastMonth,
                'series' => $this->bucket($patients()->where('created_at', '>=', $months->first())->pluck('created_at'), $months),
                'recent' => $patients()->latest()->limit(5)->get(['id', 'first_name', 'last_name', 'created_at'])
                    ->map(fn (Patient $patient) => [
                        'id' => $patient->id,
                        'name' => trim($patient->first_name.' '.$patient->last_name) ?: '—',
                        'created_at' => $patient->created_at,
                    ])->all(),
            ];
        }

        if ($has(Modules::TREATMENTS)) {
            $treatments = fn () => Treatment::query()->when($centerId, fn ($query) => $query->where('center_id', $centerId));
            $sessions = fn () => TreatmentSession::query()
                ->when($centerId, fn ($query) => $query->whereHas('treatment', fn ($query) => $query->where('center_id', $centerId)));

            $data['treatments'] = [
                'ongoing' => $treatments()->currentStatus('ongoing')->count(),
                'closed' => $treatments()->currentStatus('closed')->count(),
                'closure_reasons' => $treatments()->currentStatus('closed')->whereNotNull('closure_reason')
                    ->groupBy('closure_reason')->selectRaw('closure_reason, COUNT(*) as total')->pluck('total', 'closure_reason')->all(),
                'sessions_this_month' => $sessions()->where('session_date', '>=', now()->startOfMonth()->toDateString())->count(),
                'sessions_last_month' => $sessions()
                    ->where('session_date', '>=', now()->subMonthNoOverflow()->startOfMonth()->toDateString())
                    ->where('session_date', '<', now()->startOfMonth()->toDateString())->count(),
                'sessions_series' => $this->bucket($sessions()->where('session_date', '>=', $months->first()->toDateString())->pluck('session_date'), $months),
                'outcomes' => $this->outcomes($centerId),
                'top_diseases' => $this->topDiseases($centerId),
            ];
        }

        if ($has(Modules::SCHEDULING)) {
            $appointments = fn () => Appointment::query()->when($centerId, fn ($query) => $query->where('center_id', $centerId));
            $monthStatuses = $appointments()->where('starts_at', '>=', now()->startOfMonth())->where('starts_at', '<', now()->startOfMonth()->addMonth())
                ->groupBy('status')->selectRaw('status, COUNT(*) as total')->pluck('total', 'status');

            $data['appointments'] = [
                'today' => $appointments()->whereBetween('starts_at', [now()->startOfDay(), now()->endOfDay()])->where('status', '!=', 'cancelled')->count(),
                'next_7_days' => $appointments()->where('status', 'scheduled')->whereBetween('starts_at', [now(), now()->addDays(7)])->count(),
                'month_statuses' => $monthStatuses->all(),
                'upcoming' => $appointments()->where('status', 'scheduled')->where('starts_at', '>=', now()->startOfDay())
                    ->with(['patient', 'practitioner'])->orderBy('starts_at')->limit(6)->get()
                    ->map(fn (Appointment $appointment) => [
                        'id' => $appointment->id,
                        'starts_at' => $appointment->starts_at,
                        'duration_minutes' => $appointment->duration_minutes,
                        'modality' => $appointment->modality,
                        'patient_id' => $appointment->patient_id,
                        'patient' => trim($appointment->patient->first_name.' '.$appointment->patient->last_name),
                        'practitioner_id' => $appointment->practitioner_id,
                        'practitioner' => trim($appointment->practitioner->first_name.' '.$appointment->practitioner->last_name),
                    ])->all(),
            ];
        }

        if ($has(Modules::PRACTITIONERS)) {
            $data['practitioners'] = [
                'total' => Practitioner::query()->when($centerId, fn ($query) => $query->where('center_id', $centerId))->count(),
            ];
        }

        if ($has(Modules::BILLING)) {
            $data['billing'] = $this->billing($centerId, $months);
        }

        if ($centerId === null && $user->isGlobalAdmin()) {
            $data['centers'] = Center::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'city'])
                ->map(fn (Center $center) => [
                    'id' => $center->id,
                    'name' => $center->name,
                    'city' => $center->city,
                    'patients' => Patient::query()->where('intake_center_id', $center->id)->count(),
                    'ongoing_treatments' => Treatment::query()->where('center_id', $center->id)->currentStatus('ongoing')->count(),
                    'practitioners' => Practitioner::query()->where('center_id', $center->id)->count(),
                ])->all();
        }

        return $data;
    }

    /** @return Collection<int, Carbon> */
    private function months(): Collection
    {
        return collect(range(self::MONTHS - 1, 0))->map(fn (int $ago) => now()->startOfMonth()->subMonthsNoOverflow($ago));
    }

    /**
     * @param  Collection<int, mixed>  $dates
     * @param  Collection<int, Carbon>  $months
     * @return array<int, int>
     */
    private function bucket(Collection $dates, Collection $months): array
    {
        $counts = $dates->countBy(fn ($date) => Carbon::parse($date)->format('Y-m'));

        return $months->map(fn (Carbon $month) => (int) ($counts[$month->format('Y-m')] ?? 0))->all();
    }

    /**
     * Latest known outcome of every (treatment, disease) pair.
     *
     * @return array<string, int>
     */
    private function outcomes(?int $centerId): array
    {
        $rows = DB::table('treatment_session_disease_progress as progress')
            ->join('treatment_sessions as sessions', 'sessions.id', '=', 'progress.treatment_session_id')
            ->join('treatments', 'treatments.id', '=', 'sessions.treatment_id')
            ->when($centerId, fn ($query) => $query->where('treatments.center_id', $centerId))
            ->orderBy('sessions.session_date')
            ->orderBy('sessions.id')
            ->get(['sessions.treatment_id', 'progress.disease_id', 'progress.outcome']);

        return $rows
            ->keyBy(fn ($row) => $row->treatment_id.'-'.$row->disease_id) // later rows win
            ->countBy('outcome')
            ->all();
    }

    /** @return array<int, array{label: string, total: int}> */
    private function topDiseases(?int $centerId): array
    {
        $totals = DB::table('treatment_diseases')
            ->join('treatments', 'treatments.id', '=', 'treatment_diseases.treatment_id')
            ->when($centerId, fn ($query) => $query->where('treatments.center_id', $centerId))
            ->groupBy('treatment_diseases.disease_id')
            ->selectRaw('treatment_diseases.disease_id as disease_id, COUNT(*) as total')
            ->orderByDesc('total')
            ->limit(6)
            ->pluck('total', 'disease_id');

        $diseases = Disease::query()->whereIn('id', $totals->keys())->get()->keyBy('id');

        return $totals->map(fn ($total, $diseaseId) => [
            'label' => (string) ($diseases[$diseaseId]->label ?? $diseaseId),
            'total' => (int) $total,
        ])->values()->all();
    }

    /**
     * @param  Collection<int, Carbon>  $months
     * @return array<string, mixed>
     */
    private function billing(?int $centerId, Collection $months): array
    {
        $payments = Payment::query()
            ->join('billing_invoices', 'billing_invoices.id', '=', 'billing_payments.invoice_id')
            ->when($centerId, fn ($query) => $query->where('billing_invoices.center_id', $centerId))
            ->where('billing_payments.paid_on', '>=', $months->first()->toDateString())
            ->get(['billing_payments.amount', 'billing_payments.paid_on', 'billing_payments.method', 'billing_invoices.currency']);

        $monthKey = now()->format('Y-m');
        $lastMonthKey = now()->subMonthNoOverflow()->format('Y-m');

        $byCurrency = $payments->groupBy('currency')->map(function (Collection $rows, string $currency) use ($months, $monthKey, $lastMonthKey) {
            $perMonth = $rows->groupBy(fn ($row) => Carbon::parse($row->paid_on)->format('Y-m'))->map(fn (Collection $rows) => round((float) $rows->sum('amount'), 2));

            return [
                'currency' => $currency,
                'collected_this_month' => (float) ($perMonth[$monthKey] ?? 0),
                'collected_last_month' => (float) ($perMonth[$lastMonthKey] ?? 0),
                'series' => $months->map(fn (Carbon $month) => (float) ($perMonth[$month->format('Y-m')] ?? 0))->all(),
                'methods' => $rows->filter(fn ($row) => Carbon::parse($row->paid_on)->format('Y-m') === $monthKey)
                    ->groupBy('method')->map(fn (Collection $rows) => round((float) $rows->sum('amount'), 2))->all(),
            ];
        });

        $outstanding = Invoice::query()->active()
            ->when($centerId, fn ($query) => $query->where('center_id', $centerId))
            ->groupBy('currency')->selectRaw('currency, SUM(total - paid_total) as amount, COUNT(*) as invoices')
            ->havingRaw('SUM(total - paid_total) > 0')->get();

        // A currency with unpaid invoices but no payment yet still gets a row.
        foreach ($outstanding as $row) {
            if (! $byCurrency->has($row->currency)) {
                $byCurrency[$row->currency] = [
                    'currency' => $row->currency,
                    'collected_this_month' => 0.0,
                    'collected_last_month' => 0.0,
                    'series' => $months->map(fn () => 0.0)->all(),
                    'methods' => [],
                ];
            }
        }

        $outstandingByCurrency = $outstanding->pluck('amount', 'currency');

        return [
            'currencies' => $byCurrency->map(fn (array $row) => [
                ...$row,
                'outstanding' => round((float) ($outstandingByCurrency[$row['currency']] ?? 0), 2),
            ])->values()->all(),
            'unpaid_invoices' => Invoice::query()->active()->when($centerId, fn ($query) => $query->where('center_id', $centerId))
                ->whereColumn('paid_total', '<', 'total')->count(),
        ];
    }
}
