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
            ['code' => '01', 'name' => ['fr' => 'France', 'en' => 'France', 'ar' => 'فرنسا'], 'zone_code' => 'EUROPE'],
            ['code' => '02', 'name' => ['fr' => 'Belgique', 'en' => 'Belgium', 'ar' => 'بلجيكا'], 'zone_code' => 'EUROPE'],
            ['code' => '03', 'name' => ['fr' => 'Suisse', 'en' => 'Switzerland', 'ar' => 'سويسرا'], 'zone_code' => 'EUROPE'],
            ['code' => '04', 'name' => ['fr' => 'Côte d’Ivoire', 'en' => 'Ivory Coast', 'ar' => 'ساحل العاج'], 'zone_code' => 'CEDEAO'],
            ['code' => '05', 'name' => ['fr' => 'Niger', 'en' => 'Niger', 'ar' => 'النيجر'], 'zone_code' => 'CEDEAO'],
            ['code' => '06', 'name' => ['fr' => 'Burkina', 'en' => 'Burkina Faso', 'ar' => 'بوركينا فاسو'], 'zone_code' => 'CEDEAO'],
            ['code' => '07', 'name' => ['fr' => 'Togo', 'en' => 'Togo', 'ar' => 'توغو'], 'zone_code' => 'CEDEAO'],
            ['code' => '08', 'name' => ['fr' => 'Bénin', 'en' => 'Benin', 'ar' => 'بنين'], 'zone_code' => 'CEDEAO'],
            ['code' => '09', 'name' => ['fr' => 'Sénégal', 'en' => 'Senegal', 'ar' => 'السنغال'], 'zone_code' => 'CEDEAO'],
            ['code' => '10', 'name' => ['fr' => 'Nigéria', 'en' => 'Nigeria', 'ar' => 'نيجيريا'], 'zone_code' => 'CEDEAO'],
            ['code' => '11', 'name' => ['fr' => 'Cameroun', 'en' => 'Cameroon', 'ar' => 'الكاميرون'], 'zone_code' => 'AFRIQUE_CENTRALE'],
            ['code' => '12', 'name' => ['fr' => 'Tchad', 'en' => 'Chad', 'ar' => 'تشاد'], 'zone_code' => 'AFRIQUE_CENTRALE'],
            ['code' => '13', 'name' => ['fr' => 'Mali', 'en' => 'Mali', 'ar' => 'مالي'], 'zone_code' => 'CEDEAO'],
            ['code' => '14', 'name' => ['fr' => 'Guinée Conakry', 'en' => 'Guinea-Conakry', 'ar' => 'غينيا كوناكري'], 'zone_code' => 'CEDEAO'],
            ['code' => '15', 'name' => ['fr' => 'Réunion', 'en' => 'Réunion', 'ar' => 'لا ريونيون'], 'zone_code' => null],
            ['code' => '16', 'name' => ['fr' => 'Maurice', 'en' => 'Mauritius', 'ar' => 'موريشيوس'], 'zone_code' => null],
            ['code' => '17', 'name' => ['fr' => 'Portugal', 'en' => 'Portugal', 'ar' => 'البرتغال'], 'zone_code' => 'EUROPE'],
            ['code' => '18', 'name' => ['fr' => 'Tunisie', 'en' => 'Tunisia', 'ar' => 'تونس'], 'zone_code' => 'MAGHREB'],
            ['code' => '19', 'name' => ['fr' => 'Angleterre', 'en' => 'England', 'ar' => 'إنجلترا'], 'zone_code' => 'EUROPE'],
            ['code' => '20', 'name' => ['fr' => 'Maroc', 'en' => 'Morocco', 'ar' => 'المغرب'], 'zone_code' => 'MAGHREB'],
            ['code' => '21', 'name' => ['fr' => 'Algérie', 'en' => 'Algeria', 'ar' => 'الجزائر'], 'zone_code' => 'MAGHREB'],
            ['code' => '22', 'name' => ['fr' => 'Afrique du Sud', 'en' => 'South Africa', 'ar' => 'جنوب أفريقيا'], 'zone_code' => 'AFRIQUE_AUSTRALE'],
            ['code' => '23', 'name' => ['fr' => 'Ghana', 'en' => 'Ghana', 'ar' => 'غانا'], 'zone_code' => 'CEDEAO'],
            ['code' => '24', 'name' => ['fr' => 'Gambie', 'en' => 'Gambia', 'ar' => 'غامبيا'], 'zone_code' => 'CEDEAO'],
            ['code' => '25', 'name' => ['fr' => 'Trinidad', 'en' => 'Trinidad', 'ar' => 'ترينيداد'], 'zone_code' => 'AMERIQUE_SUD'],
            ['code' => '26', 'name' => ['fr' => 'Allemagne', 'en' => 'Germany', 'ar' => 'ألمانيا'], 'zone_code' => 'EUROPE'],
            ['code' => '27', 'name' => ['fr' => 'Macédoine', 'en' => 'North Macedonia', 'ar' => 'مقدونيا الشمالية'], 'zone_code' => 'EUROPE'],
            ['code' => '28', 'name' => ['fr' => 'Angola', 'en' => 'Angola', 'ar' => 'أنغولا'], 'zone_code' => null],
            ['code' => '29', 'name' => ['fr' => 'Congo Brazzaville', 'en' => 'Congo-Brazzaville', 'ar' => 'الكونغو برازافيل'], 'zone_code' => 'AFRIQUE_CENTRALE'],
            ['code' => '30', 'name' => ['fr' => 'Congo Kinshasa', 'en' => 'Congo-Kinshasa', 'ar' => 'الكونغو كينشاسا'], 'zone_code' => null],
            ['code' => '31', 'name' => ['fr' => 'Gabon', 'en' => 'Gabon', 'ar' => 'الغابون'], 'zone_code' => null],
            ['code' => '32', 'name' => ['fr' => 'Guinée Bissau', 'en' => 'Guinea-Bissau', 'ar' => 'غينيا بيساو'], 'zone_code' => 'CEDEAO'],
            ['code' => '33', 'name' => ['fr' => 'Madagascar', 'en' => 'Madagascar', 'ar' => 'مدغشقر'], 'zone_code' => null],
            ['code' => '34', 'name' => ['fr' => 'Mauritanie', 'en' => 'Mauritania', 'ar' => 'موريتانيا'], 'zone_code' => null],
            ['code' => '35', 'name' => ['fr' => 'Ouganda', 'en' => 'Uganda', 'ar' => 'أوغندا'], 'zone_code' => null],
            ['code' => '36', 'name' => ['fr' => 'Djibouti', 'en' => 'Djibouti', 'ar' => 'جيبوتي'], 'zone_code' => 'AFRIQUE_EST'],
            ['code' => '37', 'name' => ['fr' => 'Canada', 'en' => 'Canada', 'ar' => 'كندا'], 'zone_code' => 'AMERIQUE_NORD'],
            ['code' => '38', 'name' => ['fr' => 'USA', 'en' => 'USA', 'ar' => 'الولايات المتحدة'], 'zone_code' => 'AMERIQUE_NORD'],
            ['code' => '39', 'name' => ['fr' => 'Inde', 'en' => 'India', 'ar' => 'الهند'], 'zone_code' => 'ASIE'],
            ['code' => '40', 'name' => ['fr' => 'Indonésie', 'en' => 'Indonesia', 'ar' => 'إندونيسيا'], 'zone_code' => 'ASIE'],
            ['code' => '41', 'name' => ['fr' => 'Pakistan', 'en' => 'Pakistan', 'ar' => 'باكستان'], 'zone_code' => 'ASIE'],
            ['code' => '42', 'name' => ['fr' => 'Malaisie', 'en' => 'Malaysia', 'ar' => 'ماليزيا'], 'zone_code' => 'ASIE'],
            ['code' => '43', 'name' => ['fr' => 'Australie', 'en' => 'Australia', 'ar' => 'أستراليا'], 'zone_code' => 'ASIE'],
            ['code' => '44', 'name' => ['fr' => 'Oman', 'en' => 'Oman', 'ar' => 'عُمان'], 'zone_code' => 'ASIE'],
            ['code' => '45', 'name' => ['fr' => 'Mayotte', 'en' => 'Mayotte', 'ar' => 'مايوت'], 'zone_code' => null],
            ['code' => '46', 'name' => ['fr' => 'Surinam', 'en' => 'Suriname', 'ar' => 'سورينام'], 'zone_code' => 'AMERIQUE_SUD'],
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
