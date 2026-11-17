<?php

namespace App\Domains\Core\Support;

/**
 * Functional modules that can be switched on per center and narrowed per
 * user. A center (or user) with `modules = null` has everything.
 * Enforced by the `module:<key>` route middleware and mirrored in the nav.
 */
class Modules
{
    public const PATIENTS = 'patients';

    public const TREATMENTS = 'treatments';

    public const SCHEDULING = 'scheduling';

    public const PRACTITIONERS = 'practitioners';

    public const BILLING = 'billing';

    /** @return array<int, string> */
    public static function all(): array
    {
        return [self::PATIENTS, self::TREATMENTS, self::SCHEDULING, self::PRACTITIONERS, self::BILLING];
    }

    /**
     * A module that needs another one to make sense (a treatment or an
     * invoice always belongs to a patient).
     *
     * @return array<string, array<int, string>>
     */
    public static function dependencies(): array
    {
        return [
            self::TREATMENTS => [self::PATIENTS],
            self::BILLING => [self::PATIENTS],
        ];
    }

    /**
     * Keeps only known keys and adds any missing dependency.
     *
     * @param  array<int, mixed>|null  $modules
     * @return array<int, string>|null
     */
    public static function normalize(?array $modules): ?array
    {
        if ($modules === null) {
            return null;
        }

        $modules = array_values(array_intersect(self::all(), $modules));

        foreach (self::dependencies() as $module => $required) {
            if (in_array($module, $modules, true)) {
                $modules = array_merge($modules, $required);
            }
        }

        return array_values(array_intersect(self::all(), array_unique($modules)));
    }
}
