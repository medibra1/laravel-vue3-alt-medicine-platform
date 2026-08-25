# Schéma de données — Vague 1

Convention : tous les enregistrements "métiers créés via un wizard" portent un
`status` `draft` / `confirmed` (voir section UX). `created_by`/`updated_by`
partout où pertinent pour l'audit (`spatie/laravel-activitylog` en complément,
pas en remplacement).

---

## 1. Common (partagé par tous les domaines)

### `enum_options`
Listes de choix dynamiques, gérables en admin sans déploiement.

| Colonne | Type | Notes |
|---|---|---|
| id | bigint PK | |
| enum_type | string | ex: `disease.category`, `expense.category`, `appointment.status` |
| domain | string, nullable | pour scoper par contexte si besoin |
| code | string | valeur technique stable |
| label | json (translatable) | FR/EN/AR... |
| parent_id | fk self, nullable | options imbriquées |
| order | int | |
| active | bool | |
| properties | json, nullable | métadonnées libres |

✅ **CRUD admin implémenté (2026-08-21)** — `EnumOptionController`
(super_admin uniquement, `app/Domains/Common/Http/Controllers/Admin/`,
premier controller sous `Common`), page Inertia `Admin/EnumOptions/Index.vue`.
`enum_type` est un champ texte libre (pas une liste fixe) — cohérent
avec le principe même d'`EnumOption` : un nouveau type apparaît dès
qu'un domaine crée sa première option sous ce type, jamais par
migration. `code` unique **par `enum_type`**. `label` (JSON,
`array` cast plutôt que `HasTranslations` — voir docblock du modèle)
édité en `{fr, en}` via `AppTranslatableInput.vue`, comme les six CRUD
de la session précédente. `parent_id` non exposé dans le formulaire —
sans usage réel dans ce projet à ce jour (voir CLAUDE.md "Wizard
Treatment...").

### `model_options` (pivot polymorphe)
`model_type`, `model_id`, `option_id` (fk `enum_options`)

---

## 2. Core

### `zones`
Regroupement géographique de pays (ex. Afrique du Nord, Europe) — niveau
utilisé pour les statistiques par zone, au-dessus du pays.

id · code · name (translatable) · order · active · timestamps

✅ **CRUD admin implémenté (2026-08-21)** — `ZoneController` (super_admin
uniquement, `ZonePolicy` même pattern que `CenterPolicy`), page Inertia
`Admin/Zones/Index.vue`. Premier CRUD éditant `name` (traduisible) via
le nouveau wrapper `AppTranslatableInput.vue` (voir CLAUDE.md "CRUD
admin pour les données de référence seedées"). Pas de générateur de
code — `code` est un slug court libre, saisi directement.

### `countries`
id · zone_id (fk `zones`, nullable) · code (2 chiffres, unique) ·
name (translatable) · active · timestamps

✅ **CRUD admin implémenté (2026-08-21)** — `CountryController`
(super_admin uniquement), page Inertia `Admin/Countries/Index.vue`.
Permet d'assigner/réassigner `zone_id` (select nullable) — c'est le
besoin concret qui motivait ce lot, les 9 pays sans zone restent
assignables depuis l'admin (voir "Points ouverts" du README pour la
décision de contenu, toujours ouverte). Pas de générateur de code (le
`code` 2 chiffres est attribué par le document source, pas
auto-incrémenté).

### `centers`
id · country_id (fk) · code (2 chiffres, unique **par pays**) · name ·
city, nullable · address · phone · email · active · payroll_mode (enum
PHP `PayrollMode` : `pool_sharing`/`conventional`, défaut
`pool_sharing` — voir section 6ter) · timestamps

✅ **CRUD admin implémenté (2026-08-20)** — `CenterController` (super_admin
uniquement, `CenterPolicy` bloque tout le reste), page Inertia
`Admin/Centers/Index.vue`, nav "Centres" visible seulement pour
`is_super_admin` (nouveau prop partagé par `HandleInertiaRequests`).
`code` est **auto-suggéré** (prochain code libre dans le pays,
`CenterCodeGenerator::suggestNext()`, endpoint `GET
admin/centers/next-code`) **mais reste éditable** dans le formulaire —
certains pays attribuent déjà leurs propres numéros de centre (voir
CLAUDE.md "identifiant du compte du centre"). Validé unique par pays
côté formulaire (`StoreCenterRequest`/`UpdateCenterRequest`) en plus du
`CenterObserver` (défense en profondeur, même précédent que
`diploma_number`/`matricule` sur `practitioners`).

### `grades`
Grade du soignant (Junior/Confirmé/Senior...) — porte le `coefficient`
utilisé dans le calcul de paie (voir section 6bis). Table dédiée (pas un
`enum_option`) car elle porte des règles métier, pas juste un libellé.

id · code · label (translatable) · coefficient (décimal, multiplicateur
— ex. 1.00/1.15/1.30) · order · active · timestamps

### `users`
id · name · email (unique) · password · locale (préférence UI) ·
is_active · email_verified_at · timestamps
→ table pivot `spatie/laravel-permission` en **mode teams**, `team_id` =
`center_id` (un manager/soignant a un rôle scopé à son centre ; les
rôles `super_admin`/`admin` sont sans team réelle, assignés sous la
team sentinelle `0` — `model_has_roles.team_id` est `NOT NULL`, voir
`RolesAndPermissionsSeeder`).

✅ **Gestion des comptes (Phase 1, 2026-08-25)** — `is_active` (colonne
déjà présente depuis une migration antérieure, jamais réellement
exploitée jusqu'ici) bloque désormais la connexion
(`AuthenticatedSessionController::store()`, vérifié juste après
`Auth::attempt()` — pas via un listener sur `Attempting`, qui n'a pas
encore le `User` résolu à ce stade). Deux modes de création
(`UserController::store()`) :
- **Direct** : mot de passe choisi par l'admin, `email_verified_at`
  posé immédiatement (`now()`) — l'admin vouche pour l'adresse.
- **Invitation** : mot de passe aléatoire jetable jamais communiqué,
  `email_verified_at = null`, `WelcomeSetPasswordNotification` envoie
  un lien vers la route `password.reset` **déjà existante** (générée
  via `Password::createToken()`) — pas de colonne
  `invitation_token`/`invited_at` custom, le password broker natif de
  Laravel est réutilisé tel quel. `MarkEmailVerifiedOnPasswordReset`
  (listener sur `Illuminate\Auth\Events\PasswordReset`, enregistré
  manuellement dans `AppServiceProvider::boot()` — l'auto-discovery de
  Laravel ne scanne que `app/Listeners`, pas les sous-dossiers de
  domaine) marque l'email vérifié dès que l'utilisateur clique le lien
  et définit son mot de passe, sans étape de vérification séparée.

⚠️ **Piège de validation trouvé en vérification navigateur** — le
formulaire frontend envoie toujours `password`/`password_confirmation`
(chaînes vides en mode invitation, jamais absentes). Empiler
`'prohibited'` et `Password::defaults()` dans le même tableau de règles
ne suffit pas : `Password::defaults()` s'exécute quand même contre la
valeur vide, échec de validation silencieux (redirection 302 vers la
page précédente avec erreurs en session, aucune exception côté serveur
— long à diagnostiquer). Fix dans `StoreUserRequest::rules()` : deux
branches de règles complètement séparées selon `creation_mode`, jamais
`prohibited` et `Password::defaults()` dans le même tableau.

### `notifications` — **implémenté** (2026-08-25)
Table standard Laravel (`php artisan notifications:table`), pas
préfixée par domaine (référentiel `Common`-like, polymorphe
`notifiable_type`/`notifiable_id`). Canal `database` uniquement à ce
stade (Phase 1) — pas de mail dupliqué, pas de broadcasting temps réel.
Un seul type de notification émise pour l'instant :
`ManagerAssignedNotification` (`app/Domains/Auth/Notifications/`), à la
création/mise à jour d'un manager. Cloche `AppNotificationBell.vue`
dans l'app-bar, rafraîchie au clic (pas de polling).

### `practitioners` (soignants)
| Colonne | Type | Notes |
|---|---|---|
| id | bigint PK | |
| first_name | string | **ajouté 2026-08-21** — absent depuis la création du domaine, seul `matricule`/`full_code` identifiaient un praticien jusque-là (repéré par l'utilisateur en voyant "Praticien : 0116694" dans le dossier patient) |
| last_name | string | **ajouté 2026-08-21**, voir `first_name` |
| user_id | fk `users`, nullable | un soignant peut ne pas avoir de compte de connexion (juste référencé) |
| center_id | fk `centers` | |
| grade_id | fk `grades`, nullable | porte le coefficient utilisé dans le calcul de paie |
| matricule | string(3) | dernier segment du code — **renommé depuis `diploma_number` le 2026-08-20** (le mot "diplôme" prêtait à confusion : un matricule n'est pas toujours un vrai numéro de diplôme) |
| full_code | string(7), généré | `country.code + center.code + matricule`, unique |
| phone | string, nullable | ajouté 2026-08-21 |
| address | string, nullable | ajouté 2026-08-21 |
| email | string, nullable | ajouté 2026-08-21 |
| level | int, nullable | préparation système de niveaux/examens (vague 3) |
| hired_at | date, nullable | |
| timestamps | | |

Statut (actif/inactif) → `spatie/laravel-model-status` (`HasStatuses`),
plus l'historique associé (utile pour tracer une suspension temporaire).

Unicité en deux temps : `full_code` est unique en base (contrainte SQL,
dernier filet de sécurité), mais la validation formulaire porte sur
`matricule` scopé par `center_id`
(`Rule::unique('practitioners')->where('center_id', ...)` dans
`StorePractitionerRequest`) — c'est ce champ que l'utilisateur saisit
réellement, `full_code` étant généré. Valider le champ saisi plutôt que
le champ généré évite qu'un doublon remonte comme une exception SQL au
lieu d'une erreur de formulaire.

✅ **Auto-suggestion + édition libre (2026-08-20)** — comme
`centers.code`, `matricule` est auto-suggéré (prochain numéro libre
dans le centre, `PractitionerCodeGenerator::suggestNextMatricule()`,
endpoint `GET admin/practitioners/next-matricule`) mais reste
éditable : un manager qui a déjà un vrai numéro de diplôme/registre
peut le saisir directement à la place de la suggestion.

---

## 3. Patients

### `patients`
id · client_uuid, uuid nullable unique (généré côté client au premier
brouillon, voir plus bas) · first_name, nullable · last_name, nullable ·
gender, nullable · birth_date, nullable · phone, nullable · email,
nullable · city, nullable · country_id, nullable (résidence) ·
intake_center_id (fk `centers`, centre d'accueil initial, seul champ
métier requis dès la création du brouillon avec `created_by`) ·
patient_number, string(4) nullable, généré automatiquement (voir
plus bas) · emergency_contact_name, nullable · emergency_contact_phone,
nullable · notes, nullable · created_by (fk `users`) · timestamps ·
unique(intake_center_id, patient_number)
→ statut (draft/confirmed) via `spatie/laravel-model-status` — premier
usage réel du trait dans la codebase (voir CLAUDE.md).

✅ **`patient_number` (2026-08-20)** — 4 chiffres, auto-généré à la
création du brouillon (`PatientNumberGenerator::next()`, prochain
numéro libre dans `intake_center_id`), **jamais modifiable** ensuite
(contrairement à `matricule`/`centers.code` qui restent éditables :
l'utilisateur n'a demandé l'auto+manuel que pour ces deux-là).
L'identifiant complet affiché à l'utilisateur (ex. "01010001") est
`country.code + center.code + patient_number`, composé côté frontend
(`Index.vue`), pas stocké tel quel en base — même logique que
`practitioners.full_code`, mais sans colonne générée dédiée puisque
rien d'autre n'a besoin de filtrer/trier dessus pour l'instant.

⚠️ Presque toutes les colonnes métier sont nullable en base — volontaire,
conséquence directe du wizard résilient (CLAUDE.md "UX — wizards
résilients") : un brouillon peut être incomplet par construction. La
validation `required` (first_name/last_name/gender/phone/city/
intake_center_id) n'existe qu'au niveau applicatif, dans
`ConfirmPatientRequest`, déclenchée uniquement à la transition
`draft` → `confirmed`.

`client_uuid` rend le tout premier `POST` (création du brouillon)
idempotent côté serveur — voir `PatientController::storeDraft()` :
si la réponse HTTP est perdue et que le frontend retente avec le même
`client_uuid`, le serveur retrouve le brouillon existant au lieu d'en
créer un doublon.

### `external_medical_records`
**Historique médical conventionnel** (médecin/hôpital), pour comparaison
uniquement — jamais traité comme donnée clinique du domaine.

id · patient_id (fk) · condition_label · doctor_or_institution (texte libre)
· treatment_description · period_start, nullable · period_end, nullable ·
perceived_result (texte libre ou énuméré) · attachment_media_id, nullable
(`spatie/laravel-medialibrary`) · notes · timestamps

### `disease_categories`
Les catégories n'ont pas toutes la même nature (ex. catégories 1-7 =
type "maladie", généralement traitées par les médecins ; type "blocages" ;
type "symboles") — le `type` est lui-même un `enum_option`
(`enum_type = disease_category.type`), donc un nouveau type s'ajoute en
admin, sans migration.

**Ordre d'affichage (2026-08-24)** : `order` (tri d'affichage uniquement,
sans lien avec `code`) place désormais Blocages en premier (`order=1`),
Symboles en second (`order=2`), puis les 7 catégories "maladie"
(`order=3..9`) — les `code` ne changent pas (Blocages reste `8`,
Symboles est `0`). Catégorie "Cauchemars" (`code=9`, type `NIGHTMARE`)
**retirée** le 2026-08-24 : n'avait jamais de contenu source réel (deux
maladies placeholder 901/902), remplacée par "Symboles" (`code=0`, type
`SYMBOL`, 69 maladies — une liste de symboles rapportés en séance,
contenu réel fourni par l'utilisateur, pas un placeholder).

id · type_option_id (fk `enum_options`) · code · label (translatable) ·
icon (string, nullable — nom d'icône mdi, ex. "mdi-stomach", **ajouté
2026-08-25**) · order · active · timestamps

Les 9 catégories ont chacune une icône distincte assignée dans
`DiseaseCategorySeeder.php` (2026-08-25) : Blocages=`mdi-lock-outline`,
Symboles=`mdi-shape-outline`, Maladies digestives=`mdi-stomach`,
Maladies de la peau=`mdi-bandage`, Maladies du sexe=
`mdi-gender-male-female`, Maladies mentales et cérébrales=`mdi-brain`,
Maladies infectieuses=`mdi-virus`, La poitrine et les yeux=
`mdi-eye-outline`, Maladies héréditaires et cancer=`mdi-dna` — le
seeder `->update(['icon' => ...])` explicitement après le
`firstOrCreate` (pas seulement dans son bloc `create`) pour que ce
champ se backfill sur des lignes déjà seedées lors d'un simple
`php artisan db:seed --class=DiseaseCategorySeeder`, sans exiger un
`migrate:fresh`.

Affiché sur la card de catégorie de l'étape "Maladies" du wizard
Treatment — icône dans un badge circulaire (`v-avatar`), libellé en
dessous, compteur du nombre de maladies déjà sélectionnées dans cette
catégorie en `v-badge` sur le coin de l'icône. Rendu directement dans
`TreatmentWizardDialog.vue` (pas via les props `title`/`icon` d'`AppCard`
— ce layout, plus élaboré qu'un simple titre, utilise `AppCard` comme
coquille cliquable/sélectionnable mais compose son propre contenu dans
le slot par défaut). Catégorie sans icône : icône générique
`mdi-shape-outline` affichée à la place (jamais de badge vide).

✅ **CRUD admin implémenté (2026-08-21)** — `DiseaseCategoryController`
(super_admin uniquement), page Inertia `Admin/DiseaseCategories/Index.vue`.
`type_option_id` choisi via select alimenté par les `enum_options`
existantes (`enum_type = 'disease_category.type'`) — pas de CRUD dédié
pour `EnumOption` lui-même, ce set (ILLNESS/BLOCKAGE/SYMBOL, `NIGHTMARE`
retiré le 2026-08-24) est fermé dans la pratique actuelle. Resource d'admin séparée
(`DiseaseCategoryAdminResource`, expose `label` en `{fr,en}` via
`getTranslations()`) de la Resource en lecture seule existante
(`DiseaseCategoryResource`, string résolue, consommée par le wizard
Treatment) — voir CLAUDE.md pour le raisonnement complet.

### `diseases`
id · disease_category_id (fk) · code (3 chiffres, unique par catégorie) ·
label (translatable) · description (translatable, json, nullable) ·
default_duration_months · active · timestamps

✅ **CRUD admin implémenté (2026-08-21)** — `DiseaseController`
(super_admin uniquement), page Inertia `Admin/Diseases/Index.vue`.
`code` **auto-suggéré mais éditable** (`DiseaseCodeGenerator::
suggestNext()`, `GET admin/diseases/next-code?category_id=X`), même
mécanisme que `centers.code`/`practitioners.matricule`. Resource
d'admin séparée (`DiseaseAdminResource`) de la Resource en lecture
seule existante (`DiseaseResource`) — même raisonnement que
`DiseaseCategoryAdminResource` ci-dessus.

⚠️ **`description` jamais exposée au wizard Treatment avant le
2026-08-25** — la colonne existe depuis le tout premier seed (87 des
172 maladies ont un vrai contenu), mais `DiseaseResource` (celle
consommée par `TreatmentWizardDialog`) ne la retournait pas. Ajoutée
au `toArray()` de `DiseaseResource` — affichée en info-bulle (icône
`mdi-information-outline`, uniquement si non vide) à côté de chaque
maladie dans le wizard, pour que le praticien sache exactement ce que
couvre une maladie sans deviner depuis le seul libellé. `DiseaseAdminResource`
n'a pas eu besoin de ce changement, elle exposait déjà `description`
(champ éditable du formulaire admin).

### `disease_subcases`
Sous-cas d'un blocage (ex. sous "Travail" (801) : "Pas de travail",
"Travail médiocre"...) — présents uniquement pour la catégorie 8
(Blocages) dans les données actuelles, mais table générique, pas
spécifique aux blocages, réutilisable si un besoin similaire apparaît
ailleurs.

id · disease_id (fk `diseases`) · label (translatable) ·
description (translatable, json, nullable) · order · active · timestamps

✅ **Décidé (2026-08-20, session `feature/treatments`)** : pas de lien
structurel vers `treatment_diseases` pour l'instant — le
pivot reste `treatment_id`/`disease_id` sans colonne `subcase_id`. Si
le besoin réapparaît (stats/filtres par sous-cas précis), l'ajouter
plus tard reste un changement localisé (colonne nullable + migration),
pas une refonte.

⚠️ **Audit d'optimisation (2026-08-20)** : `disease_subcases` est la
seule table du domaine Patients référencée **uniquement** par le
modèle et le seeder — aucun controller, aucune UI, aucune requête
métier ne la lit à ce jour (confirmé par grep sur toute la codebase).
À cette date, jugée non supprimable : elle portait les 19 vrais
sous-cas extraits du document source (voir CLAUDE.md "Sous-cas des
blocages"), une vraie donnée métier en attente de son premier usage.

🔄 **Révisé le 2026-08-25** : le contenu des 19 sous-cas (801-804) a été
fusionné directement dans `diseases.description` de leur maladie parente
(format "Libellé : détail; Libellé : détail; ..." séparé par
point-virgule — 803 n'a que des libellés, sans détail source) : c'est
`description` qui est réellement affichée au praticien (info-bulle dans
le wizard Treatment, voir plus haut), jamais `disease_subcases`. Cette
table est donc désormais **candidate à la suppression** (décision
utilisateur) — le seeder continue de la peupler pour l'instant (pas
encore retirée du code), mais elle est prévue pour être droppée une
fois confirmé que rien d'autre n'en dépend. Ne pas réintroduire de
nouveau contenu dans `disease_subcases` sans repasser par `description`
en parallèle tant que la table existe encore.

### `treatments` — **implémenté** (2026-08-20)
Un traitement = un parcours de soin pour un patient, chez un soignant,
sur une période. Même pattern wizard résilient que `patients`
(`client_uuid`, statuts `draft`/`confirmed` réellement câblés).

id · client_uuid, uuid nullable unique · patient_id (fk, **requis dès
le draft** — contrairement à `patients`, un traitement sans patient
n'a pas de sens même en brouillon) · practitioner_id (fk, nullable) ·
center_id (fk, nullable) · started_at, nullable · ended_at, nullable ·
outcome (enum: cured/not_cured/percentage), nullable ·
outcome_percentage (1-99), nullable · notes · **closure_reason**
(string(20) nullable — voir ci-dessous) · created_by · timestamps
→ statut (draft/confirmed/ongoing/closed) via `spatie/laravel-model-status`
— **les 4 sont désormais câblés (2026-08-20, session "Statut global
Treatment")** : `confirm()` fait passer `confirmed` puis `ongoing` dans
la même requête (pas d'étape manuelle intermédiaire), `closed` est
atteint soit automatiquement (`Treatment::refreshClosureStatus()`,
appelé après chaque écriture de `treatment_session_disease_progress` —
`TreatmentController::confirm()`, `TreatmentSessionController::store()`/
`update()` — dès que chaque maladie rattachée a un résultat final, non
`ongoing`), soit manuellement (`Treatment::manualClose()`, route `POST
admin/treatments/{treatment}/close`, `CloseTreatmentRequest`, réservée
à un traitement `ongoing`).

**Réouverture (2026-08-21)** : `Treatment::reopen()` (route `POST
admin/treatments/{treatment}/reopen`, `ReopenTreatmentRequest`, réservée
à un traitement `closed`) repasse le traitement à `ongoing` et efface
`closure_reason`. Réservée aux managers/super_admin — aujourd'hui c'est
implicite (aucun compte de connexion à privilège inférieur n'existe
encore, voir `TreatmentPolicy::reopen()`), mais à garder ainsi
explicitement le jour où des comptes raqi individuels existeront
(demande explicite de l'utilisateur, pas juste un oubli).

`closure_reason` (posé uniquement quand statut = `closed`) : `resolved`
(auto, toutes les maladies rattachées résolues) / `lost_to_follow_up`
/ `protocol_not_followed` / `closed_manually` (les trois derniers via
clôture manuelle uniquement — `resolved` est explicitement rejeté sur
l'endpoint manuel, c'est une valeur calculée, jamais choisie).
`lost_to_follow_up`/`protocol_not_followed` viennent tous deux du brief
client (`Untitled-3`, "perdu de vue... deux semaines après la date
butoir" + "n'ont pas poursuivi le traitement") — groupés en une seule
valeur au départ, **scindés en deux le 2026-08-23** pour que les futures
stats de reporting distinguent "injoignable" (`lost_to_follow_up`) de
"joignable mais protocole non suivi" (`protocol_not_followed`, séances/
soins abandonnés en cours de route) ; `closed_manually` couvre tout le
reste (transfert, décès, arrêt médical...) — non demandé explicitement
par le client, ajouté pour ne pas bloquer un patient sur un cas hors
périmètre du document source.

**Statut patient dérivé (2026-08-23)** : `patients` n'a pas de colonne
`status` indépendante pour l'état affiché à l'écran
(actif/terminé/injoignable/arrêté/autre) — `Patient::derivedStatus()`
le calcule à la volée depuis le statut/`closure_reason` du traitement
le plus récent du patient (`Patient::latestTreatment()`), pour que les
deux ne puissent jamais diverger. Mapping complet documenté dans
CLAUDE.md (section "Motif de clôture `protocol_not_followed`, statut
patient dérivé..."). Rien de nouveau en base — uniquement une méthode
calculée sur le modèle, exposée aux props Inertia par
`PatientController::edit()`.

**Garde-fou anti-confusion séance/traitement** : `StoreTreatmentDraftRequest`
refuse de créer un nouveau traitement (`patient_id`) tant que le
patient a déjà un traitement `ongoing` — évite qu'un raqi pensant
loguer une séance/RDV crée par erreur un nouveau traitement. Contourné
uniquement pour le replay idempotent d'un `client_uuid` déjà existant
(retry réseau sur le même brouillon), jamais pour un vrai nouveau
traitement. Front-end : bouton "Ajouter un traitement" désactivé côté
`Patients/Form.vue` tant qu'un traitement `ongoing` existe, bouton
"Clôturer" dédié sur la carte du traitement en cours
(`TreatmentCloseDialog.vue`).

### `treatment_diseases` (pivot) — **implémenté**
treatment_id (fk) · disease_id (fk) — clé composite, pas d'id propre ·
**actively_tracked** (boolean, default `true`, voir ci-dessous). Reste
volontairement simple par ailleurs (2026-08-20) : dit juste "cette
maladie fait partie de ce traitement", ne porte aucun autre
statut/outcome — le suivi réel vit dans
`treatment_session_disease_progress` (historisé par séance, voir plus
bas), pas ici.

**Suivi actif vs maladie secondaire (2026-08-23)** : `actively_tracked`
distingue une maladie que le traitement doit évaluer à chaque séance
d'une maladie que le patient a, mais qui n'est pas la raison du suivi —
avant cette colonne, `treatment_diseases` était binaire (une maladie
attachée était automatiquement obligatoire à chaque séance, sans
entre-deux). Défaut `true` pour préserver le comportement des lignes
existantes (rien ne devient "secondaire" rétroactivement). Basculable
librement à tout moment via le wizard (`TreatmentWizardDialog`, un
toggle "Suivi actif à chaque séance" par maladie sélectionnée), y
compris sur un traitement déjà confirmé — **ce n'est pas soumis à la
même règle de verrouillage que le retrait complet** (voir
"Verrouillage post-suivi" ci-dessous) : désactiver le suivi actif d'une
maladie qui a déjà du progress reste toujours autorisé, elle garde son
historique, elle sort juste de l'évaluation obligatoire des séances
futures.

Effets :
- `Treatment::hasUnresolvedDiseases()`/l'auto-clôture
  (`refreshClosureStatus()`) ne considèrent que les maladies
  `actively_tracked = true` (`$this->diseases()->wherePivot('actively_tracked', true)`)
  — une maladie désactivée ne bloque plus jamais la clôture automatique
  du traitement.
- `TreatmentSessionDialog` (onglet "Suivi des maladies") n'affiche que
  les maladies actives — aucune saisie obligatoire pour une maladie
  désactivée.
- `TreatmentCard` affiche les maladies désactivées en chip discrète
  (`outlined`, sans couleur de statut, icône "non suivie") plutôt
  qu'avec la couleur de statut habituelle.

Payload : deux arrays séparés côté formulaire plutôt qu'un seul array
d'objets — `disease_ids` (inchangé) + `actively_tracked_disease_ids`
(sous-ensemble de `disease_ids`, validé comme tel via `Rule::in()` dans
les trois FormRequests concernées). Choisi pour rester un diff minimal
sur la validation Laravel existante et sur l'état du wizard
(`selectedDiseaseIds`/`isDiseaseLocked()` inchangés), au prix d'un
deuxième champ plutôt qu'une forme unique — discuté et tranché
explicitement avec l'utilisateur avant implémentation.
`TreatmentController::syncDiseases()` (privée) centralise le calcul
`sync()` avec attributs pivot pour les trois écritures
(`storeDraft`/`updateDraft`/`confirm`), pour que les trois ne
divergent jamais sur comment ce flag est appliqué.

**Verrouillage post-suivi (2026-08-22)** : `treatment_diseases`
`cascadeOnDelete` sur `treatment_session_disease_progress` (via
`treatment_sessions`), mais rien n'empêchait avant cette date de
retirer une maladie de `disease_ids` — depuis le wizard rouvert sur un
traitement déjà confirmé — alors même qu'elle avait déjà des lignes de
suivi enregistrées, laissant ces lignes orphelines. Règle actée :
- Tant qu'un traitement n'a **aucune** `treatment_session`, `disease_ids`
  reste librement modifiable (ajout et retrait) — comportement inchangé.
- Dès qu'une session existe, une maladie ayant au moins une ligne dans
  `treatment_session_disease_progress` (peu importe son `outcome`, même
  `ongoing`) ne peut plus être retirée de `disease_ids` — seulement
  ajoutée (extension de périmètre, jamais de perte de données).
- Appliqué côté serveur dans `UpdateTreatmentDraftRequest::withValidator()`
  (source de vérité), via `Treatment::latestOutcomePerDisease()`
  (factorisée depuis `hasUnresolvedDiseases()`, qui l'utilise aussi).
  Côté client, `TreatmentResource`/`TreatmentController::edit()`
  exposent `locked_disease_ids` (même calcul) pour que
  `TreatmentWizardDialog` désactive les checkboxes correspondantes
  avant même la soumission, plutôt que de laisser l'utilisateur
  découvrir le blocage seulement à l'échec de validation.

### `treatment_sessions` (séances individuelles) — **implémenté** (2026-08-20)
CRUD simple (pas le pattern wizard résilient — une séance est une
saisie courte en une fois, pas un formulaire long autosauvegardé, donc
pas de `client_uuid`/draft/confirm/`HasStatuses`).

id · treatment_id (fk) · practitioner_id (fk, nullable, peut différer
si réassignation) · session_date, nullable · duration_minutes,
nullable · notes · created_by · timestamps

### `treatment_session_disease_progress` — **implémenté** (2026-08-20)
Cœur du modèle de suivi de cette session : une ligne par maladie suivie
à une séance donnée, pas un statut final unique par maladie. Permet de
voir l'évolution dans le temps (lecture : toutes les lignes pour un
`disease_id` donné à travers les séances d'un même traitement, triées
par `session_date`). Couvre aussi les maladies de la catégorie
Symboles, traitées comme n'importe quelle autre catégorie (ancienne
catégorie Cauchemars retirée le 2026-08-24, voir note plus bas).

id · treatment_session_id (fk `treatment_sessions`, cascade) ·
disease_id (fk `diseases`, cascade) · outcome (enum:
cured/not_cured/percentage/ongoing), nullable · outcome_percentage
(1-99), nullable · notes · timestamps ·
unique(treatment_session_id, disease_id)

**Pré-remplissage à partir de la dernière valeur connue (2026-08-22)** :
`TreatmentSessionDialog` demandait auparavant de ressaisir
outcome/outcome_percentage/notes pour chaque maladie à chaque nouvelle
séance, même sans changement depuis la précédente.
`Treatment::latestOutcomePerDisease()` (la ligne la plus récente par
`disease_id`, toutes séances confondues) sert désormais aussi à
pré-remplir une **nouvelle** séance (`TreatmentResource::
latest_known_outcomes`, exposé uniquement quand `sessions` est chargé) —
uniquement au moment de créer une nouvelle séance, jamais en édition
d'une séance existante (ses propres valeurs l'emportent toujours). Un
badge "Reprise de la dernière séance" indique quand une valeur vient de
ce pré-remplissage plutôt que d'une saisie du praticien, et disparaît
dès que le champ correspondant est modifié.

### `treatment_session_care_items` (pivot) — **implémenté** (2026-08-20)
treatment_session_id (fk) · care_item_id (fk `care_items`) —
clé composite. Quels soins concrets (voir catalogue ci-dessous) ont été
utilisés à une séance donnée.

**Alimenté aussi depuis le wizard Treatment (2026-08-23)** — le 4e step
du wizard ("Soins — 1ère séance") capture les soins de la toute première
séance implicite créée à la confirmation (voir "Domaine Treatment" dans
`CLAUDE.md` pour le mécanisme de séance implicite). Les soins restent
100% par séance (option B retenue explicitement, pas de protocole/plan
au niveau du traitement) — ce step ne fait qu'ajouter un deuxième point
d'entrée vers ce même pivot, pas une nouvelle notion de donnée.

### `care_categories` / `care_items` — **implémenté** (2026-08-20)
Catalogue de soins dynamique à 2 niveaux, hand-roll sur le modèle exact
de `disease_categories`/`diseases` (décision : la
hiérarchie auto-référencée d'`EnumOption` — `parent_id` — n'a jamais
d'usage réel dans ce projet, voir `CLAUDE.md` "Wizard Treatment..." —
pas construit dessus).

**Catalogue réorganisé le 2026-08-24** — les 4 catégories placeholder
d'origine (Pommade, Bain, Encens, Tisane, jamais de contenu réel) ont
été retirées, remplacées par 2 catégories à contenu réel fourni par
l'utilisateur : **Générale** (8 items — les grands types de soins de la
pratique : Lecture, Eau/Feuille coranisée à diluer, Huile/pommade,
Encens, Tisane, Captage, Psychothérapie, Prière de malédiction) et
**Autres soins et produits** (37 items — catalogue plat de produits/eaux/
huiles/encens spécifiques). Le contenu source groupait visuellement ce
second catalogue par sous-thème (eaux, huiles, encens...), mais
`care_categories`/`care_items` est une hiérarchie fixe à 2 niveaux —
seedé en une seule catégorie plate plutôt que d'introduire un 3e niveau,
confirmé avec l'utilisateur avant seeding.

**Dé-doublonnage le 2026-08-25** : 4 items retirés d'"Autres soins et
produits" car redondants avec "Générale" — "Eau coranisée" (doublon
d'"Eau / Feuille coranisée à diluer"), "Huile" et "Pommade" (doublons
d'"Huile / pommade"), "Lecture de groupe sur patient" (retiré sans
report ailleurs, sur demande explicite). "Lecture individuelle sur
patient" renommé en "Lecture individuelle". Codes renumérotés en
continu après retrait (41 → 37 items) — aucun autre consommateur ne
référence ces codes de façon stable, renumérotation sans risque.
Catégories restantes après réorganisation : Générale, Autres soins et
produits, Verset à ajouter
(46 items — un verset coranique par symbole de la catégorie "Symboles",
label au format "S<sourate> v<verset(s)> (Symbole)", précision "à partir
de"/"jusqu'à" gardée avec le mot arabe source quand la citation est
partielle — détail dans le docblock de `CareCategorySeeder.php`),
Ventouses (71 items, zones du corps).

✅ **CRUD admin implémenté (2026-08-21)** — `CareCategoryController`/
`CareItemController` (super_admin uniquement), pages Inertia
`Admin/CareCategories/Index.vue`/`Admin/CareItems/Index.vue`. Permet de
corriger le contenu placeholder directement en admin dès qu'une vraie
source est fournie, sans repasser par le seeder. `care_items.code`
auto-suggéré mais éditable (`CareItemCodeGenerator::suggestNext()`, `GET
admin/care-items/next-code?category_id=X`) ; `care_categories.code`
reste un slug libre saisi à la main (pas de générateur, même
raisonnement que `zones.code`). Resources d'admin séparées
(`CareCategoryAdminResource`/`CareItemAdminResource`) des Resources en
lecture seule existantes.

`care_categories` : id · code (unique) · label (translatable)
· order · active · timestamps

`care_items` : id · care_category_id (fk, cascade) · code
(unique par catégorie) · label (translatable) · description
(translatable, nullable) · order · active · timestamps

⚠️ **Note "Symboles"** (remplace l'ancienne note "cauchemars", catégorie
retirée le 2026-08-24) : la `DiseaseCategory` `code=0` (`type = SYMBOL`)
est seedée avec 69 maladies (contenu réel fourni par l'utilisateur, pas
un placeholder) et traitée exactement comme les autres catégories dans
`treatment_session_disease_progress` — aucune distinction spéciale de
suivi de statut.

⚠️ **Hors périmètre, explicitement reporté** : lien entre le catalogue
de soins et le stock (`Catalog.Product` — un item comme "Verset" n'a ni
stock ni prix, contrairement à une pommade, donc ce lien ne concernera
qu'une partie des catégories le jour où il sera construit) ; lien vers
la facturation/paiement patient (`Billing`).

---

## 4. Scheduling

### `scheduling_campaigns` (tournées de soin)
id · name · organizing_center_id (fk `centers`) · location_type
(enum: fixed/mobile) · address, nullable · starts_at · ends_at ·
capacity_per_slot, nullable · notes · created_by · timestamps
→ statut (planned/active/completed/cancelled) via `spatie/laravel-model-status`

### `scheduling_appointments`
id · patient_id (fk) · practitioner_id (fk) · center_id (fk) ·
campaign_id (fk, nullable — null = RDV classique en centre) ·
scheduled_at · duration_minutes · notes · created_by · timestamps
→ statut (scheduled/confirmed/completed/cancelled/no_show) via
`spatie/laravel-model-status`

---

## 5. Catalog (produits & stock)

### `catalog_product_categories`
id · name (translatable) · parent_id, nullable · order

### `catalog_products`
id · sku (unique) · barcode, nullable, unique · name (translatable) ·
product_category_id (fk) · unit_price · cost_price, nullable ·
unit (enum: piece/box/bottle...) · active · timestamps

### `catalog_stock_movements` (source de vérité — jamais de stock stocké en dur)
id · product_id (fk) · center_id (fk) · type (enum: in/out/adjustment) ·
quantity · reason (enum: purchase/sale/loss/transfer/initial) ·
reference_type/reference_id, nullable (polymorphe — lie à une facture,
un transfert...) · performed_by (fk `users`) · notes · timestamps

### `catalog_product_center_stock` (vue matérialisée / cache, recalculée par observer)
product_id (fk) · center_id (fk) · quantity_on_hand · updated_at
→ table de lecture rapide, jamais écrite directement — reconstruite
depuis `catalog_stock_movements`, unique index (product_id, center_id)

---

## 6. Billing

### `billing_invoices`
id · invoice_number (unique, généré) · patient_id (fk, nullable — vente
produit seule possible sans patient) · center_id (fk) ·
type (enum: treatment/product/mixed) · issued_at, nullable ·
due_at, nullable · total_amount (centimes) · currency · created_by ·
timestamps
→ statut (draft/issued/paid/partially_paid/cancelled) via
`spatie/laravel-model-status`

### `billing_invoice_lines`
id · invoice_id (fk) · description · quantity · unit_price · line_total
· reference_type/reference_id, nullable (polymorphe → `treatment_session_id`
ou `product_id`)

### `billing_payments`
id · invoice_id (fk) · amount (centimes) · method (enum: cash/transfer/
card/mobile_money...) · paid_at · reference, nullable · recorded_by ·
timestamps

## 6bis. Payroll (système de paie par répartition — décision du 2026-08-19)

Modèle : **pas de salaire fixe**. Une cagnotte (`pool_amount`) est
partagée entre les soignants d'un centre sur une **période à durée
libre** (le manager décide — 3 jours, semaine, mois...), au prorata de
la **présence** de chaque soignant sur la période et de son
**coefficient de grade**. Un système d'**avances sur salaire**
(déductibles du prochain versement) complète l'ensemble. Voir
`app/Domains/Billing/Services/PayPeriodCalculator.php` pour l'algorithme
détaillé et documenté.

### `practitioners_attendances` (domaine Practitioners, pas Billing)
Présence factuelle d'un soignant à son centre, jour par jour — source de
vérité pour le calcul de paie. Table dédiée plutôt que dérivée des
séances de traitement : la présence physique est un concept distinct de
"a-t-il traité un patient ce jour-là".

id · practitioner_id (fk) · center_id (fk) · date · present (bool,
default true — permet aussi d'enregistrer une absence explicite) ·
notes, nullable · recorded_by (fk `users`, nullable) · timestamps ·
unique(practitioner_id, date)

### `billing_pay_periods`
id · center_id (fk) · starts_at · ends_at (durée libre, décidée par le
manager à chaque période) · pool_amount (centimes) · currency · notes ·
created_by · timestamps
→ statut (draft/calculated/finalized/paid) via `spatie/laravel-model-status`

### `billing_pay_period_shares`
Part calculée d'un soignant pour une période donnée. `attendance_days`
et `grade_coefficient_snapshot` sont des **snapshots** au moment du
calcul — un changement de grade après coup ne doit jamais changer
rétroactivement une part déjà calculée/versée.

id · pay_period_id (fk) · practitioner_id (fk) · attendance_days (int,
snapshot) · grade_coefficient_snapshot (décimal, snapshot) ·
gross_amount (centimes, avant avances) · advances_deducted_amount
(centimes) · net_amount (centimes, = gross − avances, jamais négatif) ·
paid_at, nullable · timestamps · unique(pay_period_id, practitioner_id)
→ statut (pending/paid) via `spatie/laravel-model-status`

### `billing_salary_advances`
id · practitioner_id (fk) · amount (centimes) · currency · granted_at ·
reason, nullable · granted_by (fk `users`, nullable) ·
pay_period_share_id (fk `billing_pay_period_shares`, nullable — renseigné
une fois l'avance effectivement déduite d'un versement réel, `null` =
encore en attente) · timestamps
→ statut (pending/approved/deducted/cancelled) via `spatie/laravel-model-status`

### `billing_expenses`
id · center_id (fk) · expense_category_option_id (fk `enum_options`,
`enum_type = expense.category`) · amount (centimes) · currency ·
expense_date · description · attachment_media_id, nullable · recorded_by
· timestamps
→ statut (draft/approved) via `spatie/laravel-model-status`

## 6ter. Paie conventionnelle (fondations — décision du 2026-08-19)

Certains centres voudront un vrai système de paie (contrat, charges
patronales/salariales, cotisations versées à des organismes), pas le
modèle de cagnotte partagée. `centers.payroll_mode` (`PayrollMode` enum
PHP : `pool_sharing`/`conventional`) est le point de bascule — décidé
par centre, pas globalement.

**Volontairement schéma seulement à ce stade** — pas de moteur de calcul
de bulletin de paie ("on fera à la fin", une fois les gros modules
posés). S'inspire de la structure `Payroll` d'un projet de référence
(`Employment`, `Organism`, `Bonus` observés dans son
`ObserverServiceProvider`) sans copier son implémentation (non
disponible dans l'export fourni — dossiers vides).

### `billing_employments`
id · practitioner_id (fk) · center_id (fk) · contract_type, nullable
(volontairement libre, varie trop par pays pour être figé maintenant) ·
base_salary_amount, nullable (centimes) · currency, nullable ·
started_at · ended_at, nullable · notes · timestamps
→ statut (active/terminated) via `spatie/laravel-model-status`

### `billing_payroll_organisms`
Organisme collecteur (sécurité sociale, impôts, retraite...) — diffère
radicalement par pays, d'où `country_id` obligatoire plutôt qu'une liste
globale.

id · country_id (fk) · type_option_id (fk `enum_options`,
`enum_type = payroll_organism.type`) · name · active · timestamps

### `billing_payroll_charges`
Taux de cotisation/charge — définition seulement, pas encore appliquée
par un calculateur.

id · country_id (fk) · organism_id (fk `billing_payroll_organisms`) ·
label · rate_percent (décimal) · charge_type (`employer`/`employee`) ·
active · timestamps

### `billing_bonuses`
Prime ponctuelle, valable dans les deux modes de paie (`pay_period_id`
nullable — un centre en mode conventionnel peut ne jamais l'utiliser).

id · practitioner_id (fk) · pay_period_id (fk `billing_pay_periods`,
nullable) · amount (centimes) · currency · reason, nullable ·
granted_by (fk `users`, nullable) · granted_at · timestamps

⚠️ **Prochaine étape quand ce mode sera activé pour de vrai** : un
service `PayslipCalculator` (nom provisoire) qui applique
`billing_payroll_charges` sur `billing_employments.base_salary_amount`
pour produire un bulletin net/brut — pas encore écrit, volontairement.

---

## 7. Reporting (statistiques authentifiables)

### `reporting_report_snapshots`
id · type (enum: by_zone/by_country/by_center/by_category/by_disease/
success_rate) · scope_params (json — filtres appliqués) · generated_at ·
generated_by (fk `users`) · content_hash (sha256) · signature (base64) ·
verification_token (unique, pour l'URL `/verify/{token}`) ·
file_path (PDF stocké) · timestamps

---

## Notes transverses

- **Multi-devise** : `currency` en ISO 4217 sur toutes les tables
  monétaires — même si tout démarre probablement en une seule devise par
  pays, ça évite une migration douloureuse plus tard.
- **Montants toujours en centimes** (integer), jamais en float — décision
  déjà actée sur Geneva Bengal, reconduite ici.
- **`draft`/`confirmed`** présent sur `patients`, `treatments`,
  `scheduling_appointments`, `billing_expenses` — ce sont les entités
  saisies via wizard/formulaire long, donc concernées par la sauvegarde
  continue. **`treatment_sessions` n'a volontairement pas ce
  statut** (décision du 2026-08-20, révise l'intention initiale) — une
  séance est une saisie courte en une fois (ce qui s'est passé pendant
  un rendez-vous), pas un formulaire long autosauvegardé ; CRUD simple.
  Les entités de référence (`countries`, `centers`, `catalog_products`...)
  n'ont pas ce statut non plus, elles sont gérées en CRUD admin classique.
- **`practitioners.full_code`** recalculé automatiquement (observer) à la
  création/modification de `center_id`/`matricule` — jamais saisi à
  la main, pour garantir l'unicité et la cohérence pays+centre.
- **Tables du domaine Patients sans préfixe `patients_` depuis
  2026-08-20** — `diseases`, `disease_categories`, `disease_subcases`,
  `care_categories`, `care_items`, `treatments`, `treatment_diseases`,
  `treatment_sessions`, `treatment_session_disease_progress`,
  `treatment_session_care_items`, `external_medical_records` (celle-ci
  toujours pas construite). Retiré car le préfixe créait de la
  confusion sans bénéfice — contrairement aux autres domaines
  (`scheduling_*`, `catalog_*`...), Patients n'a pas de risque de
  collision de nom avec un autre domaine sur ces tables précises. Seule
  la table ancre `patients` restait déjà sans double-préfixe
  (`patients_patients` n'a jamais existé).
- **Auto-suggestion + édition libre, un pattern commun à trois champs
  (2026-08-20)** — `centers.code`, `practitioners.matricule` sont tous
  les deux auto-suggérés (prochain numéro libre dans le scope parent)
  mais restent éditables dans le formulaire ; `patients.patient_number`
  suit la même logique de génération mais n'est **jamais** éditable
  (l'utilisateur ne l'a pas demandé pour ce champ). Les trois services
  (`CenterCodeGenerator`, `PractitionerCodeGenerator::
  suggestNextMatricule()`, `PatientNumberGenerator`) partagent le même
  algorithme (`MAX(colonne) + 1` scopé au parent, zero-paddé), sans
  factorisation en un service commun — trois usages distincts sur des
  colonnes de tailles différentes (2/3/4 chiffres) et des modèles
  différents, pas encore assez de règles partagées pour justifier une
  abstraction (voir CLAUDE.md "Services — Action classes, pas de CRUD
  wrapper générique").
