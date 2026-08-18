<?php

namespace Database\Seeders;

use App\Domains\Core\Models\Zone;
use Illuminate\Database\Seeder;

class ZoneSeeder extends Seeder
{
    /**
     * Zones as defined by the user (source document + scoping
     * discussion) — used for stats aggregated by zone.
     */
    public function run(): void
    {
        $zones = [
            ['code' => 'MAGHREB', 'name' => ['fr' => 'Maghreb', 'en' => 'Maghreb'], 'order' => 1],
            ['code' => 'CEDEAO', 'name' => ['fr' => 'CEDEAO (Afrique de l\'Ouest)', 'en' => 'ECOWAS (West Africa)'], 'order' => 2],
            ['code' => 'AFRIQUE_CENTRALE', 'name' => ['fr' => 'Afrique centrale', 'en' => 'Central Africa'], 'order' => 3],
            ['code' => 'AFRIQUE_EST', 'name' => ['fr' => 'Afrique de l\'Est', 'en' => 'East Africa'], 'order' => 4],
            ['code' => 'AFRIQUE_AUSTRALE', 'name' => ['fr' => 'Afrique australe', 'en' => 'Southern Africa'], 'order' => 5],
            ['code' => 'EUROPE', 'name' => ['fr' => 'Europe', 'en' => 'Europe'], 'order' => 6],
            ['code' => 'ASIE', 'name' => ['fr' => 'Asie (y compris Australie)', 'en' => 'Asia (including Australia)'], 'order' => 7],
            ['code' => 'AMERIQUE_NORD', 'name' => ['fr' => 'Amérique du Nord', 'en' => 'North America'], 'order' => 8],
            ['code' => 'AMERIQUE_SUD', 'name' => ['fr' => 'Amérique centrale et sud', 'en' => 'Central & South America'], 'order' => 9],
        ];

        foreach ($zones as $zone) {
            Zone::query()->firstOrCreate(['code' => $zone['code']], $zone);
        }
    }
}
