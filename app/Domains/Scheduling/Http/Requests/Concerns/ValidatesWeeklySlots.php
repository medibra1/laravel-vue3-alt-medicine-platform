<?php

namespace App\Domains\Scheduling\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

/**
 * Shared by the single- and multi-practitioner weekly schedule sync
 * requests: same slot shape, same "no overlap within a day" rule.
 */
trait ValidatesWeeklySlots
{
    /** @return array<string, mixed> */
    protected function slotRules(): array
    {
        return [
            // An empty array is valid: it clears the practitioner's schedule.
            'slots' => ['present', 'array'],
            'slots.*.day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'slots.*.start_time' => ['required', 'date_format:H:i'],
            'slots.*.end_time' => ['required', 'date_format:H:i', 'after:slots.*.start_time'],
        ];
    }

    /**
     * Rejects two windows sharing a day_of_week whose ranges intersect
     * (start_a < end_b AND end_a > start_b). Touching windows
     * (12:00-14:00 then 14:00-18:00) are allowed. H:i strings compare
     * correctly as plain strings.
     */
    protected function validateNoOverlappingSlots(Validator $validator): void
    {
        $slots = $this->input('slots', []);

        if (! is_array($slots)) {
            return;
        }

        $slots = array_values($slots);
        $count = count($slots);

        for ($a = 0; $a < $count; $a++) {
            for ($b = $a + 1; $b < $count; $b++) {
                $first = $slots[$a];
                $second = $slots[$b];

                if (! is_array($first) || ! is_array($second)
                    || ! isset($first['day_of_week'], $first['start_time'], $first['end_time'], $second['day_of_week'], $second['start_time'], $second['end_time'])
                    || (int) $first['day_of_week'] !== (int) $second['day_of_week']) {
                    continue;
                }

                if ($first['start_time'] < $second['end_time'] && $first['end_time'] > $second['start_time']) {
                    $validator->errors()->add(
                        "slots.{$b}.start_time",
                        __('La plage :second chevauche la plage :first le même jour.', [
                            'first' => "{$first['start_time']}-{$first['end_time']}",
                            'second' => "{$second['start_time']}-{$second['end_time']}",
                        ]),
                    );
                }
            }
        }
    }
}
