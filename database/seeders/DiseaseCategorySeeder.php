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
                'label' => ['fr' => 'Maladies digestives', 'en' => 'Digestive diseases'],
                'type' => $illness,
                'order' => 3,
                'diseases' => [
                    ['code' => '101', 'label' => ['fr' => 'Acidité-brûlure', 'en' => 'Acidity / heartburn'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '102', 'label' => ['fr' => 'Ulcère', 'en' => 'Ulcer'], 'default_duration_months' => 3, 'description' => ['fr' => 'Il faut que la médecine ait identifié une plaie dans l’estomac', 'en' => 'Medicine must have identified a wound in the stomach']],
                    ['code' => '103', 'label' => ['fr' => 'Constipation', 'en' => 'Constipation'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '104', 'label' => ['fr' => 'Diarrhée', 'en' => 'Diarrhea'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '105', 'label' => ['fr' => 'Ballonnement', 'en' => 'Bloating'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '106', 'label' => ['fr' => 'Hémorroïdes', 'en' => 'Hemorrhoids'], 'default_duration_months' => 3, 'description' => ['fr' => 'Interne, externe ou avec saignement', 'en' => 'Internal, external, or with bleeding']],
                    ['code' => '107', 'label' => ['fr' => 'Douleur abdominale', 'en' => 'Abdominal pain'], 'default_duration_months' => 3, 'description' => ['fr' => 'Générale ou localisée', 'en' => 'General or localized']],
                    ['code' => '108', 'label' => ['fr' => 'Prise de poids', 'en' => 'Weight gain'], 'default_duration_months' => 3, 'description' => ['fr' => 'Soit la personne mange trop, soit elle grossit dans raison', 'en' => 'Either the person eats too much, or gains weight for no reason']],
                    ['code' => '109', 'label' => ['fr' => 'Perte de poids', 'en' => 'Weight loss'], 'default_duration_months' => 3, 'description' => ['fr' => 'Soit la personne ne se nourrit plus, soit elle maigrit sans raison', 'en' => 'Either the person stops eating, or loses weight for no reason']],
                    ['code' => '110', 'label' => ['fr' => 'Plaie dans la bouche', 'en' => 'Mouth sore'], 'default_duration_months' => 3, 'description' => ['fr' => 'Ou douleurs dans la gorge', 'en' => 'Or throat pain']],
                    ['code' => '111', 'label' => ['fr' => 'Enflure de la gencive', 'en' => 'Gum swelling'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '112', 'label' => ['fr' => 'Mauvaise odeur', 'en' => 'Bad odor'], 'default_duration_months' => 3, 'description' => ['fr' => 'Se dégageant de toute partie du corps', 'en' => 'Coming from any part of the body']],
                    ['code' => '113', 'label' => ['fr' => 'Maladie de Crohn', 'en' => 'Crohn\'s disease'], 'default_duration_months' => 12, 'description' => ['fr' => 'Inflammation chronique de l’intestin ou de tout l’appareil digestif', 'en' => 'Chronic inflammation of the intestine or the entire digestive tract']],
                    ['code' => '114', 'label' => ['fr' => 'Ascite', 'en' => 'Ascites'], 'default_duration_months' => 3, 'description' => ['fr' => 'Accumulation de liquide dans le ventre', 'en' => 'Fluid buildup in the abdomen']],
                    ['code' => '115', 'label' => ['fr' => 'Infection urinaire', 'en' => 'Urinary infection'], 'default_duration_months' => 3, 'description' => ['fr' => 'Douleurs de vessie et urinaire, calculs dans la vessie', 'en' => 'Bladder and urinary pain, bladder stones']],
                    ['code' => '116', 'label' => ['fr' => 'Faiblesse des reins', 'en' => 'Kidney weakness'], 'default_duration_months' => 6, 'description' => ['fr' => 'Cela provoque une faiblesse générale mais ne nécessite pas la dialyse', 'en' => 'Causes general weakness but does not require dialysis']],
                    ['code' => '117', 'label' => ['fr' => 'Arrêt des reins', 'en' => 'Kidney failure'], 'default_duration_months' => 6, 'description' => ['fr' => 'Nécessité de dialyse et de greffe de rein', 'en' => 'Requires dialysis and kidney transplant']],
                    ['code' => '118', 'label' => ['fr' => 'Autres', 'en' => 'Other'], 'default_duration_months' => 6, 'description' => ['fr' => null, 'en' => null]],
                ],
            ],
            2 => [
                'code' => '2',
                'label' => ['fr' => 'Maladies de la peau', 'en' => 'Skin diseases'],
                'type' => $illness,
                'order' => 4,
                'diseases' => [
                    ['code' => '201', 'label' => ['fr' => 'Psoriasis', 'en' => 'Psoriasis'], 'default_duration_months' => 6, 'description' => ['fr' => 'Eczéma grave, étendu et non traitable', 'en' => 'Severe, widespread and untreatable eczema']],
                    ['code' => '202', 'label' => ['fr' => 'Eczéma', 'en' => 'Eczema'], 'default_duration_months' => 3, 'description' => ['fr' => 'Y compris démangeaisons, acné, rougeurs, boutons', 'en' => 'Including itching, acne, redness, pimples']],
                    ['code' => '203', 'label' => ['fr' => 'Plaie ouverte', 'en' => 'Open wound'], 'default_duration_months' => 3, 'description' => ['fr' => 'Souvent purulente et puante', 'en' => 'Often purulent and foul-smelling']],
                    ['code' => '204', 'label' => ['fr' => 'Abcès et boutons persistants', 'en' => 'Abscesses and persistent pimples'], 'default_duration_months' => 3, 'description' => ['fr' => 'Dont abcès anal', 'en' => 'Including anal abscess']],
                    ['code' => '205', 'label' => ['fr' => 'Enflure inexpliquée', 'en' => 'Unexplained swelling'], 'default_duration_months' => 3, 'description' => ['fr' => 'Comme les jambes d’éléphant, à distinguer des enflures dues au cancer', 'en' => 'Such as elephantiasis-like legs, to be distinguished from cancer-related swelling']],
                    ['code' => '206', 'label' => ['fr' => 'Chutes de cheveux', 'en' => 'Hair loss'], 'default_duration_months' => 6, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '207', 'label' => ['fr' => 'Chéloïde', 'en' => 'Keloid'], 'default_duration_months' => 6, 'description' => ['fr' => 'Plaie cicatrisant avec une excroissance de la peau', 'en' => 'A scar healing with a raised overgrowth of skin']],
                    ['code' => '208', 'label' => ['fr' => 'Vitiligo, Dépigmentation', 'en' => 'Vitiligo, depigmentation'], 'default_duration_months' => 6, 'description' => ['fr' => 'Nous n’avons pas pu retrouver la pigmentation, mais nous essayons de stopper l’expansion de la dépigmentation', 'en' => 'Pigmentation could not be restored, but we try to stop the depigmentation from spreading']],
                    ['code' => '209', 'label' => ['fr' => 'Autres', 'en' => 'Other'], 'default_duration_months' => 6, 'description' => ['fr' => null, 'en' => null]],
                ],
            ],
            3 => [
                'code' => '3',
                'label' => ['fr' => 'Maladies du sexe', 'en' => 'Reproductive and sexual health disorders'],
                'type' => $illness,
                'order' => 5,
                'diseases' => [
                    ['code' => '301', 'label' => ['fr' => 'Kyste', 'en' => 'Cyst'], 'default_duration_months' => 3, 'description' => ['fr' => 'Ou fibrome  dans l’ovaire ou l’utérus', 'en' => 'Or fibroid in the ovary or uterus']],
                    ['code' => '302', 'label' => ['fr' => 'Règles irrégulières', 'en' => 'Irregular periods'], 'default_duration_months' => 6, 'description' => ['fr' => 'Ainsi que saignement en dehors des menstrues', 'en' => 'As well as bleeding outside of menstruation']],
                    ['code' => '303', 'label' => ['fr' => 'Douleurs de bas-ventre', 'en' => 'Lower abdominal pain'], 'default_duration_months' => 3, 'description' => ['fr' => 'Très forte douleur pendant les règles ou en dehors, aux ovaires ou à l’utérus', 'en' => 'Very strong pain during or outside periods, in the ovaries or uterus']],
                    ['code' => '304', 'label' => ['fr' => 'Ménopause précoce', 'en' => 'Early menopause'], 'default_duration_months' => 12, 'description' => ['fr' => 'Arrêt des règles longtemps avant 50 ans', 'en' => 'Periods stop long before age 50']],
                    ['code' => '305', 'label' => ['fr' => 'Règles jamais venues', 'en' => 'Periods never started'], 'default_duration_months' => 12, 'description' => ['fr' => 'Femme adulte n’a jamais eu les règles', 'en' => 'Adult woman has never had periods']],
                    ['code' => '306', 'label' => ['fr' => 'Infertilité inexpliquée', 'en' => 'Unexplained infertility'], 'default_duration_months' => 6, 'description' => ['fr' => 'Absence de grossesse', 'en' => 'Absence of pregnancy']],
                    ['code' => '307', 'label' => ['fr' => 'Fausses couches', 'en' => 'Recurrent miscarriages'], 'default_duration_months' => 12, 'description' => ['fr' => 'Répétées, l’enfant vient après plusieurs fausses couches', 'en' => 'The child comes only after several miscarriages']],
                    ['code' => '308', 'label' => ['fr' => 'Fausses couches', 'en' => 'Systematic miscarriages'], 'default_duration_months' => 12, 'description' => ['fr' => 'Systématiques, la grossesse n’aboutit jamais', 'en' => 'The pregnancy never succeeds']],
                    ['code' => '309', 'label' => ['fr' => 'Vaginisme', 'en' => 'Vaginismus'], 'default_duration_months' => 3, 'description' => ['fr' => 'Contraction des muscles entourant le vagin conduisant à sa fermeture et l’impossibilité des rapports', 'en' => 'Contraction of the muscles surrounding the vagina, making intercourse impossible']],
                    ['code' => '310', 'label' => ['fr' => 'Sécheresse vaginale', 'en' => 'Vaginal dryness'], 'default_duration_months' => 3, 'description' => ['fr' => 'Provoquant des douleurs lors de l’acte intime', 'en' => 'Causing pain during intimacy']],
                    ['code' => '311', 'label' => ['fr' => 'Infection vaginale', 'en' => 'Vaginal infection'], 'default_duration_months' => 3, 'description' => ['fr' => 'Boutons et irritations persistants rendant l’acte pénible', 'en' => 'Persistent pimples and irritation making intercourse painful']],
                    ['code' => '312', 'label' => ['fr' => 'Frigidité', 'en' => 'Frigidity'], 'default_duration_months' => 3, 'description' => ['fr' => 'Absence de désir et de plaisir sexuels', 'en' => 'Absence of sexual desire and pleasure']],
                    ['code' => '313', 'label' => ['fr' => 'Répulsion', 'en' => 'Repulsion'], 'default_duration_months' => 3, 'description' => ['fr' => 'Colère, rejet, ne pas se supporter, refuser les rapports', 'en' => 'Anger, rejection, cannot stand the partner, refusing intercourse']],
                    ['code' => '314', 'label' => ['fr' => 'Faiblesse sexuelle', 'en' => 'Sexual weakness'], 'default_duration_months' => 3, 'description' => ['fr' => 'Perte de 50% ou plus de capacité sexuelle', 'en' => 'Loss of 50% or more of sexual capacity']],
                    ['code' => '315', 'label' => ['fr' => 'Impuissance', 'en' => 'Impotence'], 'default_duration_months' => 3, 'description' => ['fr' => 'Absence d’érection permanente ou au moment des rapports ou pendant les rapports', 'en' => 'Absence of erection, permanently or during intercourse']],
                    ['code' => '316', 'label' => ['fr' => 'Stérilité de l’homme', 'en' => 'Male sterility'], 'default_duration_months' => 6, 'description' => ['fr' => 'Absence totale de spermatozoïdes', 'en' => 'Total absence of sperm']],
                    ['code' => '317', 'label' => ['fr' => 'Stérilité de l’homme', 'en' => 'Male sterility'], 'default_duration_months' => 6, 'description' => ['fr' => 'Spermatozoïdes insuffisants en nombre ou en qualité', 'en' => 'Insufficient sperm count or quality']],
                    ['code' => '318', 'label' => ['fr' => 'Éjaculation précoce', 'en' => 'Premature ejaculation'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '319', 'label' => ['fr' => 'Autres mâle', 'en' => 'Other (male)'], 'default_duration_months' => 6, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '320', 'label' => ['fr' => 'Autres femme', 'en' => 'Other (female)'], 'default_duration_months' => 6, 'description' => ['fr' => null, 'en' => null]],
                ],
            ],
            4 => [
                'code' => '4',
                'label' => ['fr' => 'Maladies mentales et cérébrales', 'en' => 'Mental and neurological disorders'],
                'type' => $illness,
                'order' => 6,
                'diseases' => [
                    ['code' => '401', 'label' => ['fr' => 'Psychose', 'en' => 'Psychosis'], 'default_duration_months' => 3, 'description' => ['fr' => 'Perte de contact avec la réalité (folie), délires, propos absurdes', 'en' => 'Loss of contact with reality, delusions, absurd statements']],
                    ['code' => '402', 'label' => ['fr' => 'Psychose épisodique', 'en' => 'Episodic psychosis'], 'default_duration_months' => 3, 'description' => ['fr' => 'Un moment et le patient revient à la réalité', 'en' => 'A brief episode, then the patient returns to reality']],
                    ['code' => '403', 'label' => ['fr' => 'Hallucination', 'en' => 'Hallucination'], 'default_duration_months' => 3, 'description' => ['fr' => 'Visions, voix, odeurs', 'en' => 'Visions, voices, smells']],
                    ['code' => '404', 'label' => ['fr' => 'Dépression', 'en' => 'Depression'], 'default_duration_months' => 3, 'description' => ['fr' => 'Le patient abandonne ses activités par découragement et s’isole', 'en' => 'The patient abandons their activities out of discouragement and withdraws']],
                    ['code' => '405', 'label' => ['fr' => 'Anxiété', 'en' => 'Anxiety'], 'default_duration_months' => 3, 'description' => ['fr' => 'Le patient ne peut conduire ou dormir ou rester seul ou rester dans un endroit fermé ou passer un examen. Ceci inclut la sorcellerie qui empêche ces actes', 'en' => 'The patient cannot drive, sleep, stay alone, stay in an enclosed space, or sit an exam. This includes sorcery preventing these actions']],
                    ['code' => '406', 'label' => ['fr' => 'Addiction', 'en' => 'Addiction'], 'default_duration_months' => 3, 'description' => ['fr' => 'Drogues, pornographie, sexe, alcool, jeux de hasard', 'en' => 'Drugs, pornography, sex, alcohol, gambling']],
                    ['code' => '407', 'label' => ['fr' => 'Rébellion', 'en' => 'Rebellion'], 'default_duration_months' => 3, 'description' => ['fr' => 'Enfant désobéissant, vol, mensonge, fugue, violence, délinquance', 'en' => 'Disobedient child — stealing, lying, running away, violence, delinquency']],
                    ['code' => '408', 'label' => ['fr' => 'Blocage religion', 'en' => 'Religious blockage'], 'default_duration_months' => 3, 'description' => ['fr' => 'Ablutions, prière, coran, prêche, voile', 'en' => 'Ablutions, prayer, scripture, sermon, veil']],
                    ['code' => '409', 'label' => ['fr' => 'TOC', 'en' => 'OCD'], 'default_duration_months' => 6, 'description' => ['fr' => 'Troubles obsessionnels compulsifs, manies fortes et handicapantes', 'en' => 'Obsessive-compulsive disorder, severe and disabling compulsions']],
                    ['code' => '410', 'label' => ['fr' => 'Colère incontrôlée', 'en' => 'Uncontrolled anger'], 'default_duration_months' => 3, 'description' => ['fr' => 'Causant des problèmes au foyer, au travail ou dans les relations', 'en' => 'Causing problems at home, at work, or in relationships']],
                    ['code' => '411', 'label' => ['fr' => 'Instabilité émotionnelle', 'en' => 'Emotional instability'], 'default_duration_months' => 3, 'description' => ['fr' => 'Bipolarité, cyclothymie (passer rapidement et par cycle de l’exaltation à la mélancolie)', 'en' => 'Bipolarity, cyclothymia (rapid cyclical swings between elation and melancholy)']],
                    ['code' => '412', 'label' => ['fr' => 'Pb concentration', 'en' => 'Concentration problem'], 'default_duration_months' => 3, 'description' => ['fr' => 'Entraînant un blocage ou un ralentissement de l’apprentissage ou du travail ; Y compris l’incapacité qui surgit lors d’un examen ou entretien', 'en' => 'Blocks or slows down learning or work; includes an inability that arises specifically during an exam or interview']],
                    ['code' => '413', 'label' => ['fr' => 'Pb de mémoire', 'en' => 'Memory problem'], 'default_duration_months' => 3, 'description' => ['fr' => 'Difficulté d’apprentissage, de travail ou dans la vie', 'en' => 'Difficulty learning, working, or in daily life']],
                    ['code' => '414', 'label' => ['fr' => 'Manque de volonté', 'en' => 'Lack of willpower'], 'default_duration_months' => 3, 'description' => ['fr' => 'Négligence, retard,', 'en' => 'Negligence, procrastination']],
                    ['code' => '415', 'label' => ['fr' => 'Épilepsie', 'en' => 'Epilepsy'], 'default_duration_months' => 6, 'description' => ['fr' => 'Perte de contrôle du corps, mouvements, tremblements, paroles, perte de connaissance ou de conscience. On ne distingue pas l’épilepsie nerveuse et la possession', 'en' => 'Loss of body control, movements, tremors, speech, loss of consciousness. Nervous epilepsy and possession are not distinguished']],
                    ['code' => '416', 'label' => ['fr' => 'Epilepsie occasionnelle', 'en' => 'Occasional epilepsy'], 'default_duration_months' => 3, 'description' => ['fr' => 'Moins d\'une fois par semaine, ou juste pendant la séance', 'en' => 'Less than once a week, or only during a session']],
                    ['code' => '417', 'label' => ['fr' => 'Paralysie AVC', 'en' => 'Stroke-related paralysis'], 'default_duration_months' => 6, 'description' => ['fr' => 'Paralysie de la moitié du corps due à un Accident Vasculaire Cérébral 1) dû à l’arrêt de la circulation du sang dans une zone du cerveau 2) ou dû à une hémorragie intracérébrale', 'en' => 'Paralysis of one half of the body caused by a stroke — 1) blocked blood flow to a brain area, or 2) intracerebral hemorrhage']],
                    ['code' => '418', 'label' => ['fr' => 'Paralysie du visage', 'en' => 'Facial paralysis'], 'default_duration_months' => 6, 'description' => ['fr' => 'La bouche ne se ferme plus ou l’œil ou la joue reste déformée', 'en' => 'The mouth no longer closes properly, or the eye or cheek stays distorted']],
                    ['code' => '419', 'label' => ['fr' => 'Paralysie inexpliquée', 'en' => 'Unexplained paralysis'], 'default_duration_months' => 6, 'description' => ['fr' => 'D’une partie du corps', 'en' => 'Of a part of the body']],
                    ['code' => '420', 'label' => ['fr' => 'Retard mental', 'en' => 'Intellectual disability'], 'default_duration_months' => 3, 'description' => ['fr' => 'Chez l’enfant, comme s’il a plusieurs années de moins. Nous n’espérons pas la guérison des pathologies de naissance, mais un pourcentage d’amélioration (%)', 'en' => 'In a child, as if several years behind. We do not expect a cure for congenital conditions, but a percentage of improvement (%)']],
                    ['code' => '421', 'label' => ['fr' => 'Enfant handicapé', 'en' => 'Disabled child'], 'default_duration_months' => 3, 'description' => ['fr' => 'Dans la forme ou les fonctions de son corps (%)', 'en' => 'In the form or function of their body (%)']],
                    ['code' => '422', 'label' => ['fr' => 'Autisme', 'en' => 'Autism'], 'default_duration_months' => 3, 'description' => ['fr' => 'La communication de l’enfant avec notre monde est absente ou imparfaite. Hyperactivité (%)', 'en' => 'The child\'s communication with our world is absent or impaired. Hyperactivity (%)']],
                    ['code' => '423', 'label' => ['fr' => 'Sclérose en plaque', 'en' => 'Multiple sclerosis'], 'default_duration_months' => 12, 'description' => ['fr' => 'Affaiblissement du système nerveux entraînant faiblesse musculaire, trouble de l’équilibre, diminution de la sensibilité, troubles de la mémoire et de la concentration', 'en' => 'Weakening of the nervous system causing muscle weakness, balance issues, reduced sensitivity, memory and concentration problems']],
                    ['code' => '424', 'label' => ['fr' => 'Acouphène', 'en' => 'Tinnitus'], 'default_duration_months' => 3, 'description' => ['fr' => 'Bourdonnements, sons permanents', 'en' => 'Ringing, permanent sounds']],
                    ['code' => '425', 'label' => ['fr' => 'Céphalée-migraine', 'en' => 'Headache / migraine'], 'default_duration_months' => 3, 'description' => ['fr' => 'Mal de tête', 'en' => 'Headache']],
                    ['code' => '426', 'label' => ['fr' => 'Polyarthrite', 'en' => 'Polyarthritis'], 'default_duration_months' => 3, 'description' => ['fr' => 'Douleurs de l’ensemble des articulations, genoux, épaules et autres', 'en' => 'Pain across multiple joints — knees, shoulders, and others']],
                    ['code' => '427', 'label' => ['fr' => 'Douleur inexpliquée', 'en' => 'Unexplained pain'], 'default_duration_months' => 3, 'description' => ['fr' => 'Autres que la tête, le ventre et le bas-ventre', 'en' => 'Other than the head, abdomen, and lower abdomen']],
                    ['code' => '428', 'label' => ['fr' => 'Coma', 'en' => 'Coma'], 'default_duration_months' => 3, 'description' => ['fr' => 'Inexpliqué et prolongé', 'en' => 'Unexplained and prolonged']],
                    ['code' => '429', 'label' => ['fr' => 'Autres', 'en' => 'Other'], 'default_duration_months' => 6, 'description' => ['fr' => null, 'en' => null]],
                ],
            ],
            5 => [
                'code' => '5',
                'label' => ['fr' => 'Maladies infectieuses', 'en' => 'Infectious diseases'],
                'type' => $illness,
                'order' => 7,
                'diseases' => [
                    ['code' => '501', 'label' => ['fr' => 'Infection persistante', 'en' => 'Persistent infection'], 'default_duration_months' => 3, 'description' => ['fr' => 'Du sang, des poumons, du foie ou autre', 'en' => 'Of the blood, lungs, liver, or elsewhere']],
                    ['code' => '502', 'label' => ['fr' => 'Tuberculose persistante', 'en' => 'Persistent tuberculosis'], 'default_duration_months' => 3, 'description' => ['fr' => 'Les médecins ont détecté la bactérie, mais la maladie ne part pas malgré le traitement', 'en' => 'Doctors detected the bacteria, but the disease does not go away despite treatment']],
                    ['code' => '503', 'label' => ['fr' => 'Tuberculose non détectée', 'en' => 'Undetected tuberculosis'], 'default_duration_months' => 3, 'description' => ['fr' => 'Les médecins n’ont rien trouvé, mais ont déclaré que c’est la tuberculose à cause des symptômes', 'en' => 'Doctors found nothing, but declared it tuberculosis based on symptoms']],
                    ['code' => '504', 'label' => ['fr' => 'Fièvre typhoïde', 'en' => 'Typhoid fever'], 'default_duration_months' => 3, 'description' => ['fr' => 'Dans 40% des cas, la bactérie n’est pas détectable', 'en' => 'In 40% of cases, the bacteria is not detectable']],
                    ['code' => '505', 'label' => ['fr' => 'Inflammation non spécifiée', 'en' => 'Unspecified inflammation'], 'default_duration_months' => 3, 'description' => ['fr' => 'Les médecins voient l’inflammation qui cause la douleur du patient mais ne trouvent aucune explication', 'en' => 'Doctors see the inflammation causing the patient\'s pain but find no explanation']],
                    ['code' => '506', 'label' => ['fr' => 'Mort lente', 'en' => 'Slow death'], 'default_duration_months' => 3, 'description' => ['fr' => 'Le patient perd sa mobilité, son appétit et la parole sans raison médicale jusqu’à la mort', 'en' => 'The patient loses mobility, appetite, and speech for no medical reason, until death']],
                ],
            ],
            6 => [
                'code' => '6',
                'label' => ['fr' => 'La poitrine et les yeux', 'en' => 'Chest and eyes'],
                'type' => $illness,
                'order' => 8,
                'diseases' => [
                    ['code' => '601', 'label' => ['fr' => 'Poitrine serrée', 'en' => 'Tight chest'], 'default_duration_months' => 3, 'description' => ['fr' => 'palpitation, difficulté à respirer, douleur au cœur ou à la poitrine', 'en' => 'Palpitations, difficulty breathing, heart or chest pain']],
                    ['code' => '602', 'label' => ['fr' => 'Toux persistante', 'en' => 'Persistent cough'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '603', 'label' => ['fr' => 'Kystes', 'en' => 'Cysts'], 'default_duration_months' => 6, 'description' => ['fr' => 'dans les seins ou les aisselles', 'en' => 'In the breasts or armpits']],
                    ['code' => '604', 'label' => ['fr' => 'Eau dans les poumons', 'en' => 'Water in the lungs'], 'default_duration_months' => 3, 'description' => ['fr' => 'Entraînant l’asphyxie comme une noyade', 'en' => 'Causing suffocation, like drowning']],
                    ['code' => '605', 'label' => ['fr' => 'Baisse de la vision', 'en' => 'Vision decline'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '606', 'label' => ['fr' => 'Glaucome', 'en' => 'Glaucoma'], 'default_duration_months' => 6, 'description' => ['fr' => 'Le glaucome est une dégénérescence irréversible du nerf optique. Nous n’avons jamais pu le guérir, mais nous essayons de stopper sa progression', 'en' => 'Glaucoma is an irreversible degeneration of the optic nerve. We have never been able to cure it, but we try to stop its progression']],
                    ['code' => '607', 'label' => ['fr' => 'Perte de la vision', 'en' => 'Vision loss'], 'default_duration_months' => 6, 'description' => ['fr' => 'Décollement de la rétine, voile sur les yeux, autre raison', 'en' => 'Retinal detachment, film over the eyes, or other cause']],
                    ['code' => '608', 'label' => ['fr' => 'Cécité inexpliquée', 'en' => 'Unexplained blindness'], 'default_duration_months' => 6, 'description' => ['fr' => 'L’œil est totalement sain, mais la personne ne voit pas', 'en' => 'The eye is completely healthy, but the person cannot see']],
                    ['code' => '609', 'label' => ['fr' => 'Autres', 'en' => 'Other'], 'default_duration_months' => 6, 'description' => ['fr' => null, 'en' => null]],
                ],
            ],
            7 => [
                'code' => '7',
                'label' => ['fr' => 'Maladies héréditaires et cancer', 'en' => 'Hereditary diseases and cancer'],
                'type' => $illness,
                'order' => 9,
                'diseases' => [
                    ['code' => '701', 'label' => ['fr' => 'Drépanocytose', 'en' => 'Sickle cell disease'], 'default_duration_months' => 6, 'description' => ['fr' => 'Maladie héréditaire des globules rouges', 'en' => 'Hereditary disease of the red blood cells']],
                    ['code' => '702', 'label' => ['fr' => 'Hémophilie', 'en' => 'Hemophilia'], 'default_duration_months' => 6, 'description' => ['fr' => 'Le sang ne coagule pas, les coupures se cicatrisent très difficilement', 'en' => 'Blood does not clot, cuts heal with great difficulty']],
                    ['code' => '703', 'label' => ['fr' => 'Maladies orphelines', 'en' => 'Rare/orphan diseases'], 'default_duration_months' => 6, 'description' => ['fr' => 'Maladies rares touchant moins de 5 personnes sur 10 000 et ne bénéficiant pas de traitement efficace', 'en' => 'Rare diseases affecting fewer than 5 in 10,000 people, with no effective treatment']],
                    ['code' => '704', 'label' => ['fr' => 'Autre héréditaire', 'en' => 'Other hereditary condition'], 'default_duration_months' => 6, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '705', 'label' => ['fr' => 'Cancer début', 'en' => 'Cancer — early stage'], 'default_duration_months' => 6, 'description' => ['fr' => 'Le patient n’a pas commencé de traitement médical', 'en' => 'The patient has not started medical treatment']],
                    ['code' => '706', 'label' => ['fr' => 'Cancer en traitement', 'en' => 'Cancer — in treatment'], 'default_duration_months' => 6, 'description' => ['fr' => 'Le protocole médical a des chances raisonnables de réussir', 'en' => 'The medical protocol has a reasonable chance of success']],
                    ['code' => '707', 'label' => ['fr' => 'Cancer dominant', 'en' => 'Cancer — advanced'], 'default_duration_months' => 6, 'description' => ['fr' => 'La médecine n’a plus de solution', 'en' => 'Medicine has no further solution']],
                    ['code' => '708', 'label' => ['fr' => 'Toute autre', 'en' => 'Any other'], 'default_duration_months' => 6, 'description' => ['fr' => 'Maladie n’entrant dans aucune catégorie précédente', 'en' => 'Disease not falling into any of the previous categories']],
                ],
            ],
            8 => [
                'code' => '8',
                'label' => ['fr' => 'Blocages', 'en' => 'Blockages'],
                'type' => $blockage,
                'order' => 1,
                'diseases' => [
                    ['code' => '801', 'label' => ['fr' => 'Travail', 'en' => 'Work'], 'default_duration_months' => 3, 'description' => ['fr' => 'Tous les cas ci-dessous', 'en' => 'All the cases below']],
                    ['code' => '802', 'label' => ['fr' => 'Argent', 'en' => 'Money'], 'default_duration_months' => 3, 'description' => ['fr' => 'Tous les cas ci-dessous', 'en' => 'All the cases below']],
                    ['code' => '803', 'label' => ['fr' => 'Administratif', 'en' => 'Administrative'], 'default_duration_months' => 6, 'description' => ['fr' => 'Tous les cas', 'en' => 'All cases']],
                    ['code' => '804', 'label' => ['fr' => 'Mariage', 'en' => 'Marriage'], 'default_duration_months' => 12, 'description' => ['fr' => 'Tous les cas ci-dessous', 'en' => 'All the cases below']],
                ],
            ],
            10 => [
                'code' => '0',
                'label' => ['fr' => 'Symboles', 'en' => 'Symbols'],
                'type' => $symbol,
                'order' => 2,
                // Real content provided by the user (2026-08-24) — a list
                // of symbols seen/reported during sessions, not a
                // placeholder (unlike the former "Cauchemars" category
                // this one replaces, which never had real source content
                // and has been removed).
                'diseases' => [
                    ['code' => '001', 'label' => ['fr' => 'Cadenas', 'en' => 'Padlock'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '002', 'label' => ['fr' => 'Rivière', 'en' => 'River'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '003', 'label' => ['fr' => 'Arbre', 'en' => 'Tree'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '004', 'label' => ['fr' => 'Trou', 'en' => 'Hole'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '005', 'label' => ['fr' => 'Pont', 'en' => 'Bridge'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '006', 'label' => ['fr' => 'Puits', 'en' => 'Well'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '007', 'label' => ['fr' => 'Cimetière', 'en' => 'Cemetery'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '008', 'label' => ['fr' => 'Chaussure', 'en' => 'Shoe'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '009', 'label' => ['fr' => 'Terre + pas', 'en' => 'Soil + footprint'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '010', 'label' => ['fr' => 'Habit', 'en' => 'Garment'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '011', 'label' => ['fr' => 'Cheveux', 'en' => 'Hair'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '012', 'label' => ['fr' => 'Lettre', 'en' => 'Letter'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '013', 'label' => ['fr' => 'Charbon', 'en' => 'Coal'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '014', 'label' => ['fr' => 'Braise', 'en' => 'Ember'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '015', 'label' => ['fr' => 'Etoile', 'en' => 'Star'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '016', 'label' => ['fr' => 'Trombone', 'en' => 'Paperclip'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '017', 'label' => ['fr' => 'Canari', 'en' => 'Canary'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '018', 'label' => ['fr' => 'Cola coupé', 'en' => 'Cut kola nut'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '019', 'label' => ['fr' => 'Cola', 'en' => 'Kola nut'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '020', 'label' => ['fr' => 'Poupée piqués', 'en' => 'Pierced doll'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '021', 'label' => ['fr' => '3 clous', 'en' => '3 nails'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '022', 'label' => ['fr' => 'Sang des ventouses', 'en' => 'Blood from cupping'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '023', 'label' => ['fr' => 'Sang des règles', 'en' => 'Menstrual blood'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '024', 'label' => ['fr' => 'Cadavres d’animaux', 'en' => 'Animal carcasses'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '025', 'label' => ['fr' => 'Poils de chien', 'en' => 'Dog hair'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '026', 'label' => ['fr' => 'Poisson', 'en' => 'Fish'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '027', 'label' => ['fr' => '3 os', 'en' => '3 bones'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '028', 'label' => ['fr' => 'Excrément', 'en' => 'Excrement'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '029', 'label' => ['fr' => 'Ane', 'en' => 'Donkey'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '030', 'label' => ['fr' => 'Slip', 'en' => 'Underwear'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '031', 'label' => ['fr' => 'Poils de pubis', 'en' => 'Pubic hair'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '032', 'label' => ['fr' => 'Termite', 'en' => 'Termite'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '033', 'label' => ['fr' => 'Piment', 'en' => 'Chili pepper'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '034', 'label' => ['fr' => 'Folie', 'en' => 'Madness'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '035', 'label' => ['fr' => 'Sacrifice', 'en' => 'Sacrifice'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '036', 'label' => ['fr' => 'Statue', 'en' => 'Statue'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '037', 'label' => ['fr' => 'Revivification', 'en' => 'Revivification'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '038', 'label' => ['fr' => 'Peau', 'en' => 'Skin'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '039', 'label' => ['fr' => 'Faiblesse mentale', 'en' => 'Mental weakness'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '040', 'label' => ['fr' => 'Oubli', 'en' => 'Forgetfulness'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '041', 'label' => ['fr' => 'Bruits', 'en' => 'Noises'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '042', 'label' => ['fr' => 'Bourdonnement', 'en' => 'Buzzing'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '043', 'label' => ['fr' => 'Présences dans la maison', 'en' => 'Presences in the house'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '044', 'label' => ['fr' => 'Passions', 'en' => 'Passions'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '045', 'label' => ['fr' => 'Maladie des yeux', 'en' => 'Eye ailment'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '046', 'label' => ['fr' => 'Stérilité', 'en' => 'Sterility'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '047', 'label' => ['fr' => 'Froid', 'en' => 'Cold'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '048', 'label' => ['fr' => 'Peur', 'en' => 'Fear'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '049', 'label' => ['fr' => 'Nœuds', 'en' => 'Knots'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '050', 'label' => ['fr' => 'Fil 11 Nœuds', 'en' => 'Thread with 11 knots'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '051', 'label' => ['fr' => 'Eau', 'en' => 'Water'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '052', 'label' => ['fr' => 'Trace de pas', 'en' => 'Footprint'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '053', 'label' => ['fr' => 'Ecriture', 'en' => 'Writing'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '054', 'label' => ['fr' => 'Goudron', 'en' => 'Tar'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '055', 'label' => ['fr' => 'Feu', 'en' => 'Fire'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '056', 'label' => ['fr' => 'Colère', 'en' => 'Anger'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '057', 'label' => ['fr' => 'Os', 'en' => 'Bone'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '058', 'label' => ['fr' => 'Perte d’argent', 'en' => 'Loss of money'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '059', 'label' => ['fr' => 'Passion', 'en' => 'Passion'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '060', 'label' => ['fr' => 'Présences', 'en' => 'Presences'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '061', 'label' => ['fr' => 'Attaques de djinns', 'en' => 'Jinn attacks'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '062', 'label' => ['fr' => 'Attaques de sorciers', 'en' => 'Sorcerer attacks'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '063', 'label' => ['fr' => 'Araignée', 'en' => 'Spider'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '064', 'label' => ['fr' => 'Insomnie', 'en' => 'Insomnia'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '065', 'label' => ['fr' => 'Vertige', 'en' => 'Dizziness'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '066', 'label' => ['fr' => 'Chien', 'en' => 'Dog'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '067', 'label' => ['fr' => 'Fatigue', 'en' => 'Fatigue'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '068', 'label' => ['fr' => 'Photo', 'en' => 'Photo'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                    ['code' => '069', 'label' => ['fr' => 'Autres symboles', 'en' => 'Other symbols'], 'default_duration_months' => 3, 'description' => ['fr' => null, 'en' => null]],
                ],
            ],
        ];

        // Blockage sub-cases (801-804) — see the per-entry confidence
        // note in this class's docblock.
        $subcasesByDiseaseCode = [
            '801' => [
                ['label' => ['fr' => 'Pas de travail', 'en' => 'No job'], 'description' => ['fr' => 'Le patient ne trouve pas de travail ou le travail ne dure pas', 'en' => 'The patient cannot find a job, or a job never lasts']],
                ['label' => ['fr' => 'Travail médiocre', 'en' => 'Poor-quality job'], 'description' => ['fr' => 'Le travail est sous la qualification ou le patient est exploité', 'en' => 'The job is below their qualification, or the patient is exploited']],
                ['label' => ['fr' => 'Stagnation au travail', 'en' => 'Career stagnation'], 'description' => ['fr' => 'Pas de promotion, les nouveaux le dépassent, le travail ne se développe pas', 'en' => 'No promotion, newcomers overtake them, the job does not develop']],
                ['label' => ['fr' => 'Problèmes avec les supérieurs', 'en' => 'Problems with superiors'], 'description' => ['fr' => 'Ils bloquent la carrière du patient', 'en' => 'They block the patient\'s career']],
                ['label' => ['fr' => 'Collègues ou employés', 'en' => 'Colleagues or staff'], 'description' => ['fr' => 'Ils créent des problèmes au patient', 'en' => 'They create problems for the patient']],
                ['label' => ['fr' => 'Problèmes au travail', 'en' => 'Problems at work'], 'description' => ['fr' => 'Machines, clients, paiements…', 'en' => 'Machinery, clients, payments…']],
            ],
            '802' => [
                ['label' => ['fr' => 'Argent dépensé inutilement', 'en' => 'Money spent needlessly'], 'description' => ['fr' => 'Le patient va tout dépenser dès qu\'il touche l\'argent', 'en' => 'The patient spends everything as soon as they receive money']],
                ['label' => ['fr' => 'Argent pris par les problèmes', 'en' => 'Money taken by problems'], 'description' => ['fr' => 'Des imprévus surgissent dès que l\'argent vient', 'en' => 'Unexpected expenses arise as soon as money comes in']],
                ['label' => ['fr' => 'Argent disparaît', 'en' => 'Money disappears'], 'description' => ['fr' => 'Des poches ou de la maison', 'en' => 'From pockets or from the house']],
                ['label' => ['fr' => 'Revenus instables', 'en' => 'Unstable income'], 'description' => ['fr' => 'Quand il a une somme, il ne peut plus rien gagner jusqu\'à la finir', 'en' => 'Once they have a sum, they cannot earn anything more until it runs out']],
            ],
            '803' => [
                ['label' => ['fr' => 'Papiers', 'en' => 'Paperwork'], 'description' => ['fr' => null, 'en' => null]],
                ['label' => ['fr' => 'Voyage', 'en' => 'Travel'], 'description' => ['fr' => null, 'en' => null]],
                ['label' => ['fr' => 'Inscription', 'en' => 'Registration'], 'description' => ['fr' => null, 'en' => null]],
                ['label' => ['fr' => 'Démarches', 'en' => 'Procedures'], 'description' => ['fr' => null, 'en' => null]],
            ],
            '804' => [
                ['label' => ['fr' => 'Désintérêt général', 'en' => 'General lack of interest'], 'description' => ['fr' => 'Personne ne s\'intéresse au patient', 'en' => 'No one shows interest in the patient']],
                ['label' => ['fr' => 'Prétendants inadéquats', 'en' => 'Unsuitable suitors'], 'description' => ['fr' => 'Seules les personnes inadéquates viennent', 'en' => 'Only unsuitable people come forward']],
                ['label' => ['fr' => 'Rejet familial', 'en' => 'Family rejection'], 'description' => ['fr' => 'La personne n\'est pas acceptée par la famille', 'en' => 'The person is not accepted by the family']],
                ['label' => ['fr' => 'Rupture de la relation', 'en' => 'Relationship breakdown'], 'description' => ['fr' => 'La relation coupe après un temps, après les rapports, quand ils parlent de mariage, ou à l\'approche du mariage', 'en' => 'The relationship breaks off after a while, after intimacy, when marriage is discussed, or as marriage approaches']],
                ['label' => ['fr' => 'Divorces répétés', 'en' => 'Repeated divorces'], 'description' => ['fr' => 'Le divorce survient chaque fois', 'en' => 'Divorce happens every time']],
            ],
        ];

        foreach ($categories as $categoryData) {
            $category = DiseaseCategory::query()->firstOrCreate(
                ['code' => $categoryData['code']],
                [
                    'label' => $categoryData['label'],
                    'type_option_id' => $categoryData['type']->id,
                    'order' => $categoryData['order'],
                    'active' => true,
                ]
            );

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
