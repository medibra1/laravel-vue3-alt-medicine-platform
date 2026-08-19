# Structure Domain-Driven — backend Laravel

Inspirée de l'organisation InPACT (`app/Domains/*`), adaptée à nos 7 domaines
métier + un domaine transverse. Aucune config composer.json spéciale
nécessaire : `App\` → `app/` (PSR-4 standard) couvre déjà
`App\Domains\Patients\Models\Patient` → `app/Domains/Patients/Models/Patient.php`.

```
app/
├── Domains/
│   ├── Common/                          # transverse, utilisé par tous les domaines
│   │   ├── Models/
│   │   │   ├── EnumOption.php
│   │   │   └── ModelOption.php
│   │   ├── Queries/
│   │   │   └── EnumOptionFilter.php     # AllowedFilter custom pour spatie/laravel-query-builder
│   │   ├── Traits/
│   │   │   └── HasEnumOptions.php       # trait à mixer sur les modèles concernés
│   │   └── Services/
│   │       └── SlugService.php
│   │
│   ├── Auth/                            # utilisateurs, rôles, permissions
│   │   ├── Models/
│   │   │   └── User.php
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   ├── Admin/UserController.php     # gestion comptes (super_admin)
│   │   │   │   └── ImpersonateController.php
│   │   │   └── Middleware/
│   │   │       └── EnsureCenterAccess.php       # vérifie team_id (center) sur les routes manager
│   │   ├── Policies/
│   │   │   └── UserPolicy.php
│   │   └── Notifications/
│   │       └── PasswordResetNotification.php
│   │
│   ├── Core/                            # référentiel géographique + rémunération de base
│   │   ├── Models/
│   │   │   ├── Zone.php
│   │   │   ├── Country.php
│   │   │   ├── Center.php
│   │   │   └── Grade.php
│   │   ├── Enums/
│   │   │   └── PayrollMode.php          # pool_sharing | conventional — fourche par centre
│   │   ├── Http/
│   │   │   └── Controllers/Admin/
│   │   │       ├── ZoneController.php
│   │   │       ├── CountryController.php
│   │   │       ├── CenterController.php
│   │   │       └── GradeController.php
│   │   ├── Policies/
│   │   │   └── CenterPolicy.php         # scope manager ↔ son centre
│   │   └── Observers/
│   │       └── CenterObserver.php       # génère le code centre si absent
│   │
│   ├── Practitioners/                   # soignants
│   │   ├── Models/
│   │   │   ├── Practitioner.php
│   │   │   └── PractitionerAttendance.php   # présence jour par jour, source de vérité pour la paie
│   │   ├── Http/
│   │   │   ├── Controllers/Admin/PractitionerController.php
│   │   │   └── Requests/
│   │   │       ├── StorePractitionerRequest.php   # center_id forcé/prohibé selon super_admin ou manager
│   │   │       └── UpdatePractitionerRequest.php
│   │   ├── Services/
│   │   │   └── PractitionerCodeGenerator.php   # calcule full_code (pays+centre+diplôme)
│   │   ├── Observers/
│   │   │   └── PractitionerObserver.php
│   │   └── Policies/
│   │       └── PractitionerPolicy.php
│   │
│   ├── Patients/
│   │   ├── Models/
│   │   │   ├── Patient.php
│   │   │   ├── ExternalMedicalRecord.php
│   │   │   ├── DiseaseCategory.php
│   │   │   ├── Disease.php
│   │   │   ├── Treatment.php
│   │   │   └── TreatmentSession.php
│   │   ├── Http/
│   │   │   ├── Controllers/Admin/
│   │   │   │   ├── PatientController.php
│   │   │   │   ├── TreatmentController.php
│   │   │   │   └── DiseaseController.php
│   │   │   └── Requests/
│   │   │       ├── PatientDraftStepRequest.php   # autosave par étape (wizard)
│   │   │       └── StoreTreatmentRequest.php
│   │   ├── Services/
│   │   │   └── PatientWizardDraftService.php     # logique brouillon/validation finale
│   │   ├── Policies/
│   │   │   ├── PatientPolicy.php
│   │   │   └── TreatmentPolicy.php
│   │   └── Events/ + Listeners/
│   │       └── TreatmentConfirmed.php / NotifyStaffOfNewTreatment.php
│   │
│   ├── Scheduling/
│   │   ├── Models/
│   │   │   ├── Campaign.php
│   │   │   └── Appointment.php
│   │   ├── Http/Controllers/
│   │   │   ├── Admin/CampaignController.php
│   │   │   └── Admin/AppointmentController.php
│   │   ├── Services/
│   │   │   └── AppointmentAvailabilityService.php  # créneaux libres par praticien/campagne
│   │   └── Policies/
│   │       └── AppointmentPolicy.php
│   │
│   ├── Catalog/
│   │   ├── Models/
│   │   │   ├── ProductCategory.php
│   │   │   ├── Product.php
│   │   │   ├── StockMovement.php
│   │   │   └── ProductCenterStock.php
│   │   ├── Http/Controllers/Admin/
│   │   │   ├── ProductController.php
│   │   │   └── StockMovementController.php
│   │   ├── Services/
│   │   │   └── StockLevelRecalculator.php   # reconstruit product_center_stock
│   │   └── Observers/
│   │       └── StockMovementObserver.php
│   │
│   ├── Billing/
│   │   ├── Models/
│   │   │   ├── Invoice.php
│   │   │   ├── InvoiceLine.php
│   │   │   ├── Payment.php
│   │   │   ├── Expense.php
│   │   │   ├── PayPeriod.php                # cagnotte partagée — mode pool_sharing
│   │   │   ├── PayPeriodShare.php           # part calculée par soignant
│   │   │   ├── SalaryAdvance.php            # avance sur salaire, déductible
│   │   │   ├── Employment.php               # contrat — mode conventional (fondation, pas de calcul de bulletin)
│   │   │   ├── PayrollOrganism.php          # organisme collecteur de charges, par pays
│   │   │   ├── PayrollCharge.php            # taux de charge/cotisation
│   │   │   └── Bonus.php                    # prime ponctuelle, valable dans les deux modes
│   │   ├── Http/Controllers/Admin/
│   │   │   ├── InvoiceController.php
│   │   │   ├── PayPeriodController.php
│   │   │   └── ExpenseController.php
│   │   ├── Services/
│   │   │   ├── InvoiceNumberGenerator.php
│   │   │   └── PayPeriodCalculator.php      # répartition présence × coefficient de grade (mode pool_sharing)
│   │   │       # PayslipCalculator (mode conventional) — pas encore écrit, voir schema-donnees-v1.md §6ter
│   │   ├── Exports/
│   │   │   ├── InvoicesExport.php           # maatwebsite/laravel-excel
│   │   │   └── ExpensesExport.php
│   │   └── Policies/
│   │       └── ExpensePolicy.php
│   │
│   └── Reporting/
│       ├── Models/
│       │   └── ReportSnapshot.php
│       ├── Http/Controllers/
│       │   ├── Admin/ReportController.php
│       │   └── VerifyController.php         # route publique /verify/{token}
│       ├── Services/
│       │   ├── StatsAggregator.php          # by_zone/by_country/by_center/...
│       │   ├── ReportSigner.php             # hash + signature
│       │   └── ReportPdfBuilder.php
│       └── Exports/
│           └── StatsExport.php
│
├── Providers/
│   ├── AppServiceProvider.php
│   ├── ObserverServiceProvider.php          # enregistre tous les observers ci-dessus
│   └── RouteServiceProvider.php             # charge routes/domains/*.php
│
├── Http/
│   └── Controllers/Controller.php            # base abstraite, rien de métier ici
│
├── Policies/                                  # uniquement les policies vraiment transverses
│   └── MediaPolicy.php                        # délègue aux policies de domaine (pattern InPACT)
│
└── Support/
    └── helpers.php

