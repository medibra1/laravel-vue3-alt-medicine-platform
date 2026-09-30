<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Order matters: EnumOptionSeeder must run before
     * DiseaseCategorySeeder (which references the ILLNESS/BLOCKAGE
     * types), ZoneSeeder before CountrySeeder (which references zones).
     * DemoDataSeeder (demo centers, practitioners, patients,
     * appointments, consents...) is dev/demo content only — never in
     * production, same convention as the Geneva Bengal project.
     */
    public function run(): void
    {
        $this->call([
            EnumOptionSeeder::class,
            SessionMeasurementTypesSeeder::class,
            ZoneSeeder::class,
            CountrySeeder::class,
            GradeSeeder::class,
            DiseaseCategorySeeder::class,
            CareCategorySeeder::class,
            RolesAndPermissionsSeeder::class,
            SuperAdminSeeder::class,
        ]);

        if (! app()->isProduction()) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
