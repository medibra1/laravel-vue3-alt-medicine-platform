# Application de gestion de centres de médecine alternative

Application multi-pays de gestion de centres de médecine alternative :
patients, soignants, rendez-vous, produits/stock, facturation, paie.
Laravel 13 / Inertia / Vue 3 / PrimeVue, architecture Domain-Driven.

## Documentation

- [`docs/schema-donnees.md`](./docs/schema-donnees.md) — schéma complet
  de toutes les tables, domaine par domaine.
- [`docs/structure-ddd.md`](./docs/structure-ddd.md) — organisation des
  dossiers, conventions de code.

**Ces deux documents sont mis à jour à chaque changement de schéma ou
d'architecture — pas seulement quand un domaine est terminé.** Ce
README aussi.

## Stack

- **Backend** : Laravel 13, PHP 8.3+, MySQL 8, `spatie/laravel-permission`
  (mode teams — un manager par centre), `spatie/laravel-model-status`
  (statuts historisés), `spatie/laravel-translatable` (contenu
  multilingue), système `EnumOption`/`ModelOption` maison (options
  dynamiques, éditables en admin sans déploiement).
- **Frontend** : Vue 3 + Inertia + TypeScript + PrimeVue 4 (support RTL
  natif — arabe prévu).
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

## État d'avancement (2026-08-19)

**Domaines Vague 1** — voir `docs/schema-donnees.md` pour le détail complet :

| Domaine | État |
|---|---|
| Core (zones, pays, centres, grades) | Schéma + seeders faits |
| Practitioners (soignants, présence) | **Fait** — CRUD admin, policy, tests |
| Patients (dossier, maladies, traitements) | Référentiel maladies fait ; `Patient` (mono-étape) fait ; `ExternalMedicalRecord`/`Treatment` à faire |
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
le pattern "wizard résilient" (voir `CLAUDE.md`).

**Système de paie** : deux modes au choix par centre (`payroll_mode`) —
répartition d'une cagnotte par présence/coefficient (implémenté,
`PayPeriodCalculator`), ou paie conventionnelle avec charges/cotisations
(schéma posé, moteur de calcul pas encore écrit — volontairement, voir
`CLAUDE.md`).

## Points ouverts connus

- 9 pays sur 46 sans zone assignée (ambigus dans le document source) —
  voir `database/seeders/CountrySeeder.php`.
- Catégorie de maladie "cauchemars" absente du document source — à
  définir avant de pouvoir la seeder.
- Traductions anglaises du contenu métier faites par Claude — relecture
  par un locuteur natif recommandée avant mise en prod (précision
  terminologique médicale).
- Découpage du blocage "Mariage" (804) en sous-cas fait par
  interprétation (source en prose continue, pas une liste structurée) —
  voir le docblock de `DiseaseCategorySeeder.php`.
