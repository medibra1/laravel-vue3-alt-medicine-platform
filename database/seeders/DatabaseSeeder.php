<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Order matters: EnumOptionSeeder must run before
     * DiseaseCategorySeeder (which references the ILLNESS/BLOCKAGE
     * types), ZoneSeeder before CountrySeeder (which references zones).
     */
    public function run(): void
    {
        $this->call([
            EnumOptionSeeder::class,
            ZoneSeeder::class,
            CountrySeeder::class,
            GradeSeeder::class,
            DiseaseCategorySeeder::class,
            CareCategorySeeder::class,
            RolesAndPermissionsSeeder::class,
            SuperAdminSeeder::class,
        ]);
    }
}
