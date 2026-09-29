<?php

namespace App\Domains\Scheduling\Rules;

use App\Domains\Core\Models\CenterClosure;
use App\Domains\Practitioners\Models\Practitioner;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Rejects an appointment whose day falls inside a closure of the chosen
 * practitioner's center. The center is resolved from the practitioner,
 * same inputs as NoTimeOffConflict.
 */
class NoCenterClosureConflict implements ValidationRule
{
    public function __construct(private readonly FormRequest $request) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $practitionerId = $this->request->integer('practitioner_id');
        $startsAt = $this->request->input('starts_at');

        if (! $practitionerId || ! $startsAt) {
            return;
        }

        $centerId = Practitioner::query()->whereKey($practitionerId)->value('center_id');

        if ($centerId && CenterClosure::query()->covering($centerId, CarbonImmutable::parse($startsAt))->exists()) {
            $fail('Le centre est fermé à cette date.');
        }
    }
}
