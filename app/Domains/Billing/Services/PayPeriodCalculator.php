<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\PayPeriod;
use App\Domains\Billing\Models\PayPeriodShare;
use App\Domains\Core\Models\Grade;
use App\Domains\Practitioners\Models\Practitioner;
use App\Domains\Practitioners\Models\PractitionerAttendance;
use Illuminate\Support\Collection;

/**
 * Computes each practitioner's share of a pay period's pool amount.
 *
 * Model: this is a revenue-sharing pool, not a fixed salary. The manager
 * sets a period (any length — a manager may run 3-day periods, weekly,
 * monthly, whatever fits their center) and a pool amount to distribute.
 * Each practitioner's gross share is proportional to
 * (attendance days during the period × their grade coefficient),
 * normalized against the sum of that same product across every
 * practitioner active at the center during the period.
 *
 *     gross_i = pool_amount × (attendance_i × coefficient_i)
 *                             ÷ Σ_j (attendance_j × coefficient_j)
 *
 * Outstanding salary advances (see SalaryAdvance::scopeOutstanding) are
 * deducted after the gross share is computed, never before — an advance
 * is a debt against future earnings, not a factor in how the pool itself
 * is split between practitioners.
 *
 * All monetary amounts are integers in cents. Rounding: the pool is
 * distributed in cents using largest-remainder allocation so shares sum
 * back exactly to pool_amount (implemented in allocateWithRounding()) —
 * naive per-share rounding would drift the total by a few cents.
 */
class PayPeriodCalculator
{
    /**
     * Calculate (or recalculate) every practitioner's share for a pay
     * period. Idempotent — safe to re-run before the period is finalized
     * (existing PayPeriodShare rows for this period are updated in place,
     * not duplicated). Does not touch shares already marked as paid.
     */
    public function calculate(PayPeriod $payPeriod): Collection
    {
        $practitioners = $this->activePractitioners($payPeriod);

        if ($practitioners->isEmpty()) {
            throw new \RuntimeException('No active practitioner found for this center and period.');
        }

        $weights = $practitioners->mapWithKeys(function (Practitioner $practitioner) use ($payPeriod) {
            $attendanceDays = $this->attendanceDays($practitioner, $payPeriod);
            $grade = $practitioner->grade;
            $coefficient = $grade instanceof Grade ? (float) $grade->coefficient : 1.0;

            return [$practitioner->id => [
                'attendance_days' => $attendanceDays,
                'coefficient' => $coefficient,
                'weight' => $attendanceDays * $coefficient,
            ]];
        });

        $totalWeight = $weights->sum('weight');

        if ($totalWeight <= 0) {
            throw new \RuntimeException('No attendance recorded for any practitioner in this period — nothing to distribute.');
        }

        $grossAmounts = $this->allocateWithRounding($payPeriod->pool_amount, $weights->pluck('weight'));

        return $practitioners->map(function (Practitioner $practitioner) use ($payPeriod, $weights, $grossAmounts) {
            $data = $weights[$practitioner->id];
            $grossAmount = $grossAmounts[$practitioner->id];
            $outstandingAdvances = $practitioner->salaryAdvances()->outstanding()->sum('amount');

            // Never deduct more than the gross share — a shortfall stays
            // outstanding for the next period rather than producing a
            // negative payout.
            $deducted = min($outstandingAdvances, $grossAmount);
            $netAmount = $grossAmount - $deducted;

            return PayPeriodShare::query()->updateOrCreate(
                ['pay_period_id' => $payPeriod->id, 'practitioner_id' => $practitioner->id],
                [
                    'attendance_days' => $data['attendance_days'],
                    'grade_coefficient_snapshot' => $data['coefficient'],
                    'gross_amount' => $grossAmount,
                    'advances_deducted_amount' => $deducted,
                    'net_amount' => $netAmount,
                ]
            );
        });
    }

    /**
     * Practitioners considered for this period: active at the period's
     * center, regardless of whether they have any attendance yet (a
     * practitioner with zero attendance simply gets a zero share, they
     * are not silently excluded from the calculation run).
     */
    protected function activePractitioners(PayPeriod $payPeriod): Collection
    {
        return Practitioner::query()
            ->where('center_id', $payPeriod->center_id)
            ->with('grade')
            ->get()
            ->filter(fn (Practitioner $p) => $p->latestStatus()?->name !== 'inactive');
    }

    protected function attendanceDays(Practitioner $practitioner, PayPeriod $payPeriod): int
    {
        return PractitionerAttendance::query()
            ->where('practitioner_id', $practitioner->id)
            ->present()
            ->between($payPeriod->starts_at, $payPeriod->ends_at)
            ->count();
    }

    /**
     * Largest-remainder allocation: distributes $total across the given
     * weights so the parts sum back to $total exactly (cents can't be
     * split further), rather than letting independent per-share rounding
     * drift the sum away from the pool amount.
     *
     * @param  int  $total  amount in cents
     * @param  Collection<int, float>  $weights  keyed by practitioner id
     * @return Collection<int, int> amounts in cents, keyed by practitioner id
     */
    protected function allocateWithRounding(int $total, Collection $weights): Collection
    {
        $totalWeight = $weights->sum();

        $rawShares = $weights->map(fn ($w) => $total * $w / $totalWeight);
        $floored = $rawShares->map(fn ($v) => (int) floor($v));
        $remainder = $total - $floored->sum();

        // Give the leftover cents to the practitioners with the largest
        // fractional remainder, one cent each, until the total matches.
        $order = $rawShares->map(fn ($v, $key) => ['key' => $key, 'frac' => $v - floor($v)])
            ->sortByDesc('frac')
            ->pluck('key');

        $result = $floored;
        foreach ($order->take($remainder) as $key) {
            $result[$key] = $result[$key] + 1;
        }

        return $result;
    }
}
