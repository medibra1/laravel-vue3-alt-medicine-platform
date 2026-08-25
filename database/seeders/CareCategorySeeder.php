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
            ['code' => 'general', 'label' => ['fr' => 'Générale', 'en' => 'General'], 'order' => 1, 'items' => [
                ['code' => '001', 'label' => ['fr' => 'Lecture', 'en' => 'Recitation']],
                ['code' => '002', 'label' => ['fr' => 'Eau / Feuille coranisée à diluer', 'en' => 'Water / Quran-charged leaf to dilute']],
                ['code' => '003', 'label' => ['fr' => 'Huile / pommade', 'en' => 'Oil / ointment']],
                ['code' => '004', 'label' => ['fr' => 'Encens', 'en' => 'Incense']],
                ['code' => '005', 'label' => ['fr' => 'Tisane', 'en' => 'Herbal tea']],
                ['code' => '006', 'label' => ['fr' => 'Captage', 'en' => 'Capture']],
                ['code' => '007', 'label' => ['fr' => 'Psychothérapie', 'en' => 'Psychotherapy']],
                ['code' => '008', 'label' => ['fr' => 'Prière de malédiction', 'en' => 'Curse prayer']],
            ]],
            ['code' => 'other_care_products', 'label' => ['fr' => 'Autres soins et produits', 'en' => 'Other care and products'], 'order' => 2, 'items' => [
                ['code' => '001', 'label' => ['fr' => 'Savon', 'en' => 'Soap']],
                ['code' => '002', 'label' => ['fr' => 'Kohl', 'en' => 'Kohl']],
                ['code' => '003', 'label' => ['fr' => 'Habbat sawda en grain', 'en' => 'Black seed grain']],
                ['code' => '004', 'label' => ['fr' => 'Sidr', 'en' => 'Sidr']],
                ['code' => '005', 'label' => ['fr' => 'Costus', 'en' => 'Costus']],
                ['code' => '006', 'label' => ['fr' => 'Fenugrec', 'en' => 'Fenugreek']],
                ['code' => '007', 'label' => ['fr' => 'Camomille', 'en' => 'Chamomile']],
                ['code' => '008', 'label' => ['fr' => 'Thym', 'en' => 'Thyme']],
                ['code' => '009', 'label' => ['fr' => 'Autres plantes / mélanges', 'en' => 'Other plants / blends']],
                ['code' => '010', 'label' => ['fr' => 'Invocation / Dou\'a', 'en' => 'Invocation / Dua']],
                ['code' => '011', 'label' => ['fr' => 'Dhikr', 'en' => 'Dhikr']],
                ['code' => '012', 'label' => ['fr' => 'Auto-captage', 'en' => 'Self-capture']],
                ['code' => '013', 'label' => ['fr' => 'Remontage', 'en' => 'Re-assembly']],
                ['code' => '014', 'label' => ['fr' => 'Traitement du local', 'en' => 'Premises treatment']],
                ['code' => '015', 'label' => ['fr' => 'Autres soins', 'en' => 'Other care']],
                ['code' => '016', 'label' => ['fr' => 'Lecture individuelle', 'en' => 'Individual recitation']],
                ['code' => '017', 'label' => ['fr' => 'Eau de Zamzam', 'en' => 'Zamzam water']],
                ['code' => '018', 'label' => ['fr' => 'Eau avec Sidr', 'en' => 'Water with sidr']],
                ['code' => '019', 'label' => ['fr' => 'Eau salée', 'en' => 'Salt water']],
                ['code' => '020', 'label' => ['fr' => 'Autres préparations à base d\'eau', 'en' => 'Other water-based preparations']],
                ['code' => '021', 'label' => ['fr' => 'Huile de Habbat sawda', 'en' => 'Black seed oil']],
                ['code' => '022', 'label' => ['fr' => 'Huile d\'olive', 'en' => 'Olive oil']],
                ['code' => '023', 'label' => ['fr' => 'Huile de costus', 'en' => 'Costus oil']],
                ['code' => '024', 'label' => ['fr' => 'Huile de sidr', 'en' => 'Sidr oil']],
                ['code' => '025', 'label' => ['fr' => 'Huile de ricin', 'en' => 'Castor oil']],
                ['code' => '026', 'label' => ['fr' => 'Huile de coco', 'en' => 'Coconut oil']],
                ['code' => '027', 'label' => ['fr' => 'Huile d\'argan', 'en' => 'Argan oil']],
                ['code' => '028', 'label' => ['fr' => 'Huile de sésame', 'en' => 'Sesame oil']],
                ['code' => '029', 'label' => ['fr' => 'Huile d\'amande', 'en' => 'Almond oil']],
                ['code' => '030', 'label' => ['fr' => 'Beurre de karité', 'en' => 'Shea butter']],
                ['code' => '031', 'label' => ['fr' => 'Crème', 'en' => 'Cream']],
                ['code' => '032', 'label' => ['fr' => 'Baume', 'en' => 'Balm']],
                ['code' => '033', 'label' => ['fr' => 'Massage', 'en' => 'Massage']],
                ['code' => '034', 'label' => ['fr' => 'Autres huiles / produits de massage', 'en' => 'Other oils / massage products']],
                ['code' => '035', 'label' => ['fr' => 'Musk', 'en' => 'Musk']],
                ['code' => '036', 'label' => ['fr' => 'Haltite', 'en' => 'Haltite']],
                ['code' => '037', 'label' => ['fr' => 'Autres encens', 'en' => 'Other incense']],
            ]],
            ['code' => 'verse', 'label' => ['fr' => 'Verset à ajouter', 'en' => 'Verse to add'], 'order' => 3, 'items' => [
                ['code' => '001', 'label' => ['fr' => 'S21 v30 (Cadenas)', 'en' => 'S21 v30 (Padlock)']],
                ['code' => '002', 'label' => ['fr' => 'S20 v26-28 (Fil avec 11 nœuds)', 'en' => 'S20 v26-28 (Thread with 11 knots)']],
                ['code' => '003', 'label' => ['fr' => 'S26 v63 (Rivière)', 'en' => 'S26 v63 (River)']],
                ['code' => '004', 'label' => ['fr' => 'S6 v59 jusqu\'à ya\'lamouha (Arbre)', 'en' => 'S6 v59 until ya\'lamouha (Tree)']],
                ['code' => '005', 'label' => ['fr' => 'S22 v31 à partir de waman yochrik (Trou, pont ou puits)', 'en' => 'S22 v31 from waman yochrik (Hole, bridge or well)']],
                ['code' => '006', 'label' => ['fr' => 'S6 v122 jusqu\'à bikharijin minha (Cimetière)', 'en' => 'S6 v122 until bikharijin minha (Cemetery)']],
                ['code' => '007', 'label' => ['fr' => 'S38 v42 (Chaussure, terre + pas)', 'en' => 'S38 v42 (Shoe, soil + footprint)']],
                ['code' => '008', 'label' => ['fr' => 'S7 v26 jusqu\'à khayr (Habit)', 'en' => 'S7 v26 until khayr (Garment)']],
                ['code' => '009', 'label' => ['fr' => 'S7 v11 jusqu\'à fasajadou (Photo)', 'en' => 'S7 v11 until fasajadou (Photo)']],
                ['code' => '010', 'label' => ['fr' => 'S19 v4 à partir de inni wahana (Cheveux)', 'en' => 'S19 v4 from inni wahana (Hair)']],
                ['code' => '011', 'label' => ['fr' => 'S2 v102 de fayata\'llamouna jusqu\'à bi\'idhni llah (Lettre)', 'en' => 'S2 v102 from fayata\'llamouna until bi\'idhni llah (Letter)']],
                ['code' => '012', 'label' => ['fr' => 'S3 v106 jusqu\'à imanikom (Charbon)', 'en' => 'S3 v106 until imanikom (Coal)']],
                ['code' => '013', 'label' => ['fr' => 'S5 v64 depuis kollama awqadou (Braise)', 'en' => 'S5 v64 from kollama awqadou (Ember)']],
                ['code' => '014', 'label' => ['fr' => 'S5 v89 jusqu\'à alayman (Étoile ou trombone)', 'en' => 'S5 v89 until alayman (Star or paperclip)']],
                ['code' => '015', 'label' => ['fr' => 'S25 v23 (Canari)', 'en' => 'S25 v23 (Canary)']],
                ['code' => '016', 'label' => ['fr' => 'S6 v95 jusqu\'à alhayy (Cola coupé)', 'en' => 'S6 v95 until alhayy (Cut kola nut)']],
                ['code' => '017', 'label' => ['fr' => 'S33 v10-11 (Cola ou poupée piquée)', 'en' => 'S33 v10-11 (Kola nut or pierced doll)']],
                ['code' => '018', 'label' => ['fr' => 'S42 v37 à partir de wa\'idha (3 clous, colère)', 'en' => 'S42 v37 from wa\'idha (3 nails, anger)']],
                ['code' => '019', 'label' => ['fr' => 'S5 v3 jusqu\'à lighayri llah bihi (Sang des ventouses)', 'en' => 'S5 v3 until lighayri llah bihi (Blood from cupping)']],
                ['code' => '020', 'label' => ['fr' => 'S2 v222 à partir de fa\'idha tatahharna (Sang des règles)', 'en' => 'S2 v222 from fa\'idha tatahharna (Menstrual blood)']],
                ['code' => '021', 'label' => ['fr' => 'S5 v3 jusqu\'à fisq (Cadavres d\'animaux)', 'en' => 'S5 v3 until fisq (Animal carcasses)']],
                ['code' => '022', 'label' => ['fr' => 'S5 v4 de wa ma \'allamtom jusqu\'à \'allamakoum Allah (Poils de chien)', 'en' => 'S5 v4 from wa ma \'allamtom until \'allamakoum Allah (Dog hair)']],
                ['code' => '023', 'label' => ['fr' => 'S37 v142-144 (Poisson)', 'en' => 'S37 v142-144 (Fish)']],
                ['code' => '024', 'label' => ['fr' => 'S36 v78-79 (3 os)', 'en' => 'S36 v78-79 (3 bones)']],
                ['code' => '025', 'label' => ['fr' => 'S74 v3-5 (Caca)', 'en' => 'S74 v3-5 (Excrement)']],
                ['code' => '026', 'label' => ['fr' => 'S16 v80 à partir de wa min (Peau)', 'en' => 'S16 v80 from wa min (Skin)']],
                ['code' => '027', 'label' => ['fr' => 'S62 v5 jusqu\'à asfara (Âne)', 'en' => 'S62 v5 until asfara (Donkey)']],
                ['code' => '028', 'label' => ['fr' => 'S7 v22 de badat à janna (Slip)', 'en' => 'S7 v22 from badat to janna (Underwear)']],
                ['code' => '029', 'label' => ['fr' => 'S86 v5-6 (Poils de pubis)', 'en' => 'S86 v5-6 (Pubic hair)']],
                ['code' => '030', 'label' => ['fr' => 'S56 v52-54 (Piment)', 'en' => 'S56 v52-54 (Chili pepper)']],
                ['code' => '031', 'label' => ['fr' => 'S2 v275 jusqu\'à almass (Folie)', 'en' => 'S2 v275 until almass (Madness)']],
                ['code' => '032', 'label' => ['fr' => 'S6 v162-163 (Sacrifice)', 'en' => 'S6 v162-163 (Sacrifice)']],
                ['code' => '033', 'label' => ['fr' => 'S12 v42 à partir de fa\'ansahou et S18 v24 de wadhkor (Oubli)', 'en' => 'S12 v42 from fa\'ansahou and S18 v24 from wadhkor (Forgetfulness)']],
                ['code' => '034', 'label' => ['fr' => 'S30 v17-19 (Revivification)', 'en' => 'S30 v17-19 (Revivification)']],
                ['code' => '035', 'label' => ['fr' => 'S18 v42 à partir de fa\'asbaha (Perdre l\'argent)', 'en' => 'S18 v42 from fa\'asbaha (Loss of money)']],
                ['code' => '036', 'label' => ['fr' => 'S21 v98 (Statue)', 'en' => 'S21 v98 (Statue)']],
                ['code' => '037', 'label' => ['fr' => 'S20 v108 à partir de wakhacha\'ati (Bruits ou bourdonnement)', 'en' => 'S20 v108 from wakhacha\'ati (Noises or buzzing)']],
                ['code' => '038', 'label' => ['fr' => 'S27 v52 jusqu\'à dhalamou (Présences dans la maison)', 'en' => 'S27 v52 until dhalamou (Presences in the house)']],
                ['code' => '039', 'label' => ['fr' => 'S34 v54 (Passions)', 'en' => 'S34 v54 (Passions)']],
                ['code' => '040', 'label' => ['fr' => 'S38 v20 (Faiblesse mentale)', 'en' => 'S38 v20 (Mental weakness)']],
                ['code' => '041', 'label' => ['fr' => 'S50 v22 à partir de fakachafna (Maladie des yeux)', 'en' => 'S50 v22 from fakachafna (Eye ailment)']],
                ['code' => '042', 'label' => ['fr' => 'S51 v47-49 (Stérilité et impuissance)', 'en' => 'S51 v47-49 (Sterility and impotence)']],
                ['code' => '043', 'label' => ['fr' => 'S76 v13 à partir de la yarawna (Froid)', 'en' => 'S76 v13 from la yarawna (Cold)']],
                ['code' => '044', 'label' => ['fr' => 'S34 v14 jusqu\'à minsatah (Termite)', 'en' => 'S34 v14 until minsatah (Termite)']],
                ['code' => '045', 'label' => ['fr' => 'S106 v4 (Peur)', 'en' => 'S106 v4 (Fear)']],
                ['code' => '046', 'label' => ['fr' => 'S55 v33-35 (Tuer djinns et sorciers)', 'en' => 'S55 v33-35 (Killing jinns and sorcerers)']],
            ]],
            ['code' => 'cupping', 'label' => ['fr' => 'Ventouses', 'en' => 'Cupping'], 'order' => 4, 'items' => [
                ['code' => '001', 'label' => ['fr' => 'Tête', 'en' => 'Head']],
                ['code' => '002', 'label' => ['fr' => 'Cuir chevelu', 'en' => 'Scalp']],
                ['code' => '003', 'label' => ['fr' => 'Front', 'en' => 'Forehead']],
                ['code' => '004', 'label' => ['fr' => 'Tempes', 'en' => 'Temples']],
                ['code' => '005', 'label' => ['fr' => 'Occiput', 'en' => 'Occiput']],
                ['code' => '006', 'label' => ['fr' => 'Joues', 'en' => 'Cheeks']],
                ['code' => '007', 'label' => ['fr' => 'Nez', 'en' => 'Nose']],
                ['code' => '008', 'label' => ['fr' => 'Oreilles', 'en' => 'Ears']],
                ['code' => '009', 'label' => ['fr' => 'Bouche', 'en' => 'Mouth']],
                ['code' => '010', 'label' => ['fr' => 'Menton', 'en' => 'Chin']],
                ['code' => '011', 'label' => ['fr' => 'Cou', 'en' => 'Neck']],
                ['code' => '012', 'label' => ['fr' => 'Nuque', 'en' => 'Nape']],
                ['code' => '013', 'label' => ['fr' => 'Gorge', 'en' => 'Throat']],
                ['code' => '014', 'label' => ['fr' => 'Épaules', 'en' => 'Shoulders']],
                ['code' => '015', 'label' => ['fr' => 'Trapèzes', 'en' => 'Trapezius']],
                ['code' => '016', 'label' => ['fr' => 'Bras', 'en' => 'Arms']],
                ['code' => '017', 'label' => ['fr' => 'Biceps', 'en' => 'Biceps']],
                ['code' => '018', 'label' => ['fr' => 'Triceps', 'en' => 'Triceps']],
                ['code' => '019', 'label' => ['fr' => 'Coude', 'en' => 'Elbow']],
                ['code' => '020', 'label' => ['fr' => 'Avant-bras', 'en' => 'Forearm']],
                ['code' => '021', 'label' => ['fr' => 'Poignet', 'en' => 'Wrist']],
                ['code' => '022', 'label' => ['fr' => 'Main', 'en' => 'Hand']],
                ['code' => '023', 'label' => ['fr' => 'Paume', 'en' => 'Palm']],
                ['code' => '024', 'label' => ['fr' => 'Doigts', 'en' => 'Fingers']],
                ['code' => '025', 'label' => ['fr' => 'Torse', 'en' => 'Torso']],
                ['code' => '026', 'label' => ['fr' => 'Thorax', 'en' => 'Thorax']],
                ['code' => '027', 'label' => ['fr' => 'Poitrine', 'en' => 'Chest']],
                ['code' => '028', 'label' => ['fr' => 'Pectoraux', 'en' => 'Pectorals']],
                ['code' => '029', 'label' => ['fr' => 'Seins', 'en' => 'Breasts']],
                ['code' => '030', 'label' => ['fr' => 'Clavicule', 'en' => 'Clavicle']],
                ['code' => '031', 'label' => ['fr' => 'Côtes', 'en' => 'Ribs']],
                ['code' => '032', 'label' => ['fr' => 'Cœur', 'en' => 'Heart']],
                ['code' => '033', 'label' => ['fr' => 'Poumons', 'en' => 'Lungs']],
                ['code' => '034', 'label' => ['fr' => 'Abdomen', 'en' => 'Abdomen']],
                ['code' => '035', 'label' => ['fr' => 'Ventre', 'en' => 'Belly']],
                ['code' => '036', 'label' => ['fr' => 'Nombril', 'en' => 'Navel']],
                ['code' => '037', 'label' => ['fr' => 'Plexus', 'en' => 'Solar plexus']],
                ['code' => '038', 'label' => ['fr' => 'Flancs', 'en' => 'Flanks']],
                ['code' => '039', 'label' => ['fr' => 'Dos', 'en' => 'Back']],
                ['code' => '040', 'label' => ['fr' => 'Haut du dos', 'en' => 'Upper back']],
                ['code' => '041', 'label' => ['fr' => 'Omoplates', 'en' => 'Shoulder blades']],
                ['code' => '042', 'label' => ['fr' => 'Entre les omoplates', 'en' => 'Between the shoulder blades']],
                ['code' => '043', 'label' => ['fr' => 'Colonne vertébrale', 'en' => 'Spine']],
                ['code' => '044', 'label' => ['fr' => 'Lombaires', 'en' => 'Lower back']],
                ['code' => '045', 'label' => ['fr' => 'Sacrum', 'en' => 'Sacrum']],
                ['code' => '046', 'label' => ['fr' => 'Reins', 'en' => 'Kidneys']],
                ['code' => '047', 'label' => ['fr' => 'Hanches', 'en' => 'Hips']],
                ['code' => '048', 'label' => ['fr' => 'Bassin', 'en' => 'Pelvis']],
                ['code' => '049', 'label' => ['fr' => 'Aine', 'en' => 'Groin']],
                ['code' => '050', 'label' => ['fr' => 'Pubis', 'en' => 'Pubis']],
                ['code' => '051', 'label' => ['fr' => 'Fesses', 'en' => 'Buttocks']],
                ['code' => '052', 'label' => ['fr' => 'Muscles fessiers', 'en' => 'Gluteal muscles']],
                ['code' => '053', 'label' => ['fr' => 'Sacro-iliaque', 'en' => 'Sacroiliac']],
                ['code' => '054', 'label' => ['fr' => 'Ovaires', 'en' => 'Ovaries']],
                ['code' => '055', 'label' => ['fr' => 'Aisselle', 'en' => 'Armpit']],
                ['code' => '056', 'label' => ['fr' => 'Jambes', 'en' => 'Legs']],
                ['code' => '057', 'label' => ['fr' => 'Cuisse', 'en' => 'Thigh']],
                ['code' => '058', 'label' => ['fr' => 'Quadriceps', 'en' => 'Quadriceps']],
                ['code' => '059', 'label' => ['fr' => 'Ischio-jambiers', 'en' => 'Hamstrings']],
                ['code' => '060', 'label' => ['fr' => 'Cuisse interne', 'en' => 'Inner thigh']],
                ['code' => '061', 'label' => ['fr' => 'Cuisse externe', 'en' => 'Outer thigh']],
                ['code' => '062', 'label' => ['fr' => 'Genou', 'en' => 'Knee']],
                ['code' => '063', 'label' => ['fr' => 'Mollet', 'en' => 'Calf']],
                ['code' => '064', 'label' => ['fr' => 'Tibia', 'en' => 'Shin']],
                ['code' => '065', 'label' => ['fr' => 'Cheville', 'en' => 'Ankle']],
                ['code' => '066', 'label' => ['fr' => 'Pied', 'en' => 'Foot']],
                ['code' => '067', 'label' => ['fr' => 'Dessus du pied', 'en' => 'Top of the foot']],
                ['code' => '068', 'label' => ['fr' => 'Talon', 'en' => 'Heel']],
                ['code' => '069', 'label' => ['fr' => 'Plante du pied', 'en' => 'Sole of the foot']],
                ['code' => '070', 'label' => ['fr' => 'Orteils', 'en' => 'Toes']],
                ['code' => '071', 'label' => ['fr' => 'Doigts de pied', 'en' => 'Toes']],
            ]],
        ];

        foreach ($categories as $categoryData) {
            $category = CareCategory::query()->firstOrCreate(
                ['code' => $categoryData['code']],
                ['label' => $categoryData['label'], 'order' => $categoryData['order'], 'active' => true]
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