routes/
├── web.php                    # charge chaque fichier ci-dessous
└── domains/
    ├── core.php
    ├── practitioners.php
    ├── patients.php
    ├── scheduling.php
    ├── catalog.php
    ├── billing.php
    └── reporting.php

database/
├── migrations/                # centralisées (convention Laravel standard, pas splittées par domaine)
├── seeders/
│   ├── DatabaseSeeder.php
│   ├── ZoneSeeder.php / CountrySeeder.php / GradeSeeder.php
│   ├── EnumOptionSeeder.php   # catégories, types, statuts métier non-spatie
│   └── DemoDataSeeder.php     # jamais en prod
└── factories/
    └── Domains/{Domain}/{Model}Factory.php

tests/
└── Feature/
    └── Domains/
        ├── Core/...
        ├── Practitioners/...
        ├── Patients/...
        ├── Scheduling/...
        ├── Catalog/...
        ├── Billing/...
        └── Reporting/...
```

## Décisions notables

- **Migrations centralisées** (`database/migrations/`, pas par domaine) —
  volontaire : les splitter casserait `php artisan migrate:fresh`/l'ordre
  chronologique sans bénéfice réel, l'organisation par domaine se voit déjà
  au niveau des modèles/services, pas besoin de la dupliquer côté migrations.
- **`Policies/MediaPolicy.php` reste transverse** (repris du zip InPACT) —
  délègue à la policy du modèle réel porteur des médias (photo patient,
  diplôme, reçu de dépense...), pattern déjà validé.
- **Chaque domaine est autonome en tests** — `tests/Feature/Domains/X`
  reflète `app/Domains/X`, aucun fichier de test ne teste deux domaines à
  la fois sauf les tests d'intégration transverses (ex. un RDV qui génère
  une facture) qui vivent dans `tests/Feature/Integration/`.
- **`routes/domains/*.php`** plutôt qu'un `web.php` monolithique — chaque
  fichier ne connaît que les routes de son domaine, chargé depuis
  `RouteServiceProvider::boot()` avec le bon groupe de middleware
  (`role:manager`, `permission:...`, préfixe `/admin/{domain}`...).
- **`Http/Requests/` par domaine** — non prévu dans le plan initial,
  ajouté en pratique dès `Practitioners` (FormRequests dédiées pour la
  validation scopée manager↔centre). Pattern à reprendre pour les
  prochains domaines dès qu'un controller a une validation non triviale
  (scoping, règles conditionnelles selon le rôle) plutôt que de valider
  inline dans le controller.
