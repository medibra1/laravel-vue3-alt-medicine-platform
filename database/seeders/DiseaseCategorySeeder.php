<?php

namespace Database\Seeders;

use App\Domains\Common\Models\EnumOption;
use App\Domains\Patients\Models\Disease;
use App\Domains\Patients\Models\DiseaseCategory;
use App\Domains\Patients\Models\DiseaseSubcase;
use Illuminate\Database\Seeder;

class DiseaseCategorySeeder extends Seeder
{
    /**
     * Categories and diseases extracted from the source document
     * (LISTES_DES_MALADIES.docx, provided by the user). Categories 1-7
     * (array keys, `code` "1"-"7") = type ILLNESS, category 8
     * (`code` "8", BLOCKAGES) = type BLOCKAGE.
     *
     * `order` (display sort only, unrelated to `code`) is: Blockages=1,
     * Symbols=2, then illness categories 1-7 at order=3..9 — Blockages
     * and Symbols were promoted to the top of the list on 2026-08-24
     * per user request; `code` values were NOT touched (801-804 stay
     * as-is, illness disease codes keep their 1xx-7xx prefixes).
     *
     * English labels/descriptions are real translations (not
     * duplicated French), done by Claude — a native-speaker review
     * before English is a live language is still recommended for
     * medical terminology accuracy, but this is a genuine
     * translation, not a placeholder.
     *
     * Category "Symboles" (`code` "0", type SYMBOL, 2026-08-24): real
     * content provided by the user — a list of symbols reported during
     * sessions — not a placeholder. Replaces the former category 9
     * "Cauchemars" (type NIGHTMARE), which was removed: it never had
     * real source content (only two placeholder diseases, 901/902,
     * seeded solely to exercise the wizard/session flow end-to-end).
     * The NIGHTMARE EnumOption itself was also removed from
     * EnumOptionSeeder — nothing else referenced it.
     *
     * Idempotent: firstOrCreate by category code / by disease code (the
     * 3-digit code from the source document, stable and unique per
     * category).
     *
     * Blockage sub-cases (801-804) — extraction confidence level, worth
     * knowing before editing:
     * - 801 (Work) and 802 (Money): clean source format
     *   ("Label: description"), direct extraction, reliable.
     * - 803 (Administrative): flat list with no description in the
     *   source ("papers, travel, registration, procedures"), each word
     *   becomes a sub-case with no description.
     * - 804 (Marriage): source is continuous prose, NOT a structured
     *   list — split into 5 sub-cases done by interpretation (Claude),
     *   presented to and confirmed by the user on 2026-08-19 before
     *   seeding, but remains the least directly source-derived of the
     *   four. Revisit first if the end user disagrees with it.
     *
     * ⚠️ 2026-08-25 — `disease_subcases` is being retired (per user
     * decision): it was never surfaced anywhere in the wizard/session UI
     * (only `Disease.description`, shown as an info tooltip, is), so its
     * content for 801-804 was invisible to practitioners despite being
     * real data. Merged into each disease's own `description` here —
     * "Label: detail; Label: detail; ..." (semicolon-separated, 803's
     * sub-cases have no detail so those are label-only) — so the same
     * information now surfaces where practitioners actually look. The
     * $subcasesByDiseaseCode array and DiseaseSubcase::firstOrCreate()
     * loop below are UNCHANGED for now (still seed disease_subcases) —
     * only drop them once `disease_subcases` itself is actually removed
     * (migration + model), not yet done as of this commit.
     *
     * ⚠️ Disease 416's description originally referenced the practice's
     * religious name verbatim from the source document — neutralized
     * to "during a session" here, consistent with the project's
     * terminology policy (see CLAUDE.md).
     */
    public function run(): void
    {
        $illness = EnumOption::query()
            ->where('enum_type', 'disease_category.type')->where('code', 'ILLNESS')->firstOrFail();
        $blockage = EnumOption::query()
            ->where('enum_type', 'disease_category.type')->where('code', 'BLOCKAGE')->firstOrFail();
        $symbol = EnumOption::query()
            ->where('enum_type', 'disease_category.type')->where('code', 'SYMBOL')->firstOrFail();

        $categories = [
            1 => [
                'code' => '1',
                'label' => ['fr' => 'Maladies digestives', 'en' => 'Digestive diseases', 'ar' => 'أمراض الجهاز الهضمي'],
                'icon' => 'mdi-stomach',
                'type' => $illness,
                'order' => 3,
                'diseases' => [
                    ['code' => '101', 'label' => ['fr' => 'Acidité-brûlure', 'en' => 'Acidity / heartburn', 'ar' => 'حموضة وحرقة'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '102', 'label' => ['fr' => 'Ulcère', 'en' => 'Ulcer', 'ar' => 'قرحة'], 'default_duration_months' => 3, 'description' => ['fr' => 'Il faut que la médecine ait identifié une plaie dans l’estomac', 'en' => 'Medicine must have identified a wound in the stomach', 'ar' => 'يجب أن يكون الطب قد شخّص جرحاً في المعدة']],
                    ['code' => '103', 'label' => ['fr' => 'Constipation', 'en' => 'Constipation', 'ar' => 'إمساك'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '104', 'label' => ['fr' => 'Diarrhée', 'en' => 'Diarrhea', 'ar' => 'إسهال'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '105', 'label' => ['fr' => 'Ballonnement', 'en' => 'Bloating', 'ar' => 'انتفاخ'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '106', 'label' => ['fr' => 'Hémorroïdes', 'en' => 'Hemorrhoids', 'ar' => 'بواسير'], 'default_duration_months' => 3, 'description' => ['fr' => 'Interne, externe ou avec saignement', 'en' => 'Internal, external, or with bleeding', 'ar' => 'داخلية أو خارجية أو مع نزيف']],
                    ['code' => '107', 'label' => ['fr' => 'Douleur abdominale', 'en' => 'Abdominal pain', 'ar' => 'ألم في البطن'], 'default_duration_months' => 3, 'description' => ['fr' => 'Générale ou localisée', 'en' => 'General or localized', 'ar' => 'عام أو موضعي']],
                    ['code' => '108', 'label' => ['fr' => 'Prise de poids', 'en' => 'Weight gain', 'ar' => 'زيادة الوزن'], 'default_duration_months' => 3, 'description' => ['fr' => 'Soit la personne mange trop, soit elle grossit dans raison', 'en' => 'Either the person eats too much, or gains weight for no reason', 'ar' => 'إما أن الشخص يفرط في الأكل، أو يسمن دون سبب']],
                    ['code' => '109', 'label' => ['fr' => 'Perte de poids', 'en' => 'Weight loss', 'ar' => 'فقدان الوزن'], 'default_duration_months' => 3, 'description' => ['fr' => 'Soit la personne ne se nourrit plus, soit elle maigrit sans raison', 'en' => 'Either the person stops eating, or loses weight for no reason', 'ar' => 'إما أن الشخص لم يعد يتغذى، أو ينحف دون سبب']],
                    ['code' => '110', 'label' => ['fr' => 'Plaie dans la bouche', 'en' => 'Mouth sore', 'ar' => 'جرح في الفم'], 'default_duration_months' => 3, 'description' => ['fr' => 'Ou douleurs dans la gorge', 'en' => 'Or throat pain', 'ar' => 'أو آلام في الحلق']],
                    ['code' => '111', 'label' => ['fr' => 'Enflure de la gencive', 'en' => 'Gum swelling', 'ar' => 'تورّم اللثة'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '112', 'label' => ['fr' => 'Mauvaise odeur', 'en' => 'Bad odor', 'ar' => 'رائحة كريهة'], 'default_duration_months' => 3, 'description' => ['fr' => 'Se dégageant de toute partie du corps', 'en' => 'Coming from any part of the body', 'ar' => 'تنبعث من أي جزء من الجسم']],
                    ['code' => '113', 'label' => ['fr' => 'Maladie de Crohn', 'en' => 'Crohn\'s disease', 'ar' => 'داء كرون'], 'default_duration_months' => 12, 'description' => ['fr' => 'Inflammation chronique de l’intestin ou de tout l’appareil digestif', 'en' => 'Chronic inflammation of the intestine or the entire digestive tract', 'ar' => 'التهاب مزمن في الأمعاء أو في كامل الجهاز الهضمي']],
                    ['code' => '114', 'label' => ['fr' => 'Ascite', 'en' => 'Ascites', 'ar' => 'استسقاء'], 'default_duration_months' => 3, 'description' => ['fr' => 'Accumulation de liquide dans le ventre', 'en' => 'Fluid buildup in the abdomen', 'ar' => 'تجمّع السوائل في البطن']],
                    ['code' => '115', 'label' => ['fr' => 'Infection urinaire', 'en' => 'Urinary infection', 'ar' => 'التهاب المسالك البولية'], 'default_duration_months' => 3, 'description' => ['fr' => 'Douleurs de vessie et urinaire, calculs dans la vessie', 'en' => 'Bladder and urinary pain, bladder stones', 'ar' => 'آلام في المثانة والمسالك البولية، حصى في المثانة']],
                    ['code' => '116', 'label' => ['fr' => 'Faiblesse des reins', 'en' => 'Kidney weakness', 'ar' => 'ضعف الكلى'], 'default_duration_months' => 6, 'description' => ['fr' => 'Cela provoque une faiblesse générale mais ne nécessite pas la dialyse', 'en' => 'Causes general weakness but does not require dialysis', 'ar' => 'يسبب ضعفاً عاماً لكنه لا يستدعي غسيل الكلى']],
                    ['code' => '117', 'label' => ['fr' => 'Arrêt des reins', 'en' => 'Kidney failure', 'ar' => 'فشل كلوي'], 'default_duration_months' => 6, 'description' => ['fr' => 'Nécessité de dialyse et de greffe de rein', 'en' => 'Requires dialysis and kidney transplant', 'ar' => 'يستدعي غسيل الكلى وزراعة الكلية']],
                    ['code' => '118', 'label' => ['fr' => 'Autres', 'en' => 'Other', 'ar' => 'أخرى'], 'default_duration_months' => 6, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                ],
            ],
            2 => [
                'code' => '2',
                'label' => ['fr' => 'Maladies de la peau', 'en' => 'Skin diseases', 'ar' => 'الأمراض الجلدية'],
                'icon' => 'mdi-bandage',
                'type' => $illness,
                'order' => 4,
                'diseases' => [
                    ['code' => '201', 'label' => ['fr' => 'Psoriasis', 'en' => 'Psoriasis', 'ar' => 'الصدفية'], 'default_duration_months' => 6, 'description' => ['fr' => 'Eczéma grave, étendu et non traitable', 'en' => 'Severe, widespread and untreatable eczema', 'ar' => 'إكزيما شديدة وواسعة ومستعصية على العلاج']],
                    ['code' => '202', 'label' => ['fr' => 'Eczéma', 'en' => 'Eczema', 'ar' => 'الإكزيما'], 'default_duration_months' => 3, 'description' => ['fr' => 'Y compris démangeaisons, acné, rougeurs, boutons', 'en' => 'Including itching, acne, redness, pimples', 'ar' => 'بما في ذلك الحكة وحب الشباب والاحمرار والبثور']],
                    ['code' => '203', 'label' => ['fr' => 'Plaie ouverte', 'en' => 'Open wound', 'ar' => 'جرح مفتوح'], 'default_duration_months' => 3, 'description' => ['fr' => 'Souvent purulente et puante', 'en' => 'Often purulent and foul-smelling', 'ar' => 'غالباً متقيّح وكريه الرائحة']],
                    ['code' => '204', 'label' => ['fr' => 'Abcès et boutons persistants', 'en' => 'Abscesses and persistent pimples', 'ar' => 'خراريج وبثور مستمرة'], 'default_duration_months' => 3, 'description' => ['fr' => 'Dont abcès anal', 'en' => 'Including anal abscess', 'ar' => 'منها الخرّاج الشرجي']],
                    ['code' => '205', 'label' => ['fr' => 'Enflure inexpliquée', 'en' => 'Unexplained swelling', 'ar' => 'تورّم غير مبرَّر'], 'default_duration_months' => 3, 'description' => ['fr' => 'Comme les jambes d’éléphant, à distinguer des enflures dues au cancer', 'en' => 'Such as elephantiasis-like legs, to be distinguished from cancer-related swelling', 'ar' => 'مثل داء الفيل، يُميَّز عن التورّمات الناتجة عن السرطان']],
                    ['code' => '206', 'label' => ['fr' => 'Chutes de cheveux', 'en' => 'Hair loss', 'ar' => 'تساقط الشعر'], 'default_duration_months' => 6, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '207', 'label' => ['fr' => 'Chéloïde', 'en' => 'Keloid', 'ar' => 'الجُدَرة'], 'default_duration_months' => 6, 'description' => ['fr' => 'Plaie cicatrisant avec une excroissance de la peau', 'en' => 'A scar healing with a raised overgrowth of skin', 'ar' => 'جرح يلتئم مع نتوء زائد في الجلد']],
                    ['code' => '208', 'label' => ['fr' => 'Vitiligo, Dépigmentation', 'en' => 'Vitiligo, depigmentation', 'ar' => 'البهاق، فقدان الصبغة'], 'default_duration_months' => 6, 'description' => ['fr' => 'Nous n’avons pas pu retrouver la pigmentation, mais nous essayons de stopper l’expansion de la dépigmentation', 'en' => 'Pigmentation could not be restored, but we try to stop the depigmentation from spreading', 'ar' => 'لم نتمكن من استعادة الصبغة، لكننا نحاول إيقاف انتشار فقدانها']],
                    ['code' => '209', 'label' => ['fr' => 'Autres', 'en' => 'Other', 'ar' => 'أخرى'], 'default_duration_months' => 6, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                ],
            ],
            3 => [
                'code' => '3',
                'label' => ['fr' => 'Maladies du sexe', 'en' => 'Reproductive and sexual health disorders', 'ar' => 'أمراض الجهاز التناسلي'],
                'icon' => 'mdi-gender-male-female',
                'type' => $illness,
                'order' => 5,
                'diseases' => [
                    ['code' => '301', 'label' => ['fr' => 'Kyste', 'en' => 'Cyst', 'ar' => 'كيس'], 'default_duration_months' => 3, 'description' => ['fr' => 'Ou fibrome  dans l’ovaire ou l’utérus', 'en' => 'Or fibroid in the ovary or uterus', 'ar' => 'أو ورم ليفي في المبيض أو الرحم']],
                    ['code' => '302', 'label' => ['fr' => 'Règles irrégulières', 'en' => 'Irregular periods', 'ar' => 'اضطراب الدورة الشهرية'], 'default_duration_months' => 6, 'description' => ['fr' => 'Ainsi que saignement en dehors des menstrues', 'en' => 'As well as bleeding outside of menstruation', 'ar' => 'وكذلك النزيف خارج أيام الحيض']],
                    ['code' => '303', 'label' => ['fr' => 'Douleurs de bas-ventre', 'en' => 'Lower abdominal pain', 'ar' => 'آلام أسفل البطن'], 'default_duration_months' => 3, 'description' => ['fr' => 'Très forte douleur pendant les règles ou en dehors, aux ovaires ou à l’utérus', 'en' => 'Very strong pain during or outside periods, in the ovaries or uterus', 'ar' => 'ألم شديد جداً أثناء الحيض أو خارجه، في المبيضين أو الرحم']],
                    ['code' => '304', 'label' => ['fr' => 'Ménopause précoce', 'en' => 'Early menopause', 'ar' => 'انقطاع الطمث المبكر'], 'default_duration_months' => 12, 'description' => ['fr' => 'Arrêt des règles longtemps avant 50 ans', 'en' => 'Periods stop long before age 50', 'ar' => 'توقّف الحيض قبل سن الخمسين بوقت طويل']],
                    ['code' => '305', 'label' => ['fr' => 'Règles jamais venues', 'en' => 'Periods never started', 'ar' => 'عدم نزول الحيض'], 'default_duration_months' => 12, 'description' => ['fr' => 'Femme adulte n’a jamais eu les règles', 'en' => 'Adult woman has never had periods', 'ar' => 'امرأة بالغة لم تحض قط']],
                    ['code' => '306', 'label' => ['fr' => 'Infertilité inexpliquée', 'en' => 'Unexplained infertility', 'ar' => 'عقم غير مبرَّر'], 'default_duration_months' => 6, 'description' => ['fr' => 'Absence de grossesse', 'en' => 'Absence of pregnancy', 'ar' => 'عدم حدوث الحمل']],
                    ['code' => '307', 'label' => ['fr' => 'Fausses couches', 'en' => 'Recurrent miscarriages', 'ar' => 'إجهاض متكرر'], 'default_duration_months' => 12, 'description' => ['fr' => 'Répétées, l’enfant vient après plusieurs fausses couches', 'en' => 'The child comes only after several miscarriages', 'ar' => 'متكرر، يأتي الطفل بعد عدة حالات إجهاض']],
                    ['code' => '308', 'label' => ['fr' => 'Fausses couches', 'en' => 'Systematic miscarriages', 'ar' => 'إجهاض متكرر'], 'default_duration_months' => 12, 'description' => ['fr' => 'Systématiques, la grossesse n’aboutit jamais', 'en' => 'The pregnancy never succeeds', 'ar' => 'منهجي، لا يكتمل الحمل أبداً']],
                    ['code' => '309', 'label' => ['fr' => 'Vaginisme', 'en' => 'Vaginismus', 'ar' => 'التشنّج المهبلي'], 'default_duration_months' => 3, 'description' => ['fr' => 'Contraction des muscles entourant le vagin conduisant à sa fermeture et l’impossibilité des rapports', 'en' => 'Contraction of the muscles surrounding the vagina, making intercourse impossible', 'ar' => 'انقباض العضلات المحيطة بالمهبل يؤدي إلى انغلاقه واستحالة الجماع']],
                    ['code' => '310', 'label' => ['fr' => 'Sécheresse vaginale', 'en' => 'Vaginal dryness', 'ar' => 'جفاف المهبل'], 'default_duration_months' => 3, 'description' => ['fr' => 'Provoquant des douleurs lors de l’acte intime', 'en' => 'Causing pain during intimacy', 'ar' => 'يسبب آلاماً أثناء العلاقة الحميمة']],
                    ['code' => '311', 'label' => ['fr' => 'Infection vaginale', 'en' => 'Vaginal infection', 'ar' => 'التهاب مهبلي'], 'default_duration_months' => 3, 'description' => ['fr' => 'Boutons et irritations persistants rendant l’acte pénible', 'en' => 'Persistent pimples and irritation making intercourse painful', 'ar' => 'بثور وتهيّج مستمر يجعل العلاقة مؤلمة']],
                    ['code' => '312', 'label' => ['fr' => 'Frigidité', 'en' => 'Frigidity', 'ar' => 'البرود الجنسي'], 'default_duration_months' => 3, 'description' => ['fr' => 'Absence de désir et de plaisir sexuels', 'en' => 'Absence of sexual desire and pleasure', 'ar' => 'انعدام الرغبة واللذة الجنسية']],
                    ['code' => '313', 'label' => ['fr' => 'Répulsion', 'en' => 'Repulsion', 'ar' => 'النفور'], 'default_duration_months' => 3, 'description' => ['fr' => 'Colère, rejet, ne pas se supporter, refuser les rapports', 'en' => 'Anger, rejection, cannot stand the partner, refusing intercourse', 'ar' => 'غضب، رفض، عدم تحمّل أحد الزوجين للآخر، رفض العلاقة']],
                    ['code' => '314', 'label' => ['fr' => 'Faiblesse sexuelle', 'en' => 'Sexual weakness', 'ar' => 'ضعف جنسي'], 'default_duration_months' => 3, 'description' => ['fr' => 'Perte de 50% ou plus de capacité sexuelle', 'en' => 'Loss of 50% or more of sexual capacity', 'ar' => 'فقدان 50% أو أكثر من القدرة الجنسية']],
                    ['code' => '315', 'label' => ['fr' => 'Impuissance', 'en' => 'Impotence', 'ar' => 'العجز الجنسي'], 'default_duration_months' => 3, 'description' => ['fr' => 'Absence d’érection permanente ou au moment des rapports ou pendant les rapports', 'en' => 'Absence of erection, permanently or during intercourse', 'ar' => 'غياب الانتصاب بشكل دائم أو عند العلاقة أو أثناءها']],
                    ['code' => '316', 'label' => ['fr' => 'Stérilité de l’homme', 'en' => 'Male sterility', 'ar' => 'عقم الرجل'], 'default_duration_months' => 6, 'description' => ['fr' => 'Absence totale de spermatozoïdes', 'en' => 'Total absence of sperm', 'ar' => 'انعدام الحيوانات المنوية تماماً']],
                    ['code' => '317', 'label' => ['fr' => 'Stérilité de l’homme', 'en' => 'Male sterility', 'ar' => 'عقم الرجل'], 'default_duration_months' => 6, 'description' => ['fr' => 'Spermatozoïdes insuffisants en nombre ou en qualité', 'en' => 'Insufficient sperm count or quality', 'ar' => 'حيوانات منوية غير كافية عدداً أو جودة']],
                    ['code' => '318', 'label' => ['fr' => 'Éjaculation précoce', 'en' => 'Premature ejaculation', 'ar' => 'سرعة القذف'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '319', 'label' => ['fr' => 'Autres mâle', 'en' => 'Other (male)', 'ar' => 'أخرى (رجل)'], 'default_duration_months' => 6, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '320', 'label' => ['fr' => 'Autres femme', 'en' => 'Other (female)', 'ar' => 'أخرى (امرأة)'], 'default_duration_months' => 6, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                ],
            ],
            4 => [
                'code' => '4',
                'label' => ['fr' => 'Maladies mentales et cérébrales', 'en' => 'Mental and neurological disorders', 'ar' => 'الأمراض النفسية والعصبية'],
                'icon' => 'mdi-brain',
                'type' => $illness,
                'order' => 6,
                'diseases' => [
                    ['code' => '401', 'label' => ['fr' => 'Psychose', 'en' => 'Psychosis', 'ar' => 'الذهان'], 'default_duration_months' => 3, 'description' => ['fr' => 'Perte de contact avec la réalité (folie), délires, propos absurdes', 'en' => 'Loss of contact with reality, delusions, absurd statements', 'ar' => 'فقدان الاتصال بالواقع (جنون)، هذيان، كلام غير منطقي']],
                    ['code' => '402', 'label' => ['fr' => 'Psychose épisodique', 'en' => 'Episodic psychosis', 'ar' => 'ذهان متقطّع'], 'default_duration_months' => 3, 'description' => ['fr' => 'Un moment et le patient revient à la réalité', 'en' => 'A brief episode, then the patient returns to reality', 'ar' => 'لفترة ثم يعود المريض إلى الواقع']],
                    ['code' => '403', 'label' => ['fr' => 'Hallucination', 'en' => 'Hallucination', 'ar' => 'الهلوسة'], 'default_duration_months' => 3, 'description' => ['fr' => 'Visions, voix, odeurs', 'en' => 'Visions, voices, smells', 'ar' => 'رؤى، أصوات، روائح']],
                    ['code' => '404', 'label' => ['fr' => 'Dépression', 'en' => 'Depression', 'ar' => 'الاكتئاب'], 'default_duration_months' => 3, 'description' => ['fr' => 'Le patient abandonne ses activités par découragement et s’isole', 'en' => 'The patient abandons their activities out of discouragement and withdraws', 'ar' => 'يترك المريض نشاطاته بسبب الإحباط وينعزل']],
                    ['code' => '405', 'label' => ['fr' => 'Anxiété', 'en' => 'Anxiety', 'ar' => 'القلق'], 'default_duration_months' => 3, 'description' => ['fr' => 'Le patient ne peut conduire ou dormir ou rester seul ou rester dans un endroit fermé ou passer un examen. Ceci inclut la sorcellerie qui empêche ces actes', 'en' => 'The patient cannot drive, sleep, stay alone, stay in an enclosed space, or sit an exam. This includes sorcery preventing these actions', 'ar' => 'لا يستطيع المريض القيادة أو النوم أو البقاء وحيداً أو في مكان مغلق أو اجتياز امتحان. ويشمل ذلك السحر الذي يمنع هذه الأفعال']],
                    ['code' => '406', 'label' => ['fr' => 'Addiction', 'en' => 'Addiction', 'ar' => 'الإدمان'], 'default_duration_months' => 3, 'description' => ['fr' => 'Drogues, pornographie, sexe, alcool, jeux de hasard', 'en' => 'Drugs, pornography, sex, alcohol, gambling', 'ar' => 'مخدرات، إباحية، جنس، كحول، قمار']],
                    ['code' => '407', 'label' => ['fr' => 'Rébellion', 'en' => 'Rebellion', 'ar' => 'التمرّد'], 'default_duration_months' => 3, 'description' => ['fr' => 'Enfant désobéissant, vol, mensonge, fugue, violence, délinquance', 'en' => 'Disobedient child — stealing, lying, running away, violence, delinquency', 'ar' => 'طفل عاصٍ، سرقة، كذب، هروب، عنف، انحراف']],
                    ['code' => '408', 'label' => ['fr' => 'Blocage religion', 'en' => 'Religious blockage', 'ar' => 'تعطيل ديني'], 'default_duration_months' => 3, 'description' => ['fr' => 'Ablutions, prière, coran, prêche, voile', 'en' => 'Ablutions, prayer, scripture, sermon, veil', 'ar' => 'الوضوء، الصلاة، القرآن، الموعظة، الحجاب']],
                    ['code' => '409', 'label' => ['fr' => 'TOC', 'en' => 'OCD', 'ar' => 'الوسواس القهري'], 'default_duration_months' => 6, 'description' => ['fr' => 'Troubles obsessionnels compulsifs, manies fortes et handicapantes', 'en' => 'Obsessive-compulsive disorder, severe and disabling compulsions', 'ar' => 'اضطرابات الوسواس القهري، عادات قوية ومعيقة']],
                    ['code' => '410', 'label' => ['fr' => 'Colère incontrôlée', 'en' => 'Uncontrolled anger', 'ar' => 'غضب غير متحكَّم فيه'], 'default_duration_months' => 3, 'description' => ['fr' => 'Causant des problèmes au foyer, au travail ou dans les relations', 'en' => 'Causing problems at home, at work, or in relationships', 'ar' => 'يسبب مشاكل في البيت أو العمل أو العلاقات']],
                    ['code' => '411', 'label' => ['fr' => 'Instabilité émotionnelle', 'en' => 'Emotional instability', 'ar' => 'عدم الاستقرار العاطفي'], 'default_duration_months' => 3, 'description' => ['fr' => 'Bipolarité, cyclothymie (passer rapidement et par cycle de l’exaltation à la mélancolie)', 'en' => 'Bipolarity, cyclothymia (rapid cyclical swings between elation and melancholy)', 'ar' => 'ثنائي القطب، دوروية المزاج (الانتقال السريع والدوري من النشوة إلى الكآبة)']],
                    ['code' => '412', 'label' => ['fr' => 'Pb concentration', 'en' => 'Concentration problem', 'ar' => 'مشاكل التركيز'], 'default_duration_months' => 3, 'description' => ['fr' => 'Entraînant un blocage ou un ralentissement de l’apprentissage ou du travail ; Y compris l’incapacité qui surgit lors d’un examen ou entretien', 'en' => 'Blocks or slows down learning or work; includes an inability that arises specifically during an exam or interview', 'ar' => 'تؤدي إلى توقّف أو بطء في التعلّم أو العمل؛ بما في ذلك العجز الذي يظهر أثناء امتحان أو مقابلة']],
                    ['code' => '413', 'label' => ['fr' => 'Pb de mémoire', 'en' => 'Memory problem', 'ar' => 'مشاكل الذاكرة'], 'default_duration_months' => 3, 'description' => ['fr' => 'Difficulté d’apprentissage, de travail ou dans la vie', 'en' => 'Difficulty learning, working, or in daily life', 'ar' => 'صعوبة في التعلّم أو العمل أو في الحياة']],
                    ['code' => '414', 'label' => ['fr' => 'Manque de volonté', 'en' => 'Lack of willpower', 'ar' => 'ضعف الإرادة'], 'default_duration_months' => 3, 'description' => ['fr' => 'Négligence, retard,', 'en' => 'Negligence, procrastination', 'ar' => 'إهمال، تأخّر']],
                    ['code' => '415', 'label' => ['fr' => 'Épilepsie', 'en' => 'Epilepsy', 'ar' => 'الصرع'], 'default_duration_months' => 6, 'description' => ['fr' => 'Perte de contrôle du corps, mouvements, tremblements, paroles, perte de connaissance ou de conscience. On ne distingue pas l’épilepsie nerveuse et la possession', 'en' => 'Loss of body control, movements, tremors, speech, loss of consciousness. Nervous epilepsy and possession are not distinguished', 'ar' => 'فقدان السيطرة على الجسم، حركات، ارتعاش، كلام، فقدان الوعي أو الإدراك. لا نفرّق بين الصرع العصبي والمسّ']],
                    ['code' => '416', 'label' => ['fr' => 'Epilepsie occasionnelle', 'en' => 'Occasional epilepsy', 'ar' => 'صرع عرضي'], 'default_duration_months' => 3, 'description' => ['fr' => 'Moins d\'une fois par semaine, ou juste pendant la séance', 'en' => 'Less than once a week, or only during a session', 'ar' => 'أقل من مرة في الأسبوع، أو فقط أثناء الجلسة']],
                    ['code' => '417', 'label' => ['fr' => 'Paralysie AVC', 'en' => 'Stroke-related paralysis', 'ar' => 'شلل بسبب جلطة دماغية'], 'default_duration_months' => 6, 'description' => ['fr' => 'Paralysie de la moitié du corps due à un Accident Vasculaire Cérébral 1) dû à l’arrêt de la circulation du sang dans une zone du cerveau 2) ou dû à une hémorragie intracérébrale', 'en' => 'Paralysis of one half of the body caused by a stroke — 1) blocked blood flow to a brain area, or 2) intracerebral hemorrhage', 'ar' => 'شلل نصف الجسم بسبب سكتة دماغية 1) ناتجة عن توقّف الدورة الدموية في منطقة من الدماغ 2) أو عن نزيف داخل الدماغ']],
                    ['code' => '418', 'label' => ['fr' => 'Paralysie du visage', 'en' => 'Facial paralysis', 'ar' => 'شلل الوجه'], 'default_duration_months' => 6, 'description' => ['fr' => 'La bouche ne se ferme plus ou l’œil ou la joue reste déformée', 'en' => 'The mouth no longer closes properly, or the eye or cheek stays distorted', 'ar' => 'لم يعد الفم ينغلق أو تبقى العين أو الخد مشوّهة']],
                    ['code' => '419', 'label' => ['fr' => 'Paralysie inexpliquée', 'en' => 'Unexplained paralysis', 'ar' => 'شلل غير مبرَّر'], 'default_duration_months' => 6, 'description' => ['fr' => 'D’une partie du corps', 'en' => 'Of a part of the body', 'ar' => 'في جزء من الجسم']],
                    ['code' => '420', 'label' => ['fr' => 'Retard mental', 'en' => 'Intellectual disability', 'ar' => 'التأخر العقلي'], 'default_duration_months' => 3, 'description' => ['fr' => 'Chez l’enfant, comme s’il a plusieurs années de moins. Nous n’espérons pas la guérison des pathologies de naissance, mais un pourcentage d’amélioration (%)', 'en' => 'In a child, as if several years behind. We do not expect a cure for congenital conditions, but a percentage of improvement (%)', 'ar' => 'عند الطفل، كأنه أصغر من عمره بعدة سنوات. لا نرجو الشفاء من الأمراض الخَلقية، بل نسبة تحسّن (%)']],
                    ['code' => '421', 'label' => ['fr' => 'Enfant handicapé', 'en' => 'Disabled child', 'ar' => 'طفل ذو إعاقة'], 'default_duration_months' => 3, 'description' => ['fr' => 'Dans la forme ou les fonctions de son corps (%)', 'en' => 'In the form or function of their body (%)', 'ar' => 'في شكل جسمه أو وظائفه (%)']],
                    ['code' => '422', 'label' => ['fr' => 'Autisme', 'en' => 'Autism', 'ar' => 'التوحّد'], 'default_duration_months' => 3, 'description' => ['fr' => 'La communication de l’enfant avec notre monde est absente ou imparfaite. Hyperactivité (%)', 'en' => 'The child\'s communication with our world is absent or impaired. Hyperactivity (%)', 'ar' => 'تواصل الطفل مع عالمنا غائب أو ناقص. فرط الحركة (%)']],
                    ['code' => '423', 'label' => ['fr' => 'Sclérose en plaque', 'en' => 'Multiple sclerosis', 'ar' => 'التصلّب اللويحي'], 'default_duration_months' => 12, 'description' => ['fr' => 'Affaiblissement du système nerveux entraînant faiblesse musculaire, trouble de l’équilibre, diminution de la sensibilité, troubles de la mémoire et de la concentration', 'en' => 'Weakening of the nervous system causing muscle weakness, balance issues, reduced sensitivity, memory and concentration problems', 'ar' => 'ضعف الجهاز العصبي يؤدي إلى ضعف العضلات واضطراب التوازن ونقص الإحساس واضطرابات الذاكرة والتركيز']],
                    ['code' => '424', 'label' => ['fr' => 'Acouphène', 'en' => 'Tinnitus', 'ar' => 'طنين الأذن'], 'default_duration_months' => 3, 'description' => ['fr' => 'Bourdonnements, sons permanents', 'en' => 'Ringing, permanent sounds', 'ar' => 'أزيز، أصوات دائمة']],
                    ['code' => '425', 'label' => ['fr' => 'Céphalée-migraine', 'en' => 'Headache / migraine', 'ar' => 'صداع وشقيقة'], 'default_duration_months' => 3, 'description' => ['fr' => 'Mal de tête', 'en' => 'Headache', 'ar' => 'ألم في الرأس']],
                    ['code' => '426', 'label' => ['fr' => 'Polyarthrite', 'en' => 'Polyarthritis', 'ar' => 'التهاب المفاصل المتعدد'], 'default_duration_months' => 3, 'description' => ['fr' => 'Douleurs de l’ensemble des articulations, genoux, épaules et autres', 'en' => 'Pain across multiple joints — knees, shoulders, and others', 'ar' => 'آلام في جميع المفاصل، الركبتين والكتفين وغيرها']],
                    ['code' => '427', 'label' => ['fr' => 'Douleur inexpliquée', 'en' => 'Unexplained pain', 'ar' => 'ألم غير مبرَّر'], 'default_duration_months' => 3, 'description' => ['fr' => 'Autres que la tête, le ventre et le bas-ventre', 'en' => 'Other than the head, abdomen, and lower abdomen', 'ar' => 'غير ألم الرأس والبطن وأسفل البطن']],
                    ['code' => '428', 'label' => ['fr' => 'Coma', 'en' => 'Coma', 'ar' => 'غيبوبة'], 'default_duration_months' => 3, 'description' => ['fr' => 'Inexpliqué et prolongé', 'en' => 'Unexplained and prolonged', 'ar' => 'غير مبرَّرة وطويلة']],
                    ['code' => '429', 'label' => ['fr' => 'Autres', 'en' => 'Other', 'ar' => 'أخرى'], 'default_duration_months' => 6, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                ],
            ],
            5 => [
                'code' => '5',
                'label' => ['fr' => 'Maladies infectieuses', 'en' => 'Infectious diseases', 'ar' => 'الأمراض المعدية'],
                'icon' => 'mdi-virus',
                'type' => $illness,
                'order' => 7,
                'diseases' => [
                    ['code' => '501', 'label' => ['fr' => 'Infection persistante', 'en' => 'Persistent infection', 'ar' => 'عدوى مستمرة'], 'default_duration_months' => 3, 'description' => ['fr' => 'Du sang, des poumons, du foie ou autre', 'en' => 'Of the blood, lungs, liver, or elsewhere', 'ar' => 'في الدم أو الرئتين أو الكبد أو غيرها']],
                    ['code' => '502', 'label' => ['fr' => 'Tuberculose persistante', 'en' => 'Persistent tuberculosis', 'ar' => 'سل مستمر'], 'default_duration_months' => 3, 'description' => ['fr' => 'Les médecins ont détecté la bactérie, mais la maladie ne part pas malgré le traitement', 'en' => 'Doctors detected the bacteria, but the disease does not go away despite treatment', 'ar' => 'اكتشف الأطباء البكتيريا، لكن المرض لا يزول رغم العلاج']],
                    ['code' => '503', 'label' => ['fr' => 'Tuberculose non détectée', 'en' => 'Undetected tuberculosis', 'ar' => 'سل غير مكتشَف'], 'default_duration_months' => 3, 'description' => ['fr' => 'Les médecins n’ont rien trouvé, mais ont déclaré que c’est la tuberculose à cause des symptômes', 'en' => 'Doctors found nothing, but declared it tuberculosis based on symptoms', 'ar' => 'لم يجد الأطباء شيئاً، لكنهم قالوا إنه السل بسبب الأعراض']],
                    ['code' => '504', 'label' => ['fr' => 'Fièvre typhoïde', 'en' => 'Typhoid fever', 'ar' => 'حمى التيفوئيد'], 'default_duration_months' => 3, 'description' => ['fr' => 'Dans 40% des cas, la bactérie n’est pas détectable', 'en' => 'In 40% of cases, the bacteria is not detectable', 'ar' => 'في 40% من الحالات لا يمكن اكتشاف البكتيريا']],
                    ['code' => '505', 'label' => ['fr' => 'Inflammation non spécifiée', 'en' => 'Unspecified inflammation', 'ar' => 'التهاب غير محدَّد'], 'default_duration_months' => 3, 'description' => ['fr' => 'Les médecins voient l’inflammation qui cause la douleur du patient mais ne trouvent aucune explication', 'en' => 'Doctors see the inflammation causing the patient\'s pain but find no explanation', 'ar' => 'يرى الأطباء الالتهاب الذي يسبب ألم المريض لكنهم لا يجدون له تفسيراً']],
                    ['code' => '506', 'label' => ['fr' => 'Mort lente', 'en' => 'Slow death', 'ar' => 'الموت البطيء'], 'default_duration_months' => 3, 'description' => ['fr' => 'Le patient perd sa mobilité, son appétit et la parole sans raison médicale jusqu’à la mort', 'en' => 'The patient loses mobility, appetite, and speech for no medical reason, until death', 'ar' => 'يفقد المريض حركته وشهيته وكلامه دون سبب طبي حتى الوفاة']],
                ],
            ],
            6 => [
                'code' => '6',
                'label' => ['fr' => 'La poitrine et les yeux', 'en' => 'Chest and eyes', 'ar' => 'الصدر والعينان'],
                'icon' => 'mdi-eye-outline',
                'type' => $illness,
                'order' => 8,
                'diseases' => [
                    ['code' => '601', 'label' => ['fr' => 'Poitrine serrée', 'en' => 'Tight chest', 'ar' => 'ضيق الصدر'], 'default_duration_months' => 3, 'description' => ['fr' => 'palpitation, difficulté à respirer, douleur au cœur ou à la poitrine', 'en' => 'Palpitations, difficulty breathing, heart or chest pain', 'ar' => 'خفقان، صعوبة في التنفس، ألم في القلب أو الصدر']],
                    ['code' => '602', 'label' => ['fr' => 'Toux persistante', 'en' => 'Persistent cough', 'ar' => 'سعال مستمر'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '603', 'label' => ['fr' => 'Kystes', 'en' => 'Cysts', 'ar' => 'أكياس'], 'default_duration_months' => 6, 'description' => ['fr' => 'dans les seins ou les aisselles', 'en' => 'In the breasts or armpits', 'ar' => 'في الثديين أو الإبطين']],
                    ['code' => '604', 'label' => ['fr' => 'Eau dans les poumons', 'en' => 'Water in the lungs', 'ar' => 'ماء في الرئتين'], 'default_duration_months' => 3, 'description' => ['fr' => 'Entraînant l’asphyxie comme une noyade', 'en' => 'Causing suffocation, like drowning', 'ar' => 'يؤدي إلى الاختناق كالغرق']],
                    ['code' => '605', 'label' => ['fr' => 'Baisse de la vision', 'en' => 'Vision decline', 'ar' => 'ضعف البصر'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '606', 'label' => ['fr' => 'Glaucome', 'en' => 'Glaucoma', 'ar' => 'الزَّرَق (الغلوكوما)'], 'default_duration_months' => 6, 'description' => ['fr' => 'Le glaucome est une dégénérescence irréversible du nerf optique. Nous n’avons jamais pu le guérir, mais nous essayons de stopper sa progression', 'en' => 'Glaucoma is an irreversible degeneration of the optic nerve. We have never been able to cure it, but we try to stop its progression', 'ar' => 'الزرق تلف لا رجعة فيه للعصب البصري. لم نتمكن قط من شفائه، لكننا نحاول إيقاف تقدّمه']],
                    ['code' => '607', 'label' => ['fr' => 'Perte de la vision', 'en' => 'Vision loss', 'ar' => 'فقدان البصر'], 'default_duration_months' => 6, 'description' => ['fr' => 'Décollement de la rétine, voile sur les yeux, autre raison', 'en' => 'Retinal detachment, film over the eyes, or other cause', 'ar' => 'انفصال الشبكية، غشاوة على العينين، سبب آخر']],
                    ['code' => '608', 'label' => ['fr' => 'Cécité inexpliquée', 'en' => 'Unexplained blindness', 'ar' => 'عمى غير مبرَّر'], 'default_duration_months' => 6, 'description' => ['fr' => 'L’œil est totalement sain, mais la personne ne voit pas', 'en' => 'The eye is completely healthy, but the person cannot see', 'ar' => 'العين سليمة تماماً، لكن الشخص لا يرى']],
                    ['code' => '609', 'label' => ['fr' => 'Autres', 'en' => 'Other', 'ar' => 'أخرى'], 'default_duration_months' => 6, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                ],
            ],
            7 => [
                'code' => '7',
                'label' => ['fr' => 'Maladies héréditaires et cancer', 'en' => 'Hereditary diseases and cancer', 'ar' => 'الأمراض الوراثية والسرطان'],
                'icon' => 'mdi-dna',
                'type' => $illness,
                'order' => 9,
                'diseases' => [
                    ['code' => '701', 'label' => ['fr' => 'Drépanocytose', 'en' => 'Sickle cell disease', 'ar' => 'فقر الدم المنجلي'], 'default_duration_months' => 6, 'description' => ['fr' => 'Maladie héréditaire des globules rouges', 'en' => 'Hereditary disease of the red blood cells', 'ar' => 'مرض وراثي في كريات الدم الحمراء']],
                    ['code' => '702', 'label' => ['fr' => 'Hémophilie', 'en' => 'Hemophilia', 'ar' => 'الناعور (الهيموفيليا)'], 'default_duration_months' => 6, 'description' => ['fr' => 'Le sang ne coagule pas, les coupures se cicatrisent très difficilement', 'en' => 'Blood does not clot, cuts heal with great difficulty', 'ar' => 'الدم لا يتخثّر، والجروح تلتئم بصعوبة كبيرة']],
                    ['code' => '703', 'label' => ['fr' => 'Maladies orphelines', 'en' => 'Rare/orphan diseases', 'ar' => 'الأمراض اليتيمة'], 'default_duration_months' => 6, 'description' => ['fr' => 'Maladies rares touchant moins de 5 personnes sur 10 000 et ne bénéficiant pas de traitement efficace', 'en' => 'Rare diseases affecting fewer than 5 in 10,000 people, with no effective treatment', 'ar' => 'أمراض نادرة تصيب أقل من 5 أشخاص من كل 10,000 ولا يتوفر لها علاج فعّال']],
                    ['code' => '704', 'label' => ['fr' => 'Autre héréditaire', 'en' => 'Other hereditary condition', 'ar' => 'أمراض وراثية أخرى'], 'default_duration_months' => 6, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '705', 'label' => ['fr' => 'Cancer début', 'en' => 'Cancer — early stage', 'ar' => 'سرطان في بدايته'], 'default_duration_months' => 6, 'description' => ['fr' => 'Le patient n’a pas commencé de traitement médical', 'en' => 'The patient has not started medical treatment', 'ar' => 'لم يبدأ المريض العلاج الطبي']],
                    ['code' => '706', 'label' => ['fr' => 'Cancer en traitement', 'en' => 'Cancer — in treatment', 'ar' => 'سرطان قيد العلاج'], 'default_duration_months' => 6, 'description' => ['fr' => 'Le protocole médical a des chances raisonnables de réussir', 'en' => 'The medical protocol has a reasonable chance of success', 'ar' => 'للبروتوكول الطبي فرص معقولة للنجاح']],
                    ['code' => '707', 'label' => ['fr' => 'Cancer dominant', 'en' => 'Cancer — advanced', 'ar' => 'سرطان متقدّم'], 'default_duration_months' => 6, 'description' => ['fr' => 'La médecine n’a plus de solution', 'en' => 'Medicine has no further solution', 'ar' => 'لم يعد لدى الطب حل']],
                    ['code' => '708', 'label' => ['fr' => 'Toute autre', 'en' => 'Any other', 'ar' => 'أي مرض آخر'], 'default_duration_months' => 6, 'description' => ['fr' => 'Maladie n’entrant dans aucune catégorie précédente', 'en' => 'Disease not falling into any of the previous categories', 'ar' => 'مرض لا يندرج في أي فئة سابقة']],
                ],
            ],
            8 => [
                'code' => '8',
                'label' => ['fr' => 'Blocages', 'en' => 'Blockages', 'ar' => 'التعطيلات'],
                'icon' => 'mdi-lock-outline',
                'type' => $blockage,
                'order' => 1,
                'diseases' => [
                    ['code' => '801', 'label' => ['fr' => 'Travail', 'en' => 'Work', 'ar' => 'العمل'], 'default_duration_months' => 3, 'description' => [
                        'fr' => 'Pas de travail : le patient ne trouve pas de travail ou le travail ne dure pas; Travail médiocre : le travail est sous la qualification ou le patient est exploité; Stagnation au travail : pas de promotion, les nouveaux le dépassent, le travail ne se développe pas; Problèmes avec les supérieurs : ils bloquent la carrière du patient; Collègues ou employés : ils créent des problèmes au patient; Problèmes au travail : machines, clients, paiements…',
                        'en' => 'No job: the patient cannot find a job, or a job never lasts; Poor-quality job: the job is below their qualification, or the patient is exploited; Career stagnation: no promotion, newcomers overtake them, the job does not develop; Problems with superiors: they block the patient\'s career; Colleagues or staff: they create problems for the patient; Problems at work: machinery, clients, payments…',
                        'ar' => 'لا عمل: لا يجد المريض عملاً أو لا يدوم العمل؛ عمل متواضع: العمل أدنى من المؤهلات أو المريض مستغَل؛ ركود في العمل: لا ترقية، الجدد يتجاوزونه، العمل لا يتطور؛ مشاكل مع الرؤساء: يعرقلون مسيرة المريض المهنية؛ الزملاء أو الموظفون: يسببون المشاكل للمريض؛ مشاكل في العمل: آلات، زبائن، مدفوعات…',
                    ]],
                    ['code' => '802', 'label' => ['fr' => 'Argent', 'en' => 'Money', 'ar' => 'المال'], 'default_duration_months' => 3, 'description' => [
                        'fr' => 'Argent dépensé inutilement : le patient va tout dépenser dès qu\'il touche l\'argent; Argent pris par les problèmes : des imprévus surgissent dès que l\'argent vient; Argent disparaît : des poches ou de la maison; Revenus instables : quand il a une somme, il ne peut plus rien gagner jusqu\'à la finir',
                        'en' => 'Money spent needlessly: the patient spends everything as soon as they receive money; Money taken by problems: unexpected expenses arise as soon as money comes in; Money disappears: from pockets or from the house; Unstable income: once they have a sum, they cannot earn anything more until it runs out',
                        'ar' => 'مال يُنفق بلا فائدة: ينفق المريض كل شيء بمجرد أن يقبض المال؛ مال تأخذه المشاكل: تظهر الطوارئ بمجرد مجيء المال؛ المال يختفي: من الجيوب أو من البيت؛ دخل غير مستقر: حين يملك مبلغاً لا يستطيع كسب شيء حتى ينفقه',
                    ]],
                    ['code' => '803', 'label' => ['fr' => 'Administratif', 'en' => 'Administrative', 'ar' => 'الإجراءات الإدارية'], 'default_duration_months' => 6, 'description' => [
                        'fr' => 'Papiers; Voyage; Inscription; Démarches',
                        'en' => 'Paperwork; Travel; Registration; Procedures',
                        'ar' => 'الأوراق؛ السفر؛ التسجيل؛ المعاملات',
                    ]],
                    ['code' => '804', 'label' => ['fr' => 'Mariage', 'en' => 'Marriage', 'ar' => 'الزواج'], 'default_duration_months' => 12, 'description' => [
                        'fr' => 'Désintérêt général : personne ne s\'intéresse au patient; Prétendants inadéquats : seules les personnes inadéquates viennent; Rejet familial : la personne n\'est pas acceptée par la famille; Rupture de la relation : la relation coupe après un temps, après les rapports, quand ils parlent de mariage, ou à l\'approche du mariage; Divorces répétés : le divorce survient chaque fois',
                        'en' => 'General lack of interest: no one shows interest in the patient; Unsuitable suitors: only unsuitable people come forward; Family rejection: the person is not accepted by the family; Relationship breakdown: the relationship breaks off after a while, after intimacy, when marriage is discussed, or as marriage approaches; Repeated divorces: divorce happens every time',
                        'ar' => 'عدم اهتمام عام: لا أحد يهتم بالمريض؛ خُطّاب غير مناسبين: لا يتقدم إلا غير المناسبين؛ رفض عائلي: لا تقبل العائلة الشخص؛ انقطاع العلاقة: تنقطع العلاقة بعد مدة، أو بعد المعاشرة، أو عند الحديث عن الزواج، أو مع اقترابه؛ طلاق متكرر: يحدث الطلاق في كل مرة',
                    ]],
                ],
            ],
            10 => [
                'code' => '0',
                'label' => ['fr' => 'Symboles', 'en' => 'Symbols', 'ar' => 'الرموز'],
                'icon' => 'mdi-shape-outline',
                'type' => $symbol,
                'order' => 2,
                // Real content provided by the user (2026-08-24) — a list
                // of symbols seen/reported during sessions, not a
                // placeholder (unlike the former "Cauchemars" category
                // this one replaces, which never had real source content
                // and has been removed).
                'diseases' => [
                    ['code' => '001', 'label' => ['fr' => 'Cadenas', 'en' => 'Padlock', 'ar' => 'قفل'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '002', 'label' => ['fr' => 'Rivière', 'en' => 'River', 'ar' => 'نهر'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '003', 'label' => ['fr' => 'Arbre', 'en' => 'Tree', 'ar' => 'شجرة'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '004', 'label' => ['fr' => 'Trou', 'en' => 'Hole', 'ar' => 'حفرة'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '005', 'label' => ['fr' => 'Pont', 'en' => 'Bridge', 'ar' => 'جسر'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '006', 'label' => ['fr' => 'Puits', 'en' => 'Well', 'ar' => 'بئر'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '007', 'label' => ['fr' => 'Cimetière', 'en' => 'Cemetery', 'ar' => 'مقبرة'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '008', 'label' => ['fr' => 'Chaussure', 'en' => 'Shoe', 'ar' => 'حذاء'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '009', 'label' => ['fr' => 'Terre + pas', 'en' => 'Soil + footprint', 'ar' => 'تراب + أثر قدم'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '010', 'label' => ['fr' => 'Habit', 'en' => 'Garment', 'ar' => 'ثوب'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '011', 'label' => ['fr' => 'Cheveux', 'en' => 'Hair', 'ar' => 'شعر'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '012', 'label' => ['fr' => 'Lettre', 'en' => 'Letter', 'ar' => 'رسالة'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '013', 'label' => ['fr' => 'Charbon', 'en' => 'Coal', 'ar' => 'فحم'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '014', 'label' => ['fr' => 'Braise', 'en' => 'Ember', 'ar' => 'جمر'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '015', 'label' => ['fr' => 'Etoile', 'en' => 'Star', 'ar' => 'نجمة'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '016', 'label' => ['fr' => 'Trombone', 'en' => 'Paperclip', 'ar' => 'مشبك ورق'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '017', 'label' => ['fr' => 'Canari', 'en' => 'Clay jar', 'ar' => 'جرّة فخارية'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '018', 'label' => ['fr' => 'Cola coupé', 'en' => 'Cut kola nut', 'ar' => 'جوز الكولا مقطوع'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '019', 'label' => ['fr' => 'Cola', 'en' => 'Kola nut', 'ar' => 'جوز الكولا'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '020', 'label' => ['fr' => 'Poupée piqués', 'en' => 'Pierced doll', 'ar' => 'دمية مغروزة بالإبر'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '021', 'label' => ['fr' => '3 clous', 'en' => '3 nails', 'ar' => '3 مسامير'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '022', 'label' => ['fr' => 'Sang des ventouses', 'en' => 'Blood from cupping', 'ar' => 'دم الحجامة'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '023', 'label' => ['fr' => 'Sang des règles', 'en' => 'Menstrual blood', 'ar' => 'دم الحيض'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '024', 'label' => ['fr' => 'Cadavres d’animaux', 'en' => 'Animal carcasses', 'ar' => 'جيف حيوانات'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '025', 'label' => ['fr' => 'Poils de chien', 'en' => 'Dog hair', 'ar' => 'شعر كلب'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '026', 'label' => ['fr' => 'Poisson', 'en' => 'Fish', 'ar' => 'سمكة'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '027', 'label' => ['fr' => '3 os', 'en' => '3 bones', 'ar' => '3 عظام'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '028', 'label' => ['fr' => 'Excrément', 'en' => 'Excrement', 'ar' => 'براز'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '029', 'label' => ['fr' => 'Ane', 'en' => 'Donkey', 'ar' => 'حمار'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '030', 'label' => ['fr' => 'Slip', 'en' => 'Underwear', 'ar' => 'لباس داخلي'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '031', 'label' => ['fr' => 'Poils de pubis', 'en' => 'Pubic hair', 'ar' => 'شعر العانة'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '032', 'label' => ['fr' => 'Termite', 'en' => 'Termite', 'ar' => 'أَرَضة'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '033', 'label' => ['fr' => 'Piment', 'en' => 'Chili pepper', 'ar' => 'فلفل حار'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '034', 'label' => ['fr' => 'Folie', 'en' => 'Madness', 'ar' => 'جنون'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '035', 'label' => ['fr' => 'Sacrifice', 'en' => 'Sacrifice', 'ar' => 'ذبيحة'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '036', 'label' => ['fr' => 'Statue', 'en' => 'Statue', 'ar' => 'تمثال'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '037', 'label' => ['fr' => 'Revivification', 'en' => 'Revivification', 'ar' => 'إحياء'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '038', 'label' => ['fr' => 'Peau', 'en' => 'Skin', 'ar' => 'جلد'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '039', 'label' => ['fr' => 'Faiblesse mentale', 'en' => 'Mental weakness', 'ar' => 'ضعف عقلي'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '040', 'label' => ['fr' => 'Oubli', 'en' => 'Forgetfulness', 'ar' => 'نسيان'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '041', 'label' => ['fr' => 'Bruits', 'en' => 'Noises', 'ar' => 'أصوات'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '042', 'label' => ['fr' => 'Bourdonnement', 'en' => 'Buzzing', 'ar' => 'طنين'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '043', 'label' => ['fr' => 'Présences dans la maison', 'en' => 'Presences in the house', 'ar' => 'حضور في البيت'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '044', 'label' => ['fr' => 'Passions', 'en' => 'Passions', 'ar' => 'شهوات'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '045', 'label' => ['fr' => 'Maladie des yeux', 'en' => 'Eye ailment', 'ar' => 'مرض العيون'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '046', 'label' => ['fr' => 'Stérilité', 'en' => 'Sterility', 'ar' => 'عقم'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '047', 'label' => ['fr' => 'Froid', 'en' => 'Cold', 'ar' => 'برد'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '048', 'label' => ['fr' => 'Peur', 'en' => 'Fear', 'ar' => 'خوف'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '049', 'label' => ['fr' => 'Nœuds', 'en' => 'Knots', 'ar' => 'عُقد'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '050', 'label' => ['fr' => 'Fil 11 Nœuds', 'en' => 'Thread with 11 knots', 'ar' => 'خيط بإحدى عشرة عقدة'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '051', 'label' => ['fr' => 'Eau', 'en' => 'Water', 'ar' => 'ماء'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '052', 'label' => ['fr' => 'Trace de pas', 'en' => 'Footprint', 'ar' => 'أثر قدم'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '053', 'label' => ['fr' => 'Ecriture', 'en' => 'Writing', 'ar' => 'كتابة'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '054', 'label' => ['fr' => 'Goudron', 'en' => 'Tar', 'ar' => 'قطران'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '055', 'label' => ['fr' => 'Feu', 'en' => 'Fire', 'ar' => 'نار'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '056', 'label' => ['fr' => 'Colère', 'en' => 'Anger', 'ar' => 'غضب'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '057', 'label' => ['fr' => 'Os', 'en' => 'Bone', 'ar' => 'عظم'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '058', 'label' => ['fr' => 'Perte d’argent', 'en' => 'Loss of money', 'ar' => 'فقدان المال'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '059', 'label' => ['fr' => 'Passion', 'en' => 'Passion', 'ar' => 'شهوة'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '060', 'label' => ['fr' => 'Présences', 'en' => 'Presences', 'ar' => 'حضور'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '061', 'label' => ['fr' => 'Attaques de djinns', 'en' => 'Jinn attacks', 'ar' => 'هجمات الجن'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '062', 'label' => ['fr' => 'Attaques de sorciers', 'en' => 'Sorcerer attacks', 'ar' => 'هجمات السحرة'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '063', 'label' => ['fr' => 'Araignée', 'en' => 'Spider', 'ar' => 'عنكبوت'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '064', 'label' => ['fr' => 'Insomnie', 'en' => 'Insomnia', 'ar' => 'أرق'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '065', 'label' => ['fr' => 'Vertige', 'en' => 'Dizziness', 'ar' => 'دوار'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '066', 'label' => ['fr' => 'Chien', 'en' => 'Dog', 'ar' => 'كلب'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '067', 'label' => ['fr' => 'Fatigue', 'en' => 'Fatigue', 'ar' => 'تعب'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '068', 'label' => ['fr' => 'Photo', 'en' => 'Photo', 'ar' => 'صورة'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                    ['code' => '069', 'label' => ['fr' => 'Autres symboles', 'en' => 'Other symbols', 'ar' => 'رموز أخرى'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                ],
            ],
        ];

        // Blockage sub-cases (801-804) — see the per-entry confidence
        // note in this class's docblock.
        $subcasesByDiseaseCode = [
            '801' => [
                ['label' => ['fr' => 'Pas de travail', 'en' => 'No job', 'ar' => 'لا عمل'], 'description' => ['fr' => 'Le patient ne trouve pas de travail ou le travail ne dure pas', 'en' => 'The patient cannot find a job, or a job never lasts', 'ar' => 'لا يجد المريض عملاً أو لا يدوم العمل']],
                ['label' => ['fr' => 'Travail médiocre', 'en' => 'Poor-quality job', 'ar' => 'عمل متواضع'], 'description' => ['fr' => 'Le travail est sous la qualification ou le patient est exploité', 'en' => 'The job is below their qualification, or the patient is exploited', 'ar' => 'العمل أدنى من المؤهلات أو المريض مستغَل']],
                ['label' => ['fr' => 'Stagnation au travail', 'en' => 'Career stagnation', 'ar' => 'ركود في العمل'], 'description' => ['fr' => 'Pas de promotion, les nouveaux le dépassent, le travail ne se développe pas', 'en' => 'No promotion, newcomers overtake them, the job does not develop', 'ar' => 'لا ترقية، الجدد يتجاوزونه، العمل لا يتطور']],
                ['label' => ['fr' => 'Problèmes avec les supérieurs', 'en' => 'Problems with superiors', 'ar' => 'مشاكل مع الرؤساء'], 'description' => ['fr' => 'Ils bloquent la carrière du patient', 'en' => 'They block the patient\'s career', 'ar' => 'يعرقلون مسيرة المريض المهنية']],
                ['label' => ['fr' => 'Collègues ou employés', 'en' => 'Colleagues or staff', 'ar' => 'الزملاء أو الموظفون'], 'description' => ['fr' => 'Ils créent des problèmes au patient', 'en' => 'They create problems for the patient', 'ar' => 'يسببون المشاكل للمريض']],
                ['label' => ['fr' => 'Problèmes au travail', 'en' => 'Problems at work', 'ar' => 'مشاكل في العمل'], 'description' => ['fr' => 'Machines, clients, paiements…', 'en' => 'Machinery, clients, payments…', 'ar' => 'آلات، زبائن، مدفوعات…']],
            ],
            '802' => [
                ['label' => ['fr' => 'Argent dépensé inutilement', 'en' => 'Money spent needlessly', 'ar' => 'مال يُنفق بلا فائدة'], 'description' => ['fr' => 'Le patient va tout dépenser dès qu\'il touche l\'argent', 'en' => 'The patient spends everything as soon as they receive money', 'ar' => 'ينفق المريض كل شيء بمجرد أن يقبض المال']],
                ['label' => ['fr' => 'Argent pris par les problèmes', 'en' => 'Money taken by problems', 'ar' => 'مال تأخذه المشاكل'], 'description' => ['fr' => 'Des imprévus surgissent dès que l\'argent vient', 'en' => 'Unexpected expenses arise as soon as money comes in', 'ar' => 'تظهر الطوارئ بمجرد مجيء المال']],
                ['label' => ['fr' => 'Argent disparaît', 'en' => 'Money disappears', 'ar' => 'المال يختفي'], 'description' => ['fr' => 'Des poches ou de la maison', 'en' => 'From pockets or from the house', 'ar' => 'من الجيوب أو من البيت']],
                ['label' => ['fr' => 'Revenus instables', 'en' => 'Unstable income', 'ar' => 'دخل غير مستقر'], 'description' => ['fr' => 'Quand il a une somme, il ne peut plus rien gagner jusqu\'à la finir', 'en' => 'Once they have a sum, they cannot earn anything more until it runs out', 'ar' => 'حين يملك مبلغاً لا يستطيع كسب شيء حتى ينفقه']],
            ],
            '803' => [
                ['label' => ['fr' => 'Papiers', 'en' => 'Paperwork', 'ar' => 'الأوراق'], 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                ['label' => ['fr' => 'Voyage', 'en' => 'Travel', 'ar' => 'السفر'], 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                ['label' => ['fr' => 'Inscription', 'en' => 'Registration', 'ar' => 'التسجيل'], 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
                ['label' => ['fr' => 'Démarches', 'en' => 'Procedures', 'ar' => 'المعاملات'], 'description' => ['fr' => null, 'en' => null, 'ar' => null]],
            ],
            '804' => [
                ['label' => ['fr' => 'Désintérêt général', 'en' => 'General lack of interest', 'ar' => 'عدم اهتمام عام'], 'description' => ['fr' => 'Personne ne s\'intéresse au patient', 'en' => 'No one shows interest in the patient', 'ar' => 'لا أحد يهتم بالمريض']],
                ['label' => ['fr' => 'Prétendants inadéquats', 'en' => 'Unsuitable suitors', 'ar' => 'خُطّاب غير مناسبين'], 'description' => ['fr' => 'Seules les personnes inadéquates viennent', 'en' => 'Only unsuitable people come forward', 'ar' => 'لا يتقدم إلا غير المناسبين']],
                ['label' => ['fr' => 'Rejet familial', 'en' => 'Family rejection', 'ar' => 'رفض عائلي'], 'description' => ['fr' => 'La personne n\'est pas acceptée par la famille', 'en' => 'The person is not accepted by the family', 'ar' => 'لا تقبل العائلة الشخص']],
                ['label' => ['fr' => 'Rupture de la relation', 'en' => 'Relationship breakdown', 'ar' => 'انقطاع العلاقة'], 'description' => ['fr' => 'La relation coupe après un temps, après les rapports, quand ils parlent de mariage, ou à l\'approche du mariage', 'en' => 'The relationship breaks off after a while, after intimacy, when marriage is discussed, or as marriage approaches', 'ar' => 'تنقطع العلاقة بعد مدة، أو بعد المعاشرة، أو عند الحديث عن الزواج، أو مع اقترابه']],
                ['label' => ['fr' => 'Divorces répétés', 'en' => 'Repeated divorces', 'ar' => 'طلاق متكرر'], 'description' => ['fr' => 'Le divorce survient chaque fois', 'en' => 'Divorce happens every time', 'ar' => 'يحدث الطلاق في كل مرة']],
            ],
        ];

        foreach ($categories as $categoryData) {
            $category = DiseaseCategory::query()->firstOrCreate(
                ['code' => $categoryData['code']],
                [
                    'label' => $categoryData['label'],
                    'icon' => $categoryData['icon'],
                    'type_option_id' => $categoryData['type']->id,
                    'order' => $categoryData['order'],
                    'active' => true,
                ]
            );

            // icon was added after the initial seed of these categories
            // (2026-08-25) — firstOrCreate() above won't backfill it onto
            // rows that already existed, so set it explicitly on every run.
            // Cheap (9 rows), and keeps this seeder safe to re-run without
            // requiring migrate:fresh just to pick up an icon change.
            $category->update(['icon' => $categoryData['icon']]);

            foreach ($categoryData['diseases'] as $diseaseData) {
                $disease = Disease::query()->firstOrCreate(
                    ['disease_category_id' => $category->id, 'code' => $diseaseData['code']],
                    [
                        'label' => $diseaseData['label'],
                        'default_duration_months' => $diseaseData['default_duration_months'],
                        'description' => $diseaseData['description'],
                        'active' => true,
                    ]
                );

                // Blockage descriptions (801-804) were originally the
                // placeholder "Tous les cas ci-dessous" pointing at
                // disease_subcases for the real content — merged into the
                // disease's own description here (2026-08-25, per user
                // request, ahead of retiring disease_subcases once nothing
                // else reads it). firstOrCreate() above won't overwrite an
                // already-seeded row's description, so backfill explicitly
                // — scoped to just these 4 codes (not a blanket update of
                // every disease) to avoid clobbering a description an admin
                // may have since edited via the CRUD.
                if (in_array($diseaseData['code'], ['801', '802', '803', '804'], true)) {
                    $disease->update(['description' => $diseaseData['description']]);
                }

                foreach ($subcasesByDiseaseCode[$diseaseData['code']] ?? [] as $order => $subcaseData) {
                    DiseaseSubcase::query()->firstOrCreate(
                        ['disease_id' => $disease->id, 'label->fr' => $subcaseData['label']['fr']],
                        [
                            'label' => $subcaseData['label'],
                            'description' => $subcaseData['description'],
                            'order' => $order + 1,
                            'active' => true,
                        ]
                    );
                }
            }
        }
    }
}
