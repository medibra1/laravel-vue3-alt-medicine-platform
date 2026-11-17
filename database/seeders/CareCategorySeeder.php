<?php

namespace Database\Seeders;

use App\Domains\Patients\Models\CareCategory;
use App\Domains\Patients\Models\CareItem;
use Illuminate\Database\Seeder;

class CareCategorySeeder extends Seeder
{
    /**
     * Care/remedy catalog used at TreatmentSession level (what was
     * administered during a session) — hand-rolled tables mirroring
     * DiseaseCategory/Disease rather than EnumOption.parent_id, per the
     * decision documented in CLAUDE.md "Wizard Treatment..." (parent_id
     * has zero real usage anywhere in the codebase).
     *
     * The "cupping" category (Ventouses), is real content — a list
     * of body zones provided by the user (2026-08-24), not a placeholder.
     *
     * "Verset" (Quranic verse) is a translatable label/content value,
     * not a code/table/column identifier — consistent with the project's
     * terminology policy (see CLAUDE.md "Terminologie"), which targets
     * how entities are NAMED in code/schema, not user-facing seeded
     * content (disease 408 "Blocage religion" is existing precedent).
     *
     * "Verset à ajouter" items (2026-08-24, category renamed from
     * "Verset" the same day) — real content, 46 verses provided by the
     * user, one per symbol from the "Symboles" disease category (see
     * DiseaseCategorySeeder). Label format: "S<sourate> v<verset(s)>
     * (Symbole)", with the source document's own qualifier kept verbatim
     * when a verse is a partial quote — "jusqu'à <mot arabe>" (until),
     * "à partir de <mot arabe>" (from), or "de <mot> à/jusqu'à <mot>"
     * (from...to) — this is the practitioner's precise reference for
     * where to start/stop reciting, not decorative. Item 046 ("Tuer
     * djinns et sorciers", S55 v33-35) is the source document's last
     * entry — its French text is itself cut off mid-sentence in the
     * source ("il sera lan…"), so the qualifier is omitted here (nothing
     * reliable to extract), not lost in translation. Repetition counts
     * from the source ("deux fois"/"trois fois", on items 007/018/037/
     * 038/041/043) were dropped from the label per user request
     * (2026-08-24) — the qualifier word remains the practitioner's
     * actual reference point.
     *
     * Catalog reorganized 2026-08-24 (same day, later in the session):
     * the 4 former placeholder categories (ointment/Pommade, bath/Bain,
     * incense/Encens, tea/Tisane — never had real content) were dropped
     * entirely, replaced by two new categories with real content
     * provided by the user — "Générale" (8 items: the practice's main
     * remedy types, one of which, "Encens", is itself a former category
     * name now folded into this flat list) and "Autres soins et
     * produits" (a flat catalog of specific products/water
     * preparations/oils/incense variants). The source list visually
     * grouped these by sub-theme (waters, oils, incense...) but
     * CareCategory/CareItem is a fixed 2-level hierarchy (no
     * sub-category level exists in the schema) — seeded as one flat
     * category rather than introducing a 3rd level, confirmed with the
     * user before seeding. Source lines using comma-separated
     * enumeration (e.g. "Lecture de groupe sur patient, Lecture
     * individuelle sur patient, Eau coranisée") were split into one
     * CareItem per comma-separated term, per user decision — confirmed
     * explicitly before seeding, not assumed.
     *
     * "Autres soins et produits" de-duplicated against "Générale"
     * (2026-08-25, per user request): 4 items removed — "Eau coranisée"
     * (redundant with Générale's "Eau / Feuille coranisée à diluer"),
     * "Huile" and "Pommade" (both redundant with Générale's "Huile /
     * pommade"), and "Lecture de groupe sur patient" (dropped outright,
     * not folded elsewhere — the user asked to remove it, not rename
     * it). "Lecture individuelle sur patient" renamed to "Lecture
     * individuelle" (dropped "sur patient" per user request). Codes
     * renumbered contiguously after the removals (was 41 items, now
     * 37) — this category has no other consumer keying off a specific
     * code (unlike e.g. disease codes 101+, which are stable
     * identifiers elsewhere), so renumbering is safe. Checked the rest
     * of the list for further overlap with Générale's 8 broad types —
     * none found: "Autres huiles / produits de massage" and "Autres
     * encens" are catch-alls distinct from Générale's "Huile / pommade"
     * / "Encens", not duplicates.
     */
    public function run(): void
    {
        $categories = [
            ['code' => 'general', 'label' => ['fr' => 'Générale', 'en' => 'General', 'ar' => 'عامة'], 'order' => 1, 'items' => [
                ['code' => '001', 'label' => ['fr' => 'Lecture', 'en' => 'Recitation', 'ar' => 'قراءة']],
                ['code' => '002', 'label' => ['fr' => 'Eau / Feuille coranisée à diluer', 'en' => 'Water / Quran-charged leaf to dilute', 'ar' => 'ماء / ورقة مقروء عليها للتخفيف']],
                ['code' => '003', 'label' => ['fr' => 'Huile / pommade', 'en' => 'Oil / ointment', 'ar' => 'زيت / مرهم']],
                ['code' => '004', 'label' => ['fr' => 'Encens', 'en' => 'Incense', 'ar' => 'بخور']],
                ['code' => '005', 'label' => ['fr' => 'Tisane', 'en' => 'Herbal tea', 'ar' => 'منقوع أعشاب']],
                ['code' => '006', 'label' => ['fr' => 'Captage', 'en' => 'Capture', 'ar' => 'استحضار']],
                ['code' => '007', 'label' => ['fr' => 'Psychothérapie', 'en' => 'Psychotherapy', 'ar' => 'علاج نفسي']],
                ['code' => '008', 'label' => ['fr' => 'Prière de malédiction', 'en' => 'Curse prayer', 'ar' => 'دعاء على المعتدي']],
            ]],
            ['code' => 'other_care_products', 'label' => ['fr' => 'Autres soins et produits', 'en' => 'Other care and products', 'ar' => 'علاجات ومنتجات أخرى'], 'order' => 2, 'items' => [
                ['code' => '001', 'label' => ['fr' => 'Savon', 'en' => 'Soap', 'ar' => 'صابون']],
                ['code' => '002', 'label' => ['fr' => 'Kohl', 'en' => 'Kohl', 'ar' => 'كحل']],
                ['code' => '003', 'label' => ['fr' => 'Habbat sawda en grain', 'en' => 'Black seed grain', 'ar' => 'حبة سوداء (حبوب)']],
                ['code' => '004', 'label' => ['fr' => 'Sidr', 'en' => 'Sidr', 'ar' => 'سدر']],
                ['code' => '005', 'label' => ['fr' => 'Costus', 'en' => 'Costus', 'ar' => 'قسط']],
                ['code' => '006', 'label' => ['fr' => 'Fenugrec', 'en' => 'Fenugreek', 'ar' => 'حلبة']],
                ['code' => '007', 'label' => ['fr' => 'Camomille', 'en' => 'Chamomile', 'ar' => 'بابونج']],
                ['code' => '008', 'label' => ['fr' => 'Thym', 'en' => 'Thyme', 'ar' => 'زعتر']],
                ['code' => '009', 'label' => ['fr' => 'Autres plantes / mélanges', 'en' => 'Other plants / blends', 'ar' => 'نباتات / خلطات أخرى']],
                ['code' => '010', 'label' => ['fr' => 'Invocation / Dou\'a', 'en' => 'Invocation / Dua', 'ar' => 'دعاء']],
                ['code' => '011', 'label' => ['fr' => 'Dhikr', 'en' => 'Dhikr', 'ar' => 'ذكر']],
                ['code' => '012', 'label' => ['fr' => 'Auto-captage', 'en' => 'Self-capture', 'ar' => 'استحضار ذاتي']],
                ['code' => '013', 'label' => ['fr' => 'Remontage', 'en' => 'Re-assembly', 'ar' => 'إعادة التركيب']],
                ['code' => '014', 'label' => ['fr' => 'Traitement du local', 'en' => 'Premises treatment', 'ar' => 'معالجة المكان']],
                ['code' => '015', 'label' => ['fr' => 'Autres soins', 'en' => 'Other care', 'ar' => 'علاجات أخرى']],
                ['code' => '016', 'label' => ['fr' => 'Lecture individuelle', 'en' => 'Individual recitation', 'ar' => 'قراءة فردية']],
                ['code' => '017', 'label' => ['fr' => 'Eau de Zamzam', 'en' => 'Zamzam water', 'ar' => 'ماء زمزم']],
                ['code' => '018', 'label' => ['fr' => 'Eau avec Sidr', 'en' => 'Water with sidr', 'ar' => 'ماء بالسدر']],
                ['code' => '019', 'label' => ['fr' => 'Eau salée', 'en' => 'Salt water', 'ar' => 'ماء مالح']],
                ['code' => '020', 'label' => ['fr' => 'Autres préparations à base d\'eau', 'en' => 'Other water-based preparations', 'ar' => 'مستحضرات مائية أخرى']],
                ['code' => '021', 'label' => ['fr' => 'Huile de Habbat sawda', 'en' => 'Black seed oil', 'ar' => 'زيت الحبة السوداء']],
                ['code' => '022', 'label' => ['fr' => 'Huile d\'olive', 'en' => 'Olive oil', 'ar' => 'زيت الزيتون']],
                ['code' => '023', 'label' => ['fr' => 'Huile de costus', 'en' => 'Costus oil', 'ar' => 'زيت القسط']],
                ['code' => '024', 'label' => ['fr' => 'Huile de sidr', 'en' => 'Sidr oil', 'ar' => 'زيت السدر']],
                ['code' => '025', 'label' => ['fr' => 'Huile de ricin', 'en' => 'Castor oil', 'ar' => 'زيت الخروع']],
                ['code' => '026', 'label' => ['fr' => 'Huile de coco', 'en' => 'Coconut oil', 'ar' => 'زيت جوز الهند']],
                ['code' => '027', 'label' => ['fr' => 'Huile d\'argan', 'en' => 'Argan oil', 'ar' => 'زيت الأركان']],
                ['code' => '028', 'label' => ['fr' => 'Huile de sésame', 'en' => 'Sesame oil', 'ar' => 'زيت السمسم']],
                ['code' => '029', 'label' => ['fr' => 'Huile d\'amande', 'en' => 'Almond oil', 'ar' => 'زيت اللوز']],
                ['code' => '030', 'label' => ['fr' => 'Beurre de karité', 'en' => 'Shea butter', 'ar' => 'زبدة الشيا']],
                ['code' => '031', 'label' => ['fr' => 'Crème', 'en' => 'Cream', 'ar' => 'كريم']],
                ['code' => '032', 'label' => ['fr' => 'Baume', 'en' => 'Balm', 'ar' => 'بلسم']],
                ['code' => '033', 'label' => ['fr' => 'Massage', 'en' => 'Massage', 'ar' => 'تدليك']],
                ['code' => '034', 'label' => ['fr' => 'Autres huiles / produits de massage', 'en' => 'Other oils / massage products', 'ar' => 'زيوت / منتجات تدليك أخرى']],
                ['code' => '035', 'label' => ['fr' => 'Musk', 'en' => 'Musk', 'ar' => 'مسك']],
                ['code' => '036', 'label' => ['fr' => 'Haltite', 'en' => 'Haltite', 'ar' => 'حلتيت']],
                ['code' => '037', 'label' => ['fr' => 'Autres encens', 'en' => 'Other incense', 'ar' => 'أنواع بخور أخرى']],
            ]],
            ['code' => 'verse', 'label' => ['fr' => 'Verset à ajouter', 'en' => 'Verse to add', 'ar' => 'آية تُضاف'], 'order' => 3, 'items' => [
                ['code' => '001', 'label' => ['fr' => 'S21 v30 (Cadenas)', 'en' => 'S21 v30 (Padlock)', 'ar' => 'سورة 21 آية 30 (قفل)']],
                ['code' => '002', 'label' => ['fr' => 'S20 v26-28 (Fil avec 11 nœuds)', 'en' => 'S20 v26-28 (Thread with 11 knots)', 'ar' => 'سورة 20 الآيات 26-28 (خيط بإحدى عشرة عقدة)']],
                ['code' => '003', 'label' => ['fr' => 'S26 v63 (Rivière)', 'en' => 'S26 v63 (River)', 'ar' => 'سورة 26 آية 63 (نهر)']],
                ['code' => '004', 'label' => ['fr' => 'S6 v59 jusqu\'à ya\'lamouha (Arbre)', 'en' => 'S6 v59 until ya\'lamouha (Tree)', 'ar' => 'سورة 6 آية 59 إلى «يعلمها» (شجرة)']],
                ['code' => '005', 'label' => ['fr' => 'S22 v31 à partir de waman yochrik (Trou, pont ou puits)', 'en' => 'S22 v31 from waman yochrik (Hole, bridge or well)', 'ar' => 'سورة 22 آية 31 من «ومن يشرك» (حفرة أو جسر أو بئر)']],
                ['code' => '006', 'label' => ['fr' => 'S6 v122 jusqu\'à bikharijin minha (Cimetière)', 'en' => 'S6 v122 until bikharijin minha (Cemetery)', 'ar' => 'سورة 6 آية 122 إلى «بخارج منها» (مقبرة)']],
                ['code' => '007', 'label' => ['fr' => 'S38 v42 (Chaussure, terre + pas)', 'en' => 'S38 v42 (Shoe, soil + footprint)', 'ar' => 'سورة 38 آية 42 (حذاء، تراب + أثر قدم)']],
                ['code' => '008', 'label' => ['fr' => 'S7 v26 jusqu\'à khayr (Habit)', 'en' => 'S7 v26 until khayr (Garment)', 'ar' => 'سورة 7 آية 26 إلى «خير» (ثوب)']],
                ['code' => '009', 'label' => ['fr' => 'S7 v11 jusqu\'à fasajadou (Photo)', 'en' => 'S7 v11 until fasajadou (Photo)', 'ar' => 'سورة 7 آية 11 إلى «فسجدوا» (صورة)']],
                ['code' => '010', 'label' => ['fr' => 'S19 v4 à partir de inni wahana (Cheveux)', 'en' => 'S19 v4 from inni wahana (Hair)', 'ar' => 'سورة 19 آية 4 من «إني وهن» (شعر)']],
                ['code' => '011', 'label' => ['fr' => 'S2 v102 de fayata\'llamouna jusqu\'à bi\'idhni llah (Lettre)', 'en' => 'S2 v102 from fayata\'llamouna until bi\'idhni llah (Letter)', 'ar' => 'سورة 2 آية 102 من «فيتعلمون» إلى «بإذن الله» (رسالة)']],
                ['code' => '012', 'label' => ['fr' => 'S3 v106 jusqu\'à imanikom (Charbon)', 'en' => 'S3 v106 until imanikom (Coal)', 'ar' => 'سورة 3 آية 106 إلى «إيمانكم» (فحم)']],
                ['code' => '013', 'label' => ['fr' => 'S5 v64 depuis kollama awqadou (Braise)', 'en' => 'S5 v64 from kollama awqadou (Ember)', 'ar' => 'سورة 5 آية 64 من «كلما أوقدوا» (جمر)']],
                ['code' => '014', 'label' => ['fr' => 'S5 v89 jusqu\'à alayman (Étoile ou trombone)', 'en' => 'S5 v89 until alayman (Star or paperclip)', 'ar' => 'سورة 5 آية 89 إلى «الأيمان» (نجمة أو مشبك ورق)']],
                ['code' => '015', 'label' => ['fr' => 'S25 v23 (Canari)', 'en' => 'S25 v23 (Clay jar)', 'ar' => 'سورة 25 آية 23 (جرّة فخارية)']],
                ['code' => '016', 'label' => ['fr' => 'S6 v95 jusqu\'à alhayy (Cola coupé)', 'en' => 'S6 v95 until alhayy (Cut kola nut)', 'ar' => 'سورة 6 آية 95 إلى «الحي» (جوز الكولا مقطوع)']],
                ['code' => '017', 'label' => ['fr' => 'S33 v10-11 (Cola ou poupée piquée)', 'en' => 'S33 v10-11 (Kola nut or pierced doll)', 'ar' => 'سورة 33 الآيتان 10-11 (جوز الكولا أو دمية مغروزة)']],
                ['code' => '018', 'label' => ['fr' => 'S42 v37 à partir de wa\'idha (3 clous, colère)', 'en' => 'S42 v37 from wa\'idha (3 nails, anger)', 'ar' => 'سورة 42 آية 37 من «وإذا» (3 مسامير، غضب)']],
                ['code' => '019', 'label' => ['fr' => 'S5 v3 jusqu\'à lighayri llah bihi (Sang des ventouses)', 'en' => 'S5 v3 until lighayri llah bihi (Blood from cupping)', 'ar' => 'سورة 5 آية 3 إلى «لغير الله به» (دم الحجامة)']],
                ['code' => '020', 'label' => ['fr' => 'S2 v222 à partir de fa\'idha tatahharna (Sang des règles)', 'en' => 'S2 v222 from fa\'idha tatahharna (Menstrual blood)', 'ar' => 'سورة 2 آية 222 من «فإذا تطهرن» (دم الحيض)']],
                ['code' => '021', 'label' => ['fr' => 'S5 v3 jusqu\'à fisq (Cadavres d\'animaux)', 'en' => 'S5 v3 until fisq (Animal carcasses)', 'ar' => 'سورة 5 آية 3 إلى «فسق» (جيف حيوانات)']],
                ['code' => '022', 'label' => ['fr' => 'S5 v4 de wa ma \'allamtom jusqu\'à \'allamakoum Allah (Poils de chien)', 'en' => 'S5 v4 from wa ma \'allamtom until \'allamakoum Allah (Dog hair)', 'ar' => 'سورة 5 آية 4 من «وما علمتم» إلى «علمكم الله» (شعر كلب)']],
                ['code' => '023', 'label' => ['fr' => 'S37 v142-144 (Poisson)', 'en' => 'S37 v142-144 (Fish)', 'ar' => 'سورة 37 الآيات 142-144 (سمكة)']],
                ['code' => '024', 'label' => ['fr' => 'S36 v78-79 (3 os)', 'en' => 'S36 v78-79 (3 bones)', 'ar' => 'سورة 36 الآيتان 78-79 (3 عظام)']],
                ['code' => '025', 'label' => ['fr' => 'S74 v3-5 (Caca)', 'en' => 'S74 v3-5 (Excrement)', 'ar' => 'سورة 74 الآيات 3-5 (براز)']],
                ['code' => '026', 'label' => ['fr' => 'S16 v80 à partir de wa min (Peau)', 'en' => 'S16 v80 from wa min (Skin)', 'ar' => 'سورة 16 آية 80 من «ومن» (جلد)']],
                ['code' => '027', 'label' => ['fr' => 'S62 v5 jusqu\'à asfara (Âne)', 'en' => 'S62 v5 until asfara (Donkey)', 'ar' => 'سورة 62 آية 5 إلى «أسفارا» (حمار)']],
                ['code' => '028', 'label' => ['fr' => 'S7 v22 de badat à janna (Slip)', 'en' => 'S7 v22 from badat to janna (Underwear)', 'ar' => 'سورة 7 آية 22 من «بدت» إلى «الجنة» (لباس داخلي)']],
                ['code' => '029', 'label' => ['fr' => 'S86 v5-6 (Poils de pubis)', 'en' => 'S86 v5-6 (Pubic hair)', 'ar' => 'سورة 86 الآيتان 5-6 (شعر العانة)']],
                ['code' => '030', 'label' => ['fr' => 'S56 v52-54 (Piment)', 'en' => 'S56 v52-54 (Chili pepper)', 'ar' => 'سورة 56 الآيات 52-54 (فلفل حار)']],
                ['code' => '031', 'label' => ['fr' => 'S2 v275 jusqu\'à almass (Folie)', 'en' => 'S2 v275 until almass (Madness)', 'ar' => 'سورة 2 آية 275 إلى «المس» (جنون)']],
                ['code' => '032', 'label' => ['fr' => 'S6 v162-163 (Sacrifice)', 'en' => 'S6 v162-163 (Sacrifice)', 'ar' => 'سورة 6 الآيتان 162-163 (ذبيحة)']],
                ['code' => '033', 'label' => ['fr' => 'S12 v42 à partir de fa\'ansahou et S18 v24 de wadhkor (Oubli)', 'en' => 'S12 v42 from fa\'ansahou and S18 v24 from wadhkor (Forgetfulness)', 'ar' => 'سورة 12 آية 42 من «فأنساه» وسورة 18 آية 24 من «واذكر» (نسيان)']],
                ['code' => '034', 'label' => ['fr' => 'S30 v17-19 (Revivification)', 'en' => 'S30 v17-19 (Revivification)', 'ar' => 'سورة 30 الآيات 17-19 (إحياء)']],
                ['code' => '035', 'label' => ['fr' => 'S18 v42 à partir de fa\'asbaha (Perdre l\'argent)', 'en' => 'S18 v42 from fa\'asbaha (Loss of money)', 'ar' => 'سورة 18 آية 42 من «فأصبح» (فقدان المال)']],
                ['code' => '036', 'label' => ['fr' => 'S21 v98 (Statue)', 'en' => 'S21 v98 (Statue)', 'ar' => 'سورة 21 آية 98 (تمثال)']],
                ['code' => '037', 'label' => ['fr' => 'S20 v108 à partir de wakhacha\'ati (Bruits ou bourdonnement)', 'en' => 'S20 v108 from wakhacha\'ati (Noises or buzzing)', 'ar' => 'سورة 20 آية 108 من «وخشعت» (أصوات أو طنين)']],
                ['code' => '038', 'label' => ['fr' => 'S27 v52 jusqu\'à dhalamou (Présences dans la maison)', 'en' => 'S27 v52 until dhalamou (Presences in the house)', 'ar' => 'سورة 27 آية 52 إلى «ظلموا» (حضور في البيت)']],
                ['code' => '039', 'label' => ['fr' => 'S34 v54 (Passions)', 'en' => 'S34 v54 (Passions)', 'ar' => 'سورة 34 آية 54 (شهوات)']],
                ['code' => '040', 'label' => ['fr' => 'S38 v20 (Faiblesse mentale)', 'en' => 'S38 v20 (Mental weakness)', 'ar' => 'سورة 38 آية 20 (ضعف عقلي)']],
                ['code' => '041', 'label' => ['fr' => 'S50 v22 à partir de fakachafna (Maladie des yeux)', 'en' => 'S50 v22 from fakachafna (Eye ailment)', 'ar' => 'سورة 50 آية 22 من «فكشفنا» (مرض العيون)']],
                ['code' => '042', 'label' => ['fr' => 'S51 v47-49 (Stérilité et impuissance)', 'en' => 'S51 v47-49 (Sterility and impotence)', 'ar' => 'سورة 51 الآيات 47-49 (عقم وعجز جنسي)']],
                ['code' => '043', 'label' => ['fr' => 'S76 v13 à partir de la yarawna (Froid)', 'en' => 'S76 v13 from la yarawna (Cold)', 'ar' => 'سورة 76 آية 13 من «لا يرون» (برد)']],
                ['code' => '044', 'label' => ['fr' => 'S34 v14 jusqu\'à minsatah (Termite)', 'en' => 'S34 v14 until minsatah (Termite)', 'ar' => 'سورة 34 آية 14 إلى «منسأته» (أَرَضة)']],
                ['code' => '045', 'label' => ['fr' => 'S106 v4 (Peur)', 'en' => 'S106 v4 (Fear)', 'ar' => 'سورة 106 آية 4 (خوف)']],
                ['code' => '046', 'label' => ['fr' => 'S55 v33-35 (Tuer djinns et sorciers)', 'en' => 'S55 v33-35 (Killing jinns and sorcerers)', 'ar' => 'سورة 55 الآيات 33-35 (قتل الجن والسحرة)']],
            ]],
            ['code' => 'cupping', 'label' => ['fr' => 'Ventouses', 'en' => 'Cupping', 'ar' => 'الحجامة'], 'order' => 4, 'items' => [
                ['code' => '001', 'label' => ['fr' => 'Tête', 'en' => 'Head', 'ar' => 'الرأس']],
                ['code' => '002', 'label' => ['fr' => 'Cuir chevelu', 'en' => 'Scalp', 'ar' => 'فروة الرأس']],
                ['code' => '003', 'label' => ['fr' => 'Front', 'en' => 'Forehead', 'ar' => 'الجبهة']],
                ['code' => '004', 'label' => ['fr' => 'Tempes', 'en' => 'Temples', 'ar' => 'الصدغان']],
                ['code' => '005', 'label' => ['fr' => 'Occiput', 'en' => 'Occiput', 'ar' => 'مؤخرة الرأس']],
                ['code' => '006', 'label' => ['fr' => 'Joues', 'en' => 'Cheeks', 'ar' => 'الخدّان']],
                ['code' => '007', 'label' => ['fr' => 'Nez', 'en' => 'Nose', 'ar' => 'الأنف']],
                ['code' => '008', 'label' => ['fr' => 'Oreilles', 'en' => 'Ears', 'ar' => 'الأذنان']],
                ['code' => '009', 'label' => ['fr' => 'Bouche', 'en' => 'Mouth', 'ar' => 'الفم']],
                ['code' => '010', 'label' => ['fr' => 'Menton', 'en' => 'Chin', 'ar' => 'الذقن']],
                ['code' => '011', 'label' => ['fr' => 'Cou', 'en' => 'Neck', 'ar' => 'الرقبة']],
                ['code' => '012', 'label' => ['fr' => 'Nuque', 'en' => 'Nape', 'ar' => 'القفا']],
                ['code' => '013', 'label' => ['fr' => 'Gorge', 'en' => 'Throat', 'ar' => 'الحلق']],
                ['code' => '014', 'label' => ['fr' => 'Épaules', 'en' => 'Shoulders', 'ar' => 'الكتفان']],
                ['code' => '015', 'label' => ['fr' => 'Trapèzes', 'en' => 'Trapezius', 'ar' => 'العضلة شبه المنحرفة']],
                ['code' => '016', 'label' => ['fr' => 'Bras', 'en' => 'Arms', 'ar' => 'الذراعان']],
                ['code' => '017', 'label' => ['fr' => 'Biceps', 'en' => 'Biceps', 'ar' => 'العضلة ذات الرأسين']],
                ['code' => '018', 'label' => ['fr' => 'Triceps', 'en' => 'Triceps', 'ar' => 'العضلة ثلاثية الرؤوس']],
                ['code' => '019', 'label' => ['fr' => 'Coude', 'en' => 'Elbow', 'ar' => 'المرفق']],
                ['code' => '020', 'label' => ['fr' => 'Avant-bras', 'en' => 'Forearm', 'ar' => 'الساعد']],
                ['code' => '021', 'label' => ['fr' => 'Poignet', 'en' => 'Wrist', 'ar' => 'الرسغ']],
                ['code' => '022', 'label' => ['fr' => 'Main', 'en' => 'Hand', 'ar' => 'اليد']],
                ['code' => '023', 'label' => ['fr' => 'Paume', 'en' => 'Palm', 'ar' => 'راحة اليد']],
                ['code' => '024', 'label' => ['fr' => 'Doigts', 'en' => 'Fingers', 'ar' => 'الأصابع']],
                ['code' => '025', 'label' => ['fr' => 'Torse', 'en' => 'Torso', 'ar' => 'الجذع']],
                ['code' => '026', 'label' => ['fr' => 'Thorax', 'en' => 'Thorax', 'ar' => 'القفص الصدري']],
                ['code' => '027', 'label' => ['fr' => 'Poitrine', 'en' => 'Chest', 'ar' => 'الصدر']],
                ['code' => '028', 'label' => ['fr' => 'Pectoraux', 'en' => 'Pectorals', 'ar' => 'عضلات الصدر']],
                ['code' => '029', 'label' => ['fr' => 'Seins', 'en' => 'Breasts', 'ar' => 'الثديان']],
                ['code' => '030', 'label' => ['fr' => 'Clavicule', 'en' => 'Clavicle', 'ar' => 'الترقوة']],
                ['code' => '031', 'label' => ['fr' => 'Côtes', 'en' => 'Ribs', 'ar' => 'الأضلاع']],
                ['code' => '032', 'label' => ['fr' => 'Cœur', 'en' => 'Heart', 'ar' => 'القلب']],
                ['code' => '033', 'label' => ['fr' => 'Poumons', 'en' => 'Lungs', 'ar' => 'الرئتان']],
                ['code' => '034', 'label' => ['fr' => 'Abdomen', 'en' => 'Abdomen', 'ar' => 'البطن']],
                ['code' => '035', 'label' => ['fr' => 'Ventre', 'en' => 'Belly', 'ar' => 'الكرش']],
                ['code' => '036', 'label' => ['fr' => 'Nombril', 'en' => 'Navel', 'ar' => 'السرّة']],
                ['code' => '037', 'label' => ['fr' => 'Plexus', 'en' => 'Solar plexus', 'ar' => 'الضفيرة الشمسية']],
                ['code' => '038', 'label' => ['fr' => 'Flancs', 'en' => 'Flanks', 'ar' => 'الخاصرتان']],
                ['code' => '039', 'label' => ['fr' => 'Dos', 'en' => 'Back', 'ar' => 'الظهر']],
                ['code' => '040', 'label' => ['fr' => 'Haut du dos', 'en' => 'Upper back', 'ar' => 'أعلى الظهر']],
                ['code' => '041', 'label' => ['fr' => 'Omoplates', 'en' => 'Shoulder blades', 'ar' => 'لوحا الكتف']],
                ['code' => '042', 'label' => ['fr' => 'Entre les omoplates', 'en' => 'Between the shoulder blades', 'ar' => 'بين لوحي الكتف']],
                ['code' => '043', 'label' => ['fr' => 'Colonne vertébrale', 'en' => 'Spine', 'ar' => 'العمود الفقري']],
                ['code' => '044', 'label' => ['fr' => 'Lombaires', 'en' => 'Lower back', 'ar' => 'أسفل الظهر']],
                ['code' => '045', 'label' => ['fr' => 'Sacrum', 'en' => 'Sacrum', 'ar' => 'العجز']],
                ['code' => '046', 'label' => ['fr' => 'Reins', 'en' => 'Kidneys', 'ar' => 'الكليتان']],
                ['code' => '047', 'label' => ['fr' => 'Hanches', 'en' => 'Hips', 'ar' => 'الوركان']],
                ['code' => '048', 'label' => ['fr' => 'Bassin', 'en' => 'Pelvis', 'ar' => 'الحوض']],
                ['code' => '049', 'label' => ['fr' => 'Aine', 'en' => 'Groin', 'ar' => 'الأُربيّة']],
                ['code' => '050', 'label' => ['fr' => 'Pubis', 'en' => 'Pubis', 'ar' => 'العانة']],
                ['code' => '051', 'label' => ['fr' => 'Fesses', 'en' => 'Buttocks', 'ar' => 'الأرداف']],
                ['code' => '052', 'label' => ['fr' => 'Muscles fessiers', 'en' => 'Gluteal muscles', 'ar' => 'عضلات الألية']],
                ['code' => '053', 'label' => ['fr' => 'Sacro-iliaque', 'en' => 'Sacroiliac', 'ar' => 'المفصل العجزي الحرقفي']],
                ['code' => '054', 'label' => ['fr' => 'Ovaires', 'en' => 'Ovaries', 'ar' => 'المبيضان']],
                ['code' => '055', 'label' => ['fr' => 'Aisselle', 'en' => 'Armpit', 'ar' => 'الإبط']],
                ['code' => '056', 'label' => ['fr' => 'Jambes', 'en' => 'Legs', 'ar' => 'الساقان']],
                ['code' => '057', 'label' => ['fr' => 'Cuisse', 'en' => 'Thigh', 'ar' => 'الفخذ']],
                ['code' => '058', 'label' => ['fr' => 'Quadriceps', 'en' => 'Quadriceps', 'ar' => 'العضلة رباعية الرؤوس']],
                ['code' => '059', 'label' => ['fr' => 'Ischio-jambiers', 'en' => 'Hamstrings', 'ar' => 'أوتار الركبة الخلفية']],
                ['code' => '060', 'label' => ['fr' => 'Cuisse interne', 'en' => 'Inner thigh', 'ar' => 'باطن الفخذ']],
                ['code' => '061', 'label' => ['fr' => 'Cuisse externe', 'en' => 'Outer thigh', 'ar' => 'ظاهر الفخذ']],
                ['code' => '062', 'label' => ['fr' => 'Genou', 'en' => 'Knee', 'ar' => 'الركبة']],
                ['code' => '063', 'label' => ['fr' => 'Mollet', 'en' => 'Calf', 'ar' => 'ربلة الساق']],
                ['code' => '064', 'label' => ['fr' => 'Tibia', 'en' => 'Shin', 'ar' => 'قصبة الساق']],
                ['code' => '065', 'label' => ['fr' => 'Cheville', 'en' => 'Ankle', 'ar' => 'الكاحل']],
                ['code' => '066', 'label' => ['fr' => 'Pied', 'en' => 'Foot', 'ar' => 'القدم']],
                ['code' => '067', 'label' => ['fr' => 'Dessus du pied', 'en' => 'Top of the foot', 'ar' => 'ظهر القدم']],
                ['code' => '068', 'label' => ['fr' => 'Talon', 'en' => 'Heel', 'ar' => 'العقب']],
                ['code' => '069', 'label' => ['fr' => 'Plante du pied', 'en' => 'Sole of the foot', 'ar' => 'باطن القدم']],
                ['code' => '070', 'label' => ['fr' => 'Orteils', 'en' => 'Toes', 'ar' => 'أصابع القدم']],
                ['code' => '071', 'label' => ['fr' => 'Doigts de pied', 'en' => 'Toes', 'ar' => 'أصابع الرجل']],
            ]],
        ];

        foreach ($categories as $categoryData) {
            $category = CareCategory::query()->firstOrCreate(
                ['code' => $categoryData['code']],
                [
                    'label' => $categoryData['label'],
                    'order' => $categoryData['order'],
                    'active' => true,
                    // Verses are never billed; cupping is one flat price
                    // per session, not one per body zone (2026-11-17).
                    'pricing_mode' => ['verse' => CareCategory::PRICING_NONE, 'cupping' => CareCategory::PRICING_CATEGORY][$categoryData['code']] ?? CareCategory::PRICING_ITEM,
                ]
            );

            foreach ($categoryData['items'] as $itemData) {
                CareItem::query()->firstOrCreate(
                    ['care_category_id' => $category->id, 'code' => $itemData['code']],
                    ['label' => $itemData['label'], 'active' => true]
                );
            }
        }
    }
}
