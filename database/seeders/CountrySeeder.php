<?php

namespace Database\Seeders;

use App\Domains\Core\Models\Country;
use App\Domains\Core\Models\Zone;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    /**
     * The 46 countries and their codes, as provided by the user (source
     * document). Zone assignment is done ONLY for countries explicitly
     * named in the source text under a given zone — the rest are left
     * with zone_id = null rather than guessed (e.g. Réunion/Maurice:
     * Indian Ocean islands, zone not specified; Mauritania: sits at the
     * Maghreb/ECOWAS border, ambiguous; Angola/DR Congo/Gabon/
     * Madagascar/Uganda: not explicitly cited in the zone examples
     * given). To be settled with the user before production.
     */
    public function run(): void
    {
        $countries = [
            ['code' => '01', 'name' => ['fr' => 'France', 'en' => 'France'], 'zone_code' => 'EUROPE'],
            ['code' => '02', 'name' => ['fr' => 'Belgique', 'en' => 'Belgium'], 'zone_code' => 'EUROPE'],
            ['code' => '03', 'name' => ['fr' => 'Suisse', 'en' => 'Switzerland'], 'zone_code' => 'EUROPE'],
            ['code' => '04', 'name' => ['fr' => 'Côte d’Ivoire', 'en' => 'Ivory Coast'], 'zone_code' => 'CEDEAO'],
            ['code' => '05', 'name' => ['fr' => 'Niger', 'en' => 'Niger'], 'zone_code' => 'CEDEAO'],
            ['code' => '06', 'name' => ['fr' => 'Burkina', 'en' => 'Burkina Faso'], 'zone_code' => 'CEDEAO'],
            ['code' => '07', 'name' => ['fr' => 'Togo', 'en' => 'Togo'], 'zone_code' => 'CEDEAO'],
            ['code' => '08', 'name' => ['fr' => 'Bénin', 'en' => 'Benin'], 'zone_code' => 'CEDEAO'],
            ['code' => '09', 'name' => ['fr' => 'Sénégal', 'en' => 'Senegal'], 'zone_code' => 'CEDEAO'],
            ['code' => '10', 'name' => ['fr' => 'Nigéria', 'en' => 'Nigeria'], 'zone_code' => 'CEDEAO'],
            ['code' => '11', 'name' => ['fr' => 'Cameroun', 'en' => 'Cameroon'], 'zone_code' => 'AFRIQUE_CENTRALE'],
            ['code' => '12', 'name' => ['fr' => 'Tchad', 'en' => 'Chad'], 'zone_code' => 'AFRIQUE_CENTRALE'],
            ['code' => '13', 'name' => ['fr' => 'Mali', 'en' => 'Mali'], 'zone_code' => 'CEDEAO'],
            ['code' => '14', 'name' => ['fr' => 'Guinée Conakry', 'en' => 'Guinea-Conakry'], 'zone_code' => 'CEDEAO'],
            ['code' => '15', 'name' => ['fr' => 'Réunion', 'en' => 'Réunion'], 'zone_code' => null],
            ['code' => '16', 'name' => ['fr' => 'Maurice', 'en' => 'Mauritius'], 'zone_code' => null],
            ['code' => '17', 'name' => ['fr' => 'Portugal', 'en' => 'Portugal'], 'zone_code' => 'EUROPE'],
            ['code' => '18', 'name' => ['fr' => 'Tunisie', 'en' => 'Tunisia'], 'zone_code' => 'MAGHREB'],
            ['code' => '19', 'name' => ['fr' => 'Angleterre', 'en' => 'England'], 'zone_code' => 'EUROPE'],
            ['code' => '20', 'name' => ['fr' => 'Maroc', 'en' => 'Morocco'], 'zone_code' => 'MAGHREB'],
            ['code' => '21', 'name' => ['fr' => 'Algérie', 'en' => 'Algeria'], 'zone_code' => 'MAGHREB'],
            ['code' => '22', 'name' => ['fr' => 'Afrique du Sud', 'en' => 'South Africa'], 'zone_code' => 'AFRIQUE_AUSTRALE'],
            ['code' => '23', 'name' => ['fr' => 'Ghana', 'en' => 'Ghana'], 'zone_code' => 'CEDEAO'],
            ['code' => '24', 'name' => ['fr' => 'Gambie', 'en' => 'Gambia'], 'zone_code' => 'CEDEAO'],
            ['code' => '25', 'name' => ['fr' => 'Trinidad', 'en' => 'Trinidad'], 'zone_code' => 'AMERIQUE_SUD'],
            ['code' => '26', 'name' => ['fr' => 'Allemagne', 'en' => 'Germany'], 'zone_code' => 'EUROPE'],
            ['code' => '27', 'name' => ['fr' => 'Macédoine', 'en' => 'North Macedonia'], 'zone_code' => 'EUROPE'],
            ['code' => '28', 'name' => ['fr' => 'Angola', 'en' => 'Angola'], 'zone_code' => null],
            ['code' => '29', 'name' => ['fr' => 'Congo Brazzaville', 'en' => 'Congo-Brazzaville'], 'zone_code' => 'AFRIQUE_CENTRALE'],
            ['code' => '30', 'name' => ['fr' => 'Congo Kinshasa', 'en' => 'Congo-Kinshasa'], 'zone_code' => null],
            ['code' => '31', 'name' => ['fr' => 'Gabon', 'en' => 'Gabon'], 'zone_code' => null],
            ['code' => '32', 'name' => ['fr' => 'Guinée Bissau', 'en' => 'Guinea-Bissau'], 'zone_code' => 'CEDEAO'],
            ['code' => '33', 'name' => ['fr' => 'Madagascar', 'en' => 'Madagascar'], 'zone_code' => null],
            ['code' => '34', 'name' => ['fr' => 'Mauritanie', 'en' => 'Mauritania'], 'zone_code' => null],
            ['code' => '35', 'name' => ['fr' => 'Ouganda', 'en' => 'Uganda'], 'zone_code' => null],
            ['code' => '36', 'name' => ['fr' => 'Djibouti', 'en' => 'Djibouti'], 'zone_code' => 'AFRIQUE_EST'],
            ['code' => '37', 'name' => ['fr' => 'Canada', 'en' => 'Canada'], 'zone_code' => 'AMERIQUE_NORD'],
            ['code' => '38', 'name' => ['fr' => 'USA', 'en' => 'USA'], 'zone_code' => 'AMERIQUE_NORD'],
            ['code' => '39', 'name' => ['fr' => 'Inde', 'en' => 'India'], 'zone_code' => 'ASIE'],
            ['code' => '40', 'name' => ['fr' => 'Indonésie', 'en' => 'Indonesia'], 'zone_code' => 'ASIE'],
            ['code' => '41', 'name' => ['fr' => 'Pakistan', 'en' => 'Pakistan'], 'zone_code' => 'ASIE'],
            ['code' => '42', 'name' => ['fr' => 'Malaisie', 'en' => 'Malaysia'], 'zone_code' => 'ASIE'],
            ['code' => '43', 'name' => ['fr' => 'Australie', 'en' => 'Australia'], 'zone_code' => 'ASIE'],
            ['code' => '44', 'name' => ['fr' => 'Oman', 'en' => 'Oman'], 'zone_code' => 'ASIE'],
            ['code' => '45', 'name' => ['fr' => 'Mayotte', 'en' => 'Mayotte'], 'zone_code' => null],
            ['code' => '46', 'name' => ['fr' => 'Surinam', 'en' => 'Suriname'], 'zone_code' => 'AMERIQUE_SUD'],
        ];

        foreach ($countries as $country) {
            $zone = $country['zone_code']
                ? Zone::query()->where('code', $country['zone_code'])->first()
                : null;

            Country::query()->firstOrCreate(
                ['code' => $country['code']],
                [
                    'name' => $country['name'],
                    'zone_id' => $zone?->id,
                    'active' => true,
                ]
            );
        }
    }
}
