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

## Tests

```bash
./vendor/bin/pest    # Pest (backend)
npm run test         # Vitest (composables + composants Vue)
npm run test:e2e     # Playwright — golden paths navigateur réel
```

`test:e2e` lance son propre serveur (`php artisan serve`) et pilote
`php artisan tinker` en coulisses (`tests/e2e/global-setup.ts`/
`global-teardown.ts`) pour préparer puis nettoyer ses propres données —
il tourne contre la DB de dev locale (`database/database.sqlite`), pas
une DB isolée comme Pest (sqlite `:memory:`), donc à éviter en même
temps qu'un usage manuel actif de cette DB.

## État d'avancement (2026-08-20)

**Domaines Vague 1** — voir `docs/schema-donnees.md` pour le détail complet :

| Domaine | État |
|---|---|
| Core (zones, pays, centres, grades) | Zones/pays/centres : **CRUD admin fait** (super_admin uniquement). Grades : schéma + seeders, pas encore de CRUD |
| Auth (comptes utilisateurs, notifications) | **Fait (Phase 1 + 2)** — rôles `super_admin`/`admin`/`manager`/`practitioner`, création directe ou par invitation (password broker natif Laravel), blocage compte désactivé, notifications applicatives (mail + database), comptes practitioner multi-centres avec sélecteur de centre actif |
| Practitioners (soignants, présence) | **Fait** — CRUD admin, policy, tests, accès applicatif multi-centres (Phase 2) |
| Patients (dossier, maladies, traitements) | Référentiel maladies (`DiseaseCategory`/`Disease`) et catalogue de soins (`CareCategory`/`CareItem`) : **CRUD admin fait** (9 catégories dont Cauchemars, contenu soins toujours placeholder) ; `Patient` (mono-étape) fait ; `Treatment` (wizard 3 étapes) fait ; `TreatmentSession` (CRUD, catalogue de soins, mesures libres par `EnumOption`) fait ; dossier patient unifié fait (4 onglets, dont Documents — identité/médical/autres, fusion PDF auto via `spatie/laravel-medialibrary`) ; `ExternalMedicalRecord` à faire |
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

**CRUD admin pour `EnumOption`** (2026-08-21, branche `feature/treatments`,
suite immédiate) : les options dynamiques partagées entre domaines
(`disease_category.type`, `payroll_organism.type`) ont désormais un
CRUD admin, même principe que la session précédente. Cette vérification
a révélé un **bug systémique préexistant** touchant tous les CRUD de
liste du projet (Centers, Zones, Countries, DiseaseCategories, Diseases,
CareCategories, CareItems, Patients, Practitioners, Treatments) : sur un
premier chargement de page sans aucun filtre actif dans l'URL, le prop
`filters` passé à Inertia se sérialisait en tableau JSON vide `[]`
plutôt qu'en objet `{}`, ce qui faisait résoudre `props.filters.sort`
en JS vers `Array.prototype.sort` (la méthode native) au lieu de
`undefined` — un clic sur "Filtrer" envoyait alors le code source de
cette fonction dans l'URL, provoquant une erreur 400. Corrigé partout
d'un coup (`'filters' => (object) $request->only(['filter', 'sort'])`).
Détail complet dans `CLAUDE.md` "CRUD admin pour `EnumOption`, bug
systémique...". Vérifié : 176 tests Pest (11 nouveaux, zéro
régression), `pint --test` clean, Larastan clean, build OK,
`vue-tsc --noEmit` clean, golden path navigateur réel confirmant le fix
sur `EnumOptions` et re-testé sur `Centers`.

