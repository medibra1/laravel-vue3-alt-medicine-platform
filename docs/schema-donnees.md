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

### `model_options` (pivot polymorphe)
`model_type`, `model_id`, `option_id` (fk `enum_options`)

---

## 2. Core

### `zones`
Regroupement géographique de pays (ex. Afrique du Nord, Europe) — niveau
utilisé pour les statistiques par zone, au-dessus du pays.

id · code · name (translatable) · order · active · timestamps

### `countries`
id · zone_id (fk `zones`, nullable) · code (2 chiffres, unique) ·
name (translatable) · active · timestamps

### `centers`
id · country_id (fk) · code (2 chiffres, unique **par pays**) · name ·
address · phone · email · active · payroll_mode (enum PHP `PayrollMode` :
`pool_sharing`/`conventional`, défaut `pool_sharing` — voir section 6ter)
· timestamps

### `grades`
Grade du soignant (Junior/Confirmé/Senior...) — porte le `coefficient`
utilisé dans le calcul de paie (voir section 6bis). Table dédiée (pas un
`enum_option`) car elle porte des règles métier, pas juste un libellé.

id · code · label (translatable) · coefficient (décimal, multiplicateur
— ex. 1.00/1.15/1.30) · order · active · timestamps

### `users`
id · name · email (unique) · password · locale (préférence UI) ·
is_active · timestamps
→ table pivot `spatie/laravel-permission` en **mode teams**, `team_id` =
`center_id` (un manager/soignant a un rôle scopé à son centre ; le
`super_admin` a un rôle sans team, global).

### `practitioners` (soignants)
| Colonne | Type | Notes |
|---|---|---|
| id | bigint PK | |
| user_id | fk `users`, nullable | un soignant peut ne pas avoir de compte de connexion (juste référencé) |
| center_id | fk `centers` | |
| grade_id | fk `grades`, nullable | porte le coefficient utilisé dans le calcul de paie |
| diploma_number | string(3) | dernier segment du code |
| full_code | string(7), généré | `country.code + center.code + diploma_number`, unique |
| level | int, nullable | préparation système de niveaux/examens (vague 3) |
| hired_at | date, nullable | |
| timestamps | | |

Statut (actif/inactif) → `spatie/laravel-model-status` (`HasStatuses`),
plus l'historique associé (utile pour tracer une suspension temporaire).

Unicité en deux temps : `full_code` est unique en base (contrainte SQL,
dernier filet de sécurité), mais la validation formulaire porte sur
`diploma_number` scopé par `center_id`
(`Rule::unique('practitioners')->where('center_id', ...)` dans
`StorePractitionerRequest`) — c'est ce champ que l'utilisateur saisit
réellement, `full_code` étant généré. Valider le champ saisi plutôt que
le champ généré évite qu'un doublon remonte comme une exception SQL au
lieu d'une erreur de formulaire.

---

## 3. Patients

### `patients`
id · first_name · last_name · gender · birth_date, nullable · phone ·
email, nullable · city · country_id, nullable (résidence) ·
intake_center_id (fk `centers`, centre d'accueil initial) ·
emergency_contact_name, nullable · emergency_contact_phone, nullable ·
notes · created_by (fk `users`) · timestamps
→ statut (draft/confirmed) via `spatie/laravel-model-status`

### `patients_external_medical_records`
**Historique médical conventionnel** (médecin/hôpital), pour comparaison
uniquement — jamais traité comme donnée clinique du domaine.

id · patient_id (fk) · condition_label · doctor_or_institution (texte libre)
· treatment_description · period_start, nullable · period_end, nullable ·
perceived_result (texte libre ou énuméré) · attachment_media_id, nullable
(`spatie/laravel-medialibrary`) · notes · timestamps

### `patients_disease_categories`
Les catégories n'ont pas toutes la même nature (ex. catégories 1-7 =
type "maladie", généralement traitées par les médecins ; type "blocages" ;
type "cauchemars", nouvelle) — le `type` est lui-même un `enum_option`
(`enum_type = disease_category.type`), donc un nouveau type s'ajoute en
admin, sans migration.

id · type_option_id (fk `enum_options`) · code · label (translatable) ·
order · active · timestamps

### `patients_diseases`
id · disease_category_id (fk) · code (3 chiffres, unique par catégorie) ·
label (translatable) · description (translatable, json, nullable) ·
default_duration_months · active · timestamps

### `patients_disease_subcases`
Sous-cas d'un blocage (ex. sous "Travail" (801) : "Pas de travail",
"Travail médiocre"...) — présents uniquement pour la catégorie 8
(Blocages) dans les données actuelles, mais table générique, pas
spécifique aux blocages, réutilisable si un besoin similaire apparaît
ailleurs.

id · disease_id (fk `patients_diseases`) · label (translatable) ·
description (translatable, json, nullable) · order · active · timestamps

⚠️ Pas encore de lien vers `patients_treatments`/`patients_treatment_diseases`
— quand le domaine Treatment sera implémenté, décider si un traitement
référence un ou plusieurs sous-cas (probablement une colonne
`subcase_id` nullable sur le pivot, ou une table de jonction dédiée),
pas tranché à ce stade.

### `patients_treatments`
Un traitement = un parcours de soin pour un patient, chez un soignant,
sur une période.

id · patient_id (fk) · practitioner_id (fk) · center_id (fk) ·
started_at · ended_at, nullable ·
outcome (enum: cured/not_cured/percentage), nullable ·
outcome_percentage (1-99), nullable · notes · created_by · timestamps
→ statut (draft/confirmed/ongoing/closed) via `spatie/laravel-model-status`

### `patients_treatment_diseases` (pivot)
treatment_id (fk) · disease_id (fk)

### `patients_treatment_sessions` (séances individuelles)
id · treatment_id (fk) · practitioner_id (fk, peut différer si
réassignation) · session_date · duration_minutes, nullable · notes ·
timestamps
→ statut (draft/confirmed) via `spatie/laravel-model-status`

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
posés). S'inspire de la structure `Payroll` d'InPACT (`Employment`,
`Organism`, `Bonus` observés dans son `ObserverServiceProvider`) sans
copier son implémentation (non disponible dans l'export fourni — dossiers
vides).

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
- **`draft`/`confirmed`** présent sur `patients`, `patients_treatments`,
  `patients_treatment_sessions`, `scheduling_appointments`, `billing_expenses` — ce sont les entités
  saisies via wizard/formulaire long, donc concernées par la sauvegarde
  continue. Les entités de référence (`countries`, `centers`, `catalog_products`...)
  n'ont pas ce statut, elles sont gérées en CRUD admin classique.
- **`practitioners.full_code`** recalculé automatiquement (observer) à la
  création/modification de `center_id`/`diploma_number` — jamais saisi à
  la main, pour garantir l'unicité et la cohérence pays+centre.
