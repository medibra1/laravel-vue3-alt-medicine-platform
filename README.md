# Application de gestion de centres de médecine alternative

Application multi-pays de gestion de centres de médecine alternative :
patients, soignants, rendez-vous, produits/stock, facturation, paie.
Laravel 13 / Inertia / Vue 3 / Vuetify, architecture Domain-Driven.

## Documentation

- [`docs/schema-donnees.md`](./docs/schema-donnees.md) — schéma complet
  de toutes les tables, domaine par domaine.
- [`docs/structure-ddd.md`](./docs/structure-ddd.md) — organisation des
  dossiers, conventions de code.

**Ces deux documents sont mis à jour à chaque changement de schéma ou
d'architecture — pas seulement quand un domaine est terminé.** Ce
README aussi.

## Stack

- **Backend** : Laravel 13, PHP 8.4+, MySQL 8, `spatie/laravel-permission`
  (mode teams — un manager par centre), `spatie/laravel-model-status`
  (statuts historisés), `spatie/laravel-translatable` (contenu
  multilingue), système `EnumOption`/`ModelOption` maison (options
  dynamiques, éditables en admin sans déploiement).
- **Frontend** : Vue 3 + Inertia + TypeScript + Vuetify 4 (MIT ;
  remplace PrimeVue depuis le 2026-08-19, voir `CLAUDE.md` "Migration
  PrimeVue → Vuetify"). Composants Vuetify jamais importés directement
  dans les vues métier — toujours via les wrappers
  `resources/js/Components/App/App*.vue`.
- **i18n** : FR/EN actifs, AR prévu (RTL). Contenu métier (maladies,
  pays, catégories...) traduit dès le seeding — voir
  `database/seeders/`.
- **Git** : Git flow (`main`/`develop`/`feature/*`/`release/*`/`hotfix/*`).

## Démarrage

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
```

Compléter `.env` (`SUPER_ADMIN_EMAIL`/`SUPER_ADMIN_PASSWORD` notamment),
puis :

```bash
php artisan migrate
php artisan db:seed
npm run dev
```

Vérifier après le seed : 46 pays, 9 zones, 8 catégories de maladies /
103 maladies / 19 sous-cas de blocage, en français et en anglais.

## État d'avancement (2026-08-20)

**Domaines Vague 1** — voir `docs/schema-donnees.md` pour le détail complet :

| Domaine | État |
|---|---|
| Core (zones, pays, centres, grades) | Zones/pays/centres : **CRUD admin fait** (super_admin uniquement). Grades : schéma + seeders, pas encore de CRUD |
| Practitioners (soignants, présence) | **Fait** — CRUD admin, policy, tests |
| Patients (dossier, maladies, traitements) | Référentiel maladies (`DiseaseCategory`/`Disease`) et catalogue de soins (`CareCategory`/`CareItem`) : **CRUD admin fait** (9 catégories dont Cauchemars, contenu soins toujours placeholder) ; `Patient` (mono-étape) fait ; `Treatment` (wizard 3 étapes) fait ; `TreatmentSession` (CRUD, catalogue de soins) fait ; dossier patient unifié fait ; `ExternalMedicalRecord` à faire |
| Scheduling (RDV, campagnes) | Pas commencé |
| Catalog (produits, stock) | Pas commencé |
| Billing (factures, paie) | Paie (deux modes) posée ; factures/dépenses à faire |
| Reporting (stats signées) | Pas commencé |

**Vérification Practitioners** (2026-08-19) : 36 tests Pest passent (13
spécifiques à Practitioners + 23 scaffolding Breeze), `pint --test`
clean, Larastan niveau 5 clean.

**Vérification Patient** (2026-08-19) : 51 tests Pest au total (15
spécifiques à Patients, zéro régression), 4 tests Vitest (composable
d'autosave), `pint --test` clean, Larastan niveau 5 clean, et parcours
navigateur réel vérifié (Playwright headless) : création → autosave →
confirmation → apparition dans la liste. Premier domaine à implémenter
le pattern "wizard résilient" (voir `CLAUDE.md`). Cette vérification
navigateur a aussi révélé une dérive `package.json` : `primevue`
avait glissé vers `^5.0.1` (licence payante depuis PrimeVue 5) — fix
temporaire à l'époque (refixé sur `^4.5`), remplacé depuis par la
migration Vuetify ci-dessous.

**Migration PrimeVue → Vuetify** (2026-08-19, mergée dans `develop`) :
PrimeVue entièrement retiré, remplacé par Vuetify 4.x (MIT) sur
`Practitioners` et `Patients`, via une couche de composants wrapper
(`resources/js/Components/App/App*.vue`) qui isole toute dépendance
directe à la lib UI — raisonnement complet et décisions techniques
dans `CLAUDE.md` "Migration PrimeVue → Vuetify". Vérifié : 51 tests
Pest (zéro régression backend), 4 tests Vitest, `pint --test` clean,
Larastan clean, build Vite client+SSR OK, et golden path navigateur
réel (Playwright) sur les deux domaines — login → liste → création →
autosave → confirmation → liste pour Patients, liste → dialog création
→ liste pour Practitioners. RTL posé structurellement (`dir` sur
`<html>`, table `locale.rtl` Vuetify) mais pas encore branché à un
vrai changement de langue dynamique (`vue-i18n` toujours pas câblé
côté client — dette pré-existante, pas introduite par cette session).

**Système de paie** : deux modes au choix par centre (`payroll_mode`) —
répartition d'une cagnotte par présence/coefficient (implémenté,
`PayPeriodCalculator`), ou paie conventionnelle avec charges/cotisations
(schéma posé, moteur de calcul pas encore écrit — volontairement, voir
`CLAUDE.md`).

**Redesign du shell applicatif** (2026-08-19, mergé dans `develop`) :
`AuthenticatedLayout.vue` entièrement reconstruit sur `v-app`/
`v-navigation-drawer`/`v-app-bar`/`v-footer` (fini le top-nav Breeze/
Tailwind d'origine) — mode nuit (toggle manuel soleil/lune, persistant),
sélecteur de densité Vuetify (compact/comfortable/default, façon docs
Vuetify, appliqué globalement via `v-defaults-provider`), aside
collapsible en rail avec bouton dédié (persistant), footer. Détail
complet et décisions techniques dans `CLAUDE.md` "Redesign du shell
applicatif". Vérifié : 51 tests Pest, 4 tests Vitest, `pint --test`
clean, Larastan clean, build Vite client+SSR OK, `vue-tsc --noEmit`
clean.

**Domaine Treatment** (2026-08-20, branche `feature/treatments`) :
`Treatment`/`TreatmentSession` (schéma) complètent le domaine Patients
— même pattern wizard résilient que `Patient` (autosave, draft→confirmed).
Vérifié : 67 tests Pest (16 nouveaux, zéro régression), 4 tests Vitest,
`pint --test` clean, Larastan clean, build Vite client+SSR OK,
`vue-tsc --noEmit` clean, golden path navigateur réel (Playwright) sur
les deux thèmes. Deux vrais bugs trouvés et corrigés pendant cette
vérification (pas par les tests automatisés) : un `DataCloneError`
Dexie sur le premier champ tableau (`disease_ids`) d'un formulaire
résilient du projet, et une mauvaise sérialisation JSON d'un champ
traduisible (`spatie/laravel-translatable`) envoyé à Inertia sans
résolution explicite de l'accessor. Détail complet et raisonnement
dans `CLAUDE.md` "Domaine Treatment".

**Wizard Treatment, dossier patient unifié, historique par séance**
(2026-08-20, branche `feature/treatments`, session distincte de la
première implémentation Treatment) : vrai wizard 3 étapes en modal
(Infos / Maladies avec sélection par catégorie + recherche globale /
Issue par maladie), dossier patient unifié (`Patient/Edit` affiche
désormais ses traitements et séances), suivi d'évolution par maladie
**historisé par séance** (nouvelle table
`patients_treatment_session_disease_progress`, plus un statut final
unique), catalogue de soins dynamique à 2 niveaux
(`patients_care_categories`/`patients_care_items`, contenu placeholder
— Pommade/Bain/Encens/Tisane/Verset). Nouveaux wrappers `AppStepper`/
`AppCard`, premier dossier `Components/Patients/*` pour les composants
métier composés partagés entre plusieurs pages. Vérifié : 76 tests
Pest (9 nouveaux, zéro régression), 5 tests Vitest, `pint --test`
clean, Larastan clean, build Vite client+SSR OK, `vue-tsc --noEmit`
clean, golden path navigateur réel sur le wizard et le dossier patient,
deux thèmes. Quatre vrais bugs trouvés et corrigés en vérification
(détail dans `CLAUDE.md` "Wizard Treatment..."). Stock et facturation
explicitement hors périmètre cette session (évoqués puis reportés).

**Renommage des tables Patients, CRUD Centers, matricule/patient_number
auto-générés** (2026-08-20, branche `feature/treatments`, session
distincte des précédentes) : toutes les tables du domaine Patients ont
perdu leur préfixe `patients_` (`diseases`, `disease_categories`,
`disease_subcases`, `care_categories`, `care_items`, `treatments`,
`treatment_diseases`, `treatment_sessions`,
`treatment_session_disease_progress`, `treatment_session_care_items`,
`external_medical_records`) — préfixe jugé source de confusion sans
bénéfice de collision de nom (contrairement aux autres domaines).
`practitioners.diploma_number` renommé `matricule`. Trois numéros
d'identification suivent maintenant un même schéma
pays(2)+centre(2)+séquence : `centers.code` et `practitioners.matricule`
sont **auto-suggérés mais éditables** (nouveaux endpoints `GET
admin/centers/next-code` et `GET admin/practitioners/next-matricule`),
`patients.patient_number` (nouvelle colonne, 4 chiffres) est
**auto-généré et non modifiable**. Premier CRUD admin pour `Center`
(`CenterController`/`CenterPolicy`, super_admin uniquement, nav
"Centres" conditionnelle). Détail complet, raisonnement et audit des
tables Treatment dans `CLAUDE.md` et `docs/schema-donnees.md`. Vérifié
: 89 tests Pest (13 nouveaux, zéro régression), 5 tests Vitest,
`pint --test` clean, Larastan clean, build Vite client+SSR OK,
`vue-tsc --noEmit` clean, golden path navigateur réel (Playwright) sur
les trois flux (création centre avec code auto-suggéré, création
praticien avec matricule auto-suggéré, création patient avec
patient_number auto-généré affiché dans la liste), zéro erreur console.

**CRUD admin pour les données de référence seedées** (2026-08-21,
branche `feature/treatments`) : `Zone`, `Country`, `DiseaseCategory`,
`Disease`, `CareCategory`, `CareItem` ont désormais un CRUD admin
complet (super_admin uniquement), sur le même modèle que `CenterController`
— jusqu'ici ces données n'existaient que via seeders, sans aucune UI
pour les corriger/étendre. Premier CRUD du projet à éditer des champs
traduisibles (`name`/`label`/`description`, JSON `{fr, en}` via
`spatie/laravel-translatable`) directement dans un formulaire — nouveau
wrapper `AppTranslatableInput.vue` (deux champs FR/EN côte à côte).
Permet enfin d'assigner une zone aux 9 pays orphelins depuis l'interface
plutôt que par migration. Nouveaux liens de nav (Zones, Pays, Catégories
de maladies, Maladies, Catégories de soins, Soins), tous conditionnés à
`is_super_admin` comme "Centres". Détail complet et raisonnement dans
`CLAUDE.md` "CRUD admin pour les données de référence seedées". Vérifié
: 165 tests Pest (61 nouveaux, zéro régression), `pint --test` clean,
Larastan clean, build Vite client+SSR OK, `vue-tsc --noEmit` clean,
golden path navigateur réel (Playwright) sur les six nouvelles pages
(liste, création, et la réassignation de zone d'un pays existant), zéro
erreur console.

## Points ouverts connus

- 9 pays sur 46 sans zone assignée (ambigus dans le document source) —
  voir `database/seeders/CountrySeeder.php`. **Assignable depuis
  l'admin maintenant** (`/admin/countries`), reste à trancher côté
  contenu (quelle zone pour chacun), pas côté outillage.
- Contenu placeholder à remplacer par du vrai contenu source dès qu'il
  est fourni : catégorie de maladie "Cauchemars" (2 maladies, codes
  901/902) et catalogue de soins entier (`CareCategorySeeder.php`).
- Traductions anglaises du contenu métier faites par Claude — relecture
  par un locuteur natif recommandée avant mise en prod (précision
  terminologique médicale).
- Découpage du blocage "Mariage" (804) en sous-cas fait par
  interprétation (source en prose continue, pas une liste structurée) —
  voir le docblock de `DiseaseCategorySeeder.php`.