**Adaptation visuelle inspirée d'InPACT** (2026-08-21, branche
`feature/treatments`) : identité visuelle et patterns UX du projet de
référence `Model/` (InPACT) transposés à notre stack Vuetify — palette
bleu/navy, police Poppins (shell authentifié seulement), nouveau
composant `AppPageHeader` (titre + fil d'Ariane), toolbar de liste en
carte, `AppCard` étendu (`variant`/`elevation`), regroupement visuel
"Administration" dans la nav. Portée volontairement limitée au shell
applicatif + la page `Patients` (liste + dossier patient) comme pilote
— les autres pages (Praticiens, Traitements, Centres, référentiels
admin) suivront le même pattern dans une session dédiée future. Détail
complet dans `CLAUDE.md` "Adaptation visuelle inspirée d'InPACT".
Vérifié : 176 tests Pest inchangés (aucun fichier PHP touché), 5 tests
Vitest inchangés, build Vite client+SSR OK, `vue-tsc --noEmit` clean,
golden path navigateur réel (Playwright) sur le dossier patient complet
(formulaire, carte de traitement en cours, séance), mode clair et
sombre, zéro erreur console.

**Généralisation du pattern visuel à toutes les pages** (2026-08-21,
même branche, suite immédiate) : `AppPageHeader` + toolbar-en-carte +
`AppCard` (au lieu de `<v-card>` nu) étendus aux 9 pages restantes
(Praticiens, Centres, Zones, Pays, Catégories de maladies, Maladies,
Catégories de soins, Soins, Options dynamiques, Traitements) — plus
aucune page de liste admin n'utilise l'ancien header en slot d'app-bar
ni un `<v-card>` nu. Transformation mécanique, aucun fichier PHP
touché. Vérifié : `vue-tsc --noEmit` clean, build OK, 176 tests Pest +
5 tests Vitest inchangés, golden path navigateur réel sur les 10 pages
(zéro erreur console), dialog de création re-testé pour confirmer la
compatibilité avec le nouveau header.

**Timeline verticale des séances dans le dossier patient** (2026-08-22,
branche `feature/patient-treatment-timeline`) : la liste plate des
séances d'un traitement (`Patients/Form.vue`) est remplacée par un vrai
composant timeline, `Components/Patients/TreatmentTimeline.vue`
(`v-timeline` natif Vuetify, `density="compact"`, `side="end"`) —
pastille colorée (`primary` sur la séance la plus récente, `muted`
sinon), carte `AppCard` par séance (date, durée, chips de soins,
icônes de progression par maladie), clic émettant `edit-session` pour
rouvrir `TreatmentSessionDialog.vue` (comportement inchangé). Premier
composant Vue du projet à avoir un vrai test Vitest de composant (les
sessions précédentes n'avaient testé que des composables) — a exigé
d'inliner `vuetify` dans `vite.config.ts` (`test.server.deps.inline`)
et d'activer `test.css: true`, sinon Vitest échoue à charger le CSS
co-localisé des composants Vuetify (`Unknown file extension ".css"`).
Vérifié : 176 tests Pest inchangés (aucun fichier PHP touché), 7 tests
Vitest (5 existants + 2 nouveaux), `pint --test` clean, build Vite
client+SSR OK, `vue-tsc --noEmit` clean, golden path navigateur réel
(Playwright, données de test via tinker — patient avec un traitement
`ongoing` et 3 séances) : timeline affichée avec les 3 séances, clic
sur une carte rouvrant bien le dialog pré-rempli, mode clair et sombre,
zéro erreur console.

**Navigation par onglets dans le dossier patient** (2026-08-22, branche
`feature/patient-file-tabs`) : `Patients/Form.vue` (jusqu'ici une longue
page verticale — formulaire, traitement en cours, historique, séances,
le tout empilé) réorganisé en trois onglets Vuetify (`v-tabs`+`v-window`,
nouveau wrapper `Components/App/AppTabs.vue`) — Informations / Traitement
en cours (uniquement le traitement `ongoing`, s'il existe) / Historique
(traitements clôturés + leur timeline de séances). Onglet actif piloté
par `?tab=` dans l'URL, défaut sur "Traitement en cours" si le patient en
a un, sinon "Informations". Extraction de deux composants pour éviter
toute duplication de template/logique : `Components/Patients/
PatientInfoForm.vue` (formulaire identité/contact, utilisé à la fois
dans l'onglet et pour un patient pas encore créé) et `Components/
Patients/TreatmentCard.vue` (carte de traitement, partagée entre
l'onglet "en cours" et "historique" — c'était un seul bloc dupliqué en
V-for avant cette session). Tous les dialogs existants
(`TreatmentWizardDialog`, `TreatmentSessionDialog`,
`TreatmentCloseDialog`) inchangés dans leur logique, seul leur point de
déclenchement a changé de place. Détail complet dans `CLAUDE.md`
"Navigation par onglets dans le dossier patient".

Playwright devient une vraie dépendance du projet à cette occasion
(`@playwright/test`, `playwright.config.ts`, `tests/e2e/`,
`npm run test:e2e`) — jusqu'ici réinstallé à la volée dans un scratchpad
de session à chaque vérification manuelle (voir les sessions
précédentes). Premier test E2E versionné : golden path de navigation
entre les trois onglets sur un patient avec un traitement `ongoing` et
un traitement `closed` fixture. Vérifié : 176 tests Pest inchangés
(aucun fichier PHP touché), 9 tests Vitest (7 existants + 2 nouveaux sur
`AppTabs`, a nécessité un mock `ResizeObserver` dans un nouveau
`resources/js/vitest.setup.ts` — Vuetify's `v-tabs` en a besoin même en
environnement jsdom de test), `pint --test` clean, build Vite
client+SSR OK, `vue-tsc --noEmit` clean, test E2E Playwright vert (deux
runs consécutifs pour confirmer l'idempotence du setup/teardown),
vérification navigateur manuelle complémentaire (mode clair/sombre,
zéro erreur console).

**Gestion des comptes utilisateurs Phase 1** (2026-08-25, branche
`feature/user-management-phase1`) : nouveau domaine `Auth` (jusqu'ici
seulement le modèle `User` scaffoldé par Breeze) — rôle `admin` (quasi
super_admin, même contournement team_id-sentinelle que `super_admin`,
distinction faite au niveau policy plutôt que permissions), CRUD
`UserController` (création directe avec mot de passe, ou par invitation
email réutilisant le password broker natif Laravel — pas de mécanisme
de token custom), blocage de connexion si `is_active = false`
(`AuthenticatedSessionController`, colonne déjà présente depuis une
migration antérieure jamais exploitée), notifications applicatives
(canal `database` uniquement cette phase, cloche `AppNotificationBell`
dans l'app-bar). Détail complet, décisions techniques (pourquoi le
password broker plutôt qu'un token maison, le piège de team-scoping sur
`$user->can()`, le bug de validation `password`/`prohibited` trouvé en
vérification navigateur) dans `CLAUDE.md` "Gestion des comptes
utilisateurs Phase 1". Vérifié : 217 tests Pest (1 nouveau test de
régression sur le bug de validation, zéro régression), `pint --test`
clean, Larastan niveau 5 clean, build Vite client+SSR OK, `vue-tsc
--noEmit` clean, golden path navigateur réel (Playwright) : création
d'un manager par invitation → email (best-effort, échec SMTP local
toléré et loggé sans bloquer) → lien `password.reset` → définition du
mot de passe → email auto-vérifié → connexion → notification "Nouveau
centre géré" visible dans la cloche ; compte désactivé bloqué à la
connexion avec message clair. Mot de passe seed remis à une valeur
aléatoire, données de test supprimées en fin de session.

**Gestion des comptes practitioner multi-centres Phase 2** (2026-08-25,
branche `feature/user-management-phase2`, depuis Phase 1) : rôle
`practitioner` (lecture seule sur patients/traitements, scopé par
centre comme `manager` — contrairement à `admin`/`super_admin`), un
practitioner peut être accessible sur plusieurs centres à la fois
(`User::accessibleCenterIds()`, plusieurs assignations `practitioner`
jamais remplacées). Sélecteur de centre actif dans l'app-bar
(`AppCenterSwitcher.vue`), auto-sélection sans écran de choix
obligatoire (`EnsureCenterAccess` étendu). Un seul point d'entrée de
création : le formulaire Praticiens existant gagne un toggle "Donner
un accès à l'application" avec vérification d'email en direct
(nouveau/existant/déjà pris) — email déjà lié à un praticien existant
= auto-jonction au centre courant sans dupliquer sa fiche. Détail
complet, dont deux bugs réels trouvés en vérification navigateur (un
`useForm().reset()` d'Inertia qui recalibre ses valeurs par défaut sur
le dernier submit réussi plutôt que sur l'état vide initial, et un
piège de validation `prohibited` sur des champs masqués mais toujours
soumis — le même genre de piège déjà rencontré en Phase 1, cette fois
sur deux champs distincts) dans `CLAUDE.md` "Gestion des comptes
practitioner multi-centres Phase 2". Vérifié : 235 tests Pest (1
nouveau test de régression, zéro régression), `pint --test` clean,
Larastan niveau 5 clean, build Vite client+SSR OK, `vue-tsc --noEmit`
clean, golden path navigateur réel (Playwright) : création d'un
practitioner avec accès sur Centre A → création d'un second praticien
sur Centre B avec le même email (auto-jonction confirmée en base —
aucune deuxième fiche `Practitioner` créée, mail + notification database
reçus) → connexion practitioner → sélecteur de centre visible avec les
deux centres → bascule Centre A → Centre B → liste patients change bien
selon le centre actif, reste en lecture seule (formulaire désactivé via
`<fieldset disabled>`, boutons de création/suppression masqués). Zéro
erreur console. Données de test supprimées, mot de passe seed remis à
une valeur aléatoire en fin de session.

**Mesures par séance** (2026-08-28, branche `feature/session-measurements`,
depuis `develop`) : `TreatmentSession` peut désormais porter une ou
plusieurs mesures libres (tension artérielle, glycémie, poids,
température, fréquence cardiaque...) — le type de mesure n'est pas codé
en dur, il pointe vers `EnumOption` (`enum_type =
'session_measurement_type'`), éditable depuis l'admin sans déploiement,
cinq types seedés par défaut. Branche rebasée en cours de session sur
le vrai tip de `develop` (`git fetch` initial manquant avait laissé un
`develop` local périvé de 5 commits, sans `feature/patient-documents`
pourtant déjà mergée sur GitHub) — deux conflits réels résolus
(`README.md`, `TreatmentSessionDialog.vue`), voir `CLAUDE.md` "Mesures
par séance" pour le détail complet. Vérifié après rebase : 279 tests
Pest (4 nouveaux propres à cette session, zéro régression sur
l'ensemble y compris les 21 apportés par `feature/patient-documents`),
48 tests Vitest (2 nouveaux), `pint --test` clean, Larastan niveau 5
clean, build Vite client+SSR OK, `vue-tsc --noEmit` clean. Vérification
navigateur réelle non exécutée cette session (portée resserrée, voir
`CLAUDE.md`).

**Consentement patient** (2026-08-28, branche `feature/patient-consent`,
depuis `develop` à jour) : un nouvel onglet "Consentement" dans le
dossier patient permet de recueillir la signature d'un patient
(nom + trait de signature optionnel) sur un texte versionné par type
(traitement, RGPD, droit à l'image), avec génération automatique d'un
PDF horodaté et figé (`barryvdh/laravel-dompdf`, pas `spatie/browsershot`
— demande explicite de l'utilisateur ; `browsershot` reste en dépendance
inutilisée). Contrairement aux "documents patient" (Media Library seule),
`Consent` est un vrai modèle Eloquent avec relations interrogeables
(qui a consenti, à quelle version, quand) — le PDF lui-même reste
attaché via `HasMedia`, même mécanisme que `Patient`. Un admin gère les
modèles de consentement depuis une nouvelle page `/admin/consent-templates`
(non prévue explicitement dans la consigne initiale mais nécessaire :
sans elle, aucun consentement n'est jamais recueillable) — éditer un
modèle crée toujours une nouvelle version plutôt que de modifier la
ligne existante, pour qu'un texte déjà signé par un patient ne change
jamais rétroactivement. Détail complet dans `CLAUDE.md` "Consentement
patient". Vérifié : 297 tests Pest (18 nouveaux, zéro régression),
`pint --test` clean, Larastan niveau 5 clean, build Vite client+SSR OK,
`vue-tsc --noEmit` clean, golden path navigateur réel (Playwright) :
recueil d'un consentement (signature dessinée, PDF généré et
téléchargeable, contenu du PDF inspecté — texte, signataire, date,
trait de signature tous corrects), création d'un nouveau modèle en
admin, édition d'un modèle existant (nouvelle version créée, ancienne
archivée), badge "À renouveler" affiché correctement sur le patient
après la bascule de version. Mode sombre vérifié. Zéro erreur console.
Données de test et mot de passe seed nettoyés en fin de session.

**Addendum consentement — deux sources** (2026-08-29, même branche) :
un consentement peut désormais être recueilli par signature électronique
(`digital`, comportement ci-dessus, inchangé) ou par import d'un document
papier déjà signé (`uploaded` — photo/scan, plusieurs photos fusionnées
en un seul PDF via le même service que les documents patient, aucune
génération PDF dans ce cas). `Consent.type` déplacé depuis le template
vers `Consent` lui-même (toujours requis), `consent_template_id`/
`version`/`content_snapshot` nullables. Choix libre à chaque recueil, pas
de config par centre. Détail complet dans `CLAUDE.md` "Consentement —
deux sources". Vérifié : 302 tests Pest (5 nouveaux, zéro régression),
`pint`/Larastan/build/`vue-tsc` clean, golden path navigateur réel sur
les deux sources (PDF fusionné inspecté directement, 2 pages
correspondant aux 2 photos importées). Données de test nettoyées.

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
- Documents patient : HEIC (format photo iPhone par défaut) pas encore
  accepté à l'upload — reporté, voir `CLAUDE.md` "Documents patient".
  Requiert sur l'hôte : extension PHP `imagick`, ImageMagick, et
  Ghostscript (pour les miniatures PDF) — voir
  `docs/schema-donnees.md` "Documents patient" pour le détail
  d'installation.
