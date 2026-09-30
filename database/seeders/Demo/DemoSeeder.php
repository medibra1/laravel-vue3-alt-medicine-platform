<?php

namespace Database\Seeders\Demo;

use App\Domains\Auth\Models\User;
use App\Domains\Auth\Support\CenterScopedRoleAssigner;
use App\Domains\Common\Models\EnumOption;
use App\Domains\Core\Models\Center;
use App\Domains\Core\Models\CenterClosure;
use App\Domains\Core\Models\CenterOperatingHours;
use App\Domains\Core\Models\Country;
use App\Domains\Core\Services\CenterCodeGenerator;
use App\Domains\Patients\Models\ConsentTemplate;
use App\Domains\Patients\Models\Disease;
use App\Domains\Patients\Models\Patient;
use App\Domains\Patients\Models\Treatment;
use App\Domains\Patients\Models\TreatmentSession;
use App\Domains\Patients\Services\MergeImagesIntoPdfAction;
use App\Domains\Patients\Services\PatientNumberGenerator;
use App\Domains\Patients\Services\RecordPatientConsentAction;
use App\Domains\Practitioners\Models\Practitioner;
use App\Domains\Practitioners\Services\PractitionerCodeGenerator;
use App\Domains\Scheduling\Models\Appointment;
use App\Domains\Scheduling\Models\PractitionerAvailability;
use App\Domains\Scheduling\Models\PractitionerTimeOff;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Database\Seeders\CareCategorySeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\DiseaseCategorySeeder;
use Database\Seeders\EnumOptionSeeder;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SessionMeasurementTypesSeeder;
use Database\Seeders\SuperAdminSeeder;
use Database\Seeders\ZoneSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\ModelStatus\Status;

/**
 * Realistic demo dataset covering every shipped feature (agenda, time
 * offs, closures, reminders, consents, documents, measurements). Never
 * called from DatabaseSeeder — run explicitly:
 *   php artisan app:seed-demo
 *   php artisan db:seed --class="Database\Seeders\Demo\DemoSeeder"
 *
 * Refuses to run in production.
 *
 * Idempotence — delete-then-recreate, targeted: every demo center is
 * named "[Démo] ...", every demo account/patient email ends in
 * "@demo.local". A run first removes exactly those rows (and what hangs
 * off them) before recreating everything, so a second run yields the
 * same counts. Patients and consents are deleted one by one through
 * Eloquent rather than by DB cascade, so medialibrary also removes their
 * files from disk; the remaining children (appointments, treatments,
 * availabilities...) go through the existing FK cascades. spatie's
 * polymorphic `statuses` rows and the per-center roles have no FK, so
 * they're removed explicitly. Reference seeders are idempotent
 * (firstOrCreate) and simply run again.
 *
 * Demo accounts all use the password "password".
 */
class DemoSeeder extends Seeder
{
    public const CENTER_PREFIX = '[Démo] ';

    public const EMAIL_DOMAIN = '@demo.local';

    private User $author;

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->error('Refusing to seed demo data in a production environment.');

            return;
        }

        $this->call([
            EnumOptionSeeder::class,
            SessionMeasurementTypesSeeder::class,
            ZoneSeeder::class,
            CountrySeeder::class,
            GradeSeeder::class,
            DiseaseCategorySeeder::class,
            CareCategorySeeder::class,
            RolesAndPermissionsSeeder::class,
            SuperAdminSeeder::class,
        ]);

        $this->purge();

        DB::transaction(function (): void {
            $lyon = $this->center('01', 'Centre de soins Lyon', 'Lyon', sundayMorning: false);
            $abidjan = $this->center('04', 'Centre de soins Abidjan', 'Abidjan', sundayMorning: true);

            $assigner = app(CenterScopedRoleAssigner::class);

            $managerLyon = $this->user('Claire Martin', 'manager.lyon');
            $assigner->grant($managerLyon, 'manager', $lyon->id);
            $managerMulti = $this->user('Youssef Diallo', 'manager.multi');
            $assigner->grant($managerMulti, 'manager', $lyon->id);
            $assigner->grant($managerMulti, 'manager', $abidjan->id);
            $managerAbidjan = $this->user('Aïcha Koné', 'manager.abidjan');
            $assigner->grant($managerAbidjan, 'manager', $abidjan->id);

            $this->author = $managerMulti;

            // [first, last, center, login?, evening-only?]
            $specs = [
                ['Karim', 'Haddad', $lyon, true, false],
                ['Sophie', 'Bernard', $lyon, true, false],
                ['Nadia', 'Benali', $lyon, true, true],
                ['Omar', 'Traoré', $abidjan, true, false],
                ['Fatou', 'Camara', $abidjan, false, false],
                ['Ibrahim', 'Sow', $lyon, false, false],
            ];

            $practitioners = [];
            foreach ($specs as [$first, $last, $center, $login, $evening]) {
                $practitioner = $this->practitioner($first, $last, $center);

                if ($login) {
                    $user = $this->user("{$first} {$last}", Str::slug("{$first}.{$last}", '.'));
                    $practitioner->update(['user_id' => $user->id]);
                    $assigner->grant($user, 'practitioner', $center->id);
                }

                $this->availabilities($practitioner, $evening);
                $practitioners[] = $practitioner;
            }

            // Karim: a single Practitioner row, access on both centers
            // (same shape as the "join an existing account" flow).
            $assigner->grant($practitioners[0]->user, 'practitioner', $abidjan->id);

            PractitionerTimeOff::factory()->for($practitioners[1])->create([
                'starts_on' => now()->subDays(20)->toDateString(),
                'ends_on' => now()->subDays(16)->toDateString(),
                'reason' => 'sick_leave',
                'created_by' => $managerLyon->id,
            ]);
            $upcomingOff = now()->addDays(15);
            PractitionerTimeOff::factory()->for($practitioners[3])->create([
                'starts_on' => $upcomingOff->toDateString(),
                'ends_on' => $upcomingOff->copy()->addDays(4)->toDateString(),
                'reason' => 'vacation',
                'created_by' => $managerAbidjan->id,
            ]);

            $closureDay = now()->addDays(10);
            CenterClosure::factory()->for($lyon)->create([
                'starts_on' => $closureDay->toDateString(),
                'ends_on' => $closureDay->toDateString(),
                'label' => 'Fête nationale',
                'created_by' => $managerLyon->id,
            ]);

            $lyonPatients = $this->patients($lyon, 14, [$practitioners[0], $practitioners[1], $practitioners[5]]);
            $abidjanPatients = $this->patients($abidjan, 12, [$practitioners[3], $practitioners[4]]);

            $this->appointments($lyon, $lyonPatients, [$practitioners[0], $practitioners[1]], $practitioners[2], $closureDay);
            $this->appointments($abidjan, $abidjanPatients, [$practitioners[3], $practitioners[4]], $practitioners[0], $closureDay);

            $this->consents($lyonPatients, $managerLyon);
            $this->documents($lyonPatients[1]);
        });

        $this->command->info('Demo data seeded — log in as manager.multi@demo.local / password.');
    }

    private function purge(): void
    {
        $centerIds = Center::query()->where('name', 'like', self::CENTER_PREFIX.'%')->pluck('id');
        $userIds = User::query()->where('email', 'like', '%'.self::EMAIL_DOMAIN)->pluck('id');

        $patients = Patient::query()
            ->whereIn('intake_center_id', $centerIds)
            ->orWhere('email', 'like', '%'.self::EMAIL_DOMAIN)
            ->get();
        $treatmentIds = Treatment::query()->whereIn('patient_id', $patients->modelKeys())->pluck('id');

        foreach ($patients as $patient) {
            $patient->consents()->get()->each->delete();
            $patient->delete();
        }

        Status::query()->where(fn ($q) => $q
            ->where(fn ($q) => $q->where('model_type', Patient::class)->whereIn('model_id', $patients->modelKeys()))
            ->orWhere(fn ($q) => $q->where('model_type', Treatment::class)->whereIn('model_id', $treatmentIds))
        )->delete();

        DB::table('model_has_roles')->whereIn('team_id', $centerIds)->delete();
        DB::table('model_has_roles')->where('model_type', User::class)->whereIn('model_id', $userIds)->delete();
        DB::table('roles')->whereIn('team_id', $centerIds)->delete();

        Center::query()->whereIn('id', $centerIds)->delete();
        User::query()->whereIn('id', $userIds)->delete();
    }

    private function center(string $countryCode, string $name, string $city, bool $sundayMorning): Center
    {
        $country = Country::query()->where('code', $countryCode)->firstOrFail();

        $center = Center::query()->create([
            'country_id' => $country->id,
            'code' => app(CenterCodeGenerator::class)->suggestNext($country),
            'name' => self::CENTER_PREFIX.$name,
            'city' => $city,
            'address' => '12 rue de la Paix',
            'phone' => '+33 4 00 00 00 00',
            'email' => Str::slug($city).self::EMAIL_DOMAIN,
            'active' => true,
        ]);

        $hours = collect(range(1, 6))->map(fn (int $day) => [
            'day_of_week' => $day,
            'start_time' => '08:00',
            'end_time' => $day === 6 ? '13:00' : '19:00',
        ]);
        if ($sundayMorning) {
            $hours->push(['day_of_week' => 0, 'start_time' => '09:00', 'end_time' => '12:00']);
        }
        CenterOperatingHours::factory()->for($center)->forEachSequence(...$hours->all())->create();

        return $center;
    }

    private function user(string $name, string $local): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $local.self::EMAIL_DOMAIN,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    private function practitioner(string $first, string $last, Center $center): Practitioner
    {
        return Practitioner::query()->create([
            'first_name' => $first,
            'last_name' => $last,
            'center_id' => $center->id,
            'matricule' => app(PractitionerCodeGenerator::class)->suggestNextMatricule($center),
            'phone' => '+33 6 00 00 00 00',
            'email' => Str::slug("{$first}.{$last}", '.').self::EMAIL_DOMAIN,
        ]);
    }

    /**
     * Weekday day shifts, or evening-only (outside the centers' opening
     * hours on purpose — a recurring availability is never blocked by
     * them, it's the legitimate "late remote session" case).
     */
    private function availabilities(Practitioner $practitioner, bool $evening): void
    {
        $slots = $evening
            ? [['18:00', '21:00']]
            : [['09:00', '12:00'], ['14:00', '18:00']];

        $rows = [];
        foreach (range(1, 5) as $day) {
            foreach ($slots as [$start, $end]) {
                $rows[] = ['day_of_week' => $day, 'start_time' => $start, 'end_time' => $end];
            }
        }

        PractitionerAvailability::factory()->for($practitioner)->forEachSequence(...$rows)->create();
    }

    /**
     * Patients cycle through every Patient::derivedStatus() key: new (no
     * treatment), active, completed, unreachable, stopped.
     *
     * @param  array<int, Practitioner>  $practitioners
     * @return array<int, Patient>
     */
    private function patients(Center $center, int $count, array $practitioners): array
    {
        $diseaseIds = Disease::query()->orderBy('id')->limit(12)->pluck('id')->all();
        $measurementTypes = EnumOption::query()->where('enum_type', 'session_measurement_type')->get()->keyBy('code');
        $firstNames = ['Amina', 'Lucas', 'Moussa', 'Léa', 'Samir', 'Inès', 'Hugo', 'Mariam', 'Yanis', 'Chloé', 'Adama', 'Sarah', 'Bilal', 'Emma'];
        $lastNames = ['Benali', 'Dupont', 'Keita', 'Moreau', 'Haddad', 'Diop', 'Lefebvre', 'Touré', 'Mercier', 'Ndiaye', 'Garnier', 'Coulibaly', 'Rousseau', 'Faye'];
        $scenarios = ['new', 'active', 'active', 'completed', 'unreachable', 'stopped'];

        $patients = [];
        for ($i = 0; $i < $count; $i++) {
            $first = $firstNames[$i % count($firstNames)];
            $last = $lastNames[($i + $center->id) % count($lastNames)];

            $patient = Patient::factory()->create([
                'first_name' => $first,
                'last_name' => $last,
                'email' => Str::slug("{$first}.{$last}.{$center->code}{$i}", '.').self::EMAIL_DOMAIN,
                'city' => $center->city,
                'country_id' => $center->country_id,
                'intake_center_id' => $center->id,
                'patient_number' => app(PatientNumberGenerator::class)->next($center),
                'created_by' => $this->author->id,
            ]);
            $patient->setStatus('confirmed');
            $patients[] = $patient;

            $scenario = $scenarios[$i % count($scenarios)];
            if ($scenario === 'new') {
                continue;
            }

            $practitioner = $practitioners[$i % count($practitioners)];
            $startedAt = now()->subDays(60 - $i * 2);
            $treatment = Treatment::factory()->create([
                'patient_id' => $patient->id,
                'practitioner_id' => $practitioner->id,
                'center_id' => $center->id,
                'started_at' => $startedAt->toDateString(),
                'created_by' => $this->author->id,
            ]);
            $treatment->diseases()->sync([
                $diseaseIds[$i % count($diseaseIds)] => ['actively_tracked' => true],
                $diseaseIds[($i + 5) % count($diseaseIds)] => ['actively_tracked' => $i % 2 === 0],
            ]);
            $treatment->setStatus('confirmed');
            $treatment->setStatus('ongoing');

            $sessions = TreatmentSession::factory()
                ->count(3)
                ->sequence(fn ($seq) => ['session_date' => $startedAt->copy()->addWeeks($seq->index)->toDateString()])
                ->create([
                    'treatment_id' => $treatment->id,
                    'practitioner_id' => $practitioner->id,
                    'duration_minutes' => 45,
                    'modality' => $i % 4 === 0 ? 'remote' : 'in_person',
                    'created_by' => $this->author->id,
                ]);

            if ($i % 2 === 1) {
                $sessions->first()->measurements()->createMany([
                    ['measurement_type_option_id' => $measurementTypes['blood_pressure']->id, 'value' => '12/8', 'unit' => 'mmHg'],
                    ['measurement_type_option_id' => $measurementTypes['weight']->id, 'value' => (string) (60 + $i), 'unit' => 'kg'],
                ]);
            }

            match ($scenario) {
                'completed' => $this->resolveTreatment($treatment, $sessions->last()),
                'unreachable' => $treatment->manualClose('lost_to_follow_up'),
                'stopped' => $treatment->manualClose('protocol_not_followed'),
                default => null,
            };
        }

        return $patients;
    }

    private function resolveTreatment(Treatment $treatment, TreatmentSession $lastSession): void
    {
        foreach ($treatment->diseases()->pluck('diseases.id') as $diseaseId) {
            $lastSession->diseaseProgress()->create(['disease_id' => $diseaseId, 'outcome' => 'cured']);
        }

        $treatment->refreshClosureStatus();
    }

    /**
     * Past (completed/no_show/cancelled), one within the next 24h for the
     * reminders command, and a few further out — never on the closure
     * day. Each practitioner gets distinct slots, so nothing overlaps.
     *
     * @param  array<int, Patient>  $patients
     * @param  array<int, Practitioner>  $dayPractitioners
     */
    private function appointments(Center $center, array $patients, array $dayPractitioners, Practitioner $eveningPractitioner, CarbonInterface $closureDay): void
    {
        [$a, $b] = $dayPractitioners;
        $at = fn (int $days, int $hour) => $this->weekday(now()->addDays($days), $closureDay)->setTime($hour, 0);
        // Within the next 24h (for appointments:send-reminders) but at a
        // plausible hour: today 9h-17h if still possible, else tomorrow
        // 9h, which is then less than 24h away.
        $hour = max(9, now()->hour + 2);
        $soon = $hour <= 17 ? now()->setTime($hour, 0) : now()->addDay()->setTime(9, 0);

        $rows = [
            [$a, $patients[1], $at(-7, 10), 'completed'],
            [$a, $patients[2], $at(-5, 15), 'no_show'],
            [$b, $patients[3], $at(-3, 9), 'cancelled'],
            [$b, $patients[1], $soon, 'scheduled'],
            [$a, $patients[2], $at(2, 10), 'confirmed'],
            [$b, $patients[7], $at(3, 14), 'scheduled'],
            [$a, $patients[8], $at(4, 11), 'scheduled'],
            [$b, $patients[0], $at(6, 16), 'confirmed'],
        ];

        $attributes = array_map(fn (array $row) => [
            'center_id' => $center->id,
            'practitioner_id' => $row[0]->id,
            'patient_id' => $row[1]->id,
            'starts_at' => $row[2],
            'status' => $row[3],
            'duration_minutes' => 45,
            'reason' => 'Séance de suivi',
            'cancellation_reason' => $row[3] === 'cancelled' ? 'Empêchement du patient' : null,
            'created_by' => $this->author->id,
        ], $rows);

        Appointment::factory()->forEachSequence(...$attributes)->create();

        Appointment::factory()->remote()->create([
            'center_id' => $center->id,
            'practitioner_id' => $eveningPractitioner->id,
            'patient_id' => $patients[7]->id,
            'starts_at' => $at(1, 19),
            'duration_minutes' => 30,
            'status' => 'scheduled',
            'reason' => 'Consultation à distance',
            'created_by' => $this->author->id,
        ]);
    }

    /** Next weekday on or after $date that isn't the closure day. */
    private function weekday(CarbonInterface $date, CarbonInterface $closureDay): CarbonInterface
    {
        $date = $date->copy();
        while ($date->isWeekend() || $date->isSameDay($closureDay)) {
            $date = $date->addDay();
        }

        return $date;
    }

    /** @param  array<int, Patient>  $patients */
    private function consents(array $patients, User $acceptedBy): void
    {
        ConsentTemplate::query()->firstOrCreate(
            ['type' => 'treatment', 'is_active' => true],
            ConsentTemplate::factory()->raw([
                'version' => (int) ConsentTemplate::query()->where('type', 'treatment')->max('version') + 1,
                'content' => "Je soussigné(e) accepte de recevoir les soins de médecine complémentaire proposés par le centre.\n\nJ'ai été informé(e) que ces soins ne remplacent pas un suivi médical conventionnel.",
            ]),
        );

        $action = app(RecordPatientConsentAction::class);

        foreach (array_slice($patients, 1, 3) as $patient) {
            $action->digital($patient, 'treatment', [
                'signer_name' => "{$patient->first_name} {$patient->last_name}",
                'signature_svg' => null,
            ], $acceptedBy, null);
        }

        $scan = $this->tempImage('Consentement signé');
        $action->uploaded($patients[4], 'data_privacy', [
            'signer_name' => "{$patients[4]->first_name} {$patients[4]->last_name}",
            'signature_svg' => null,
            'accepted_at' => now()->subDays(30),
        ], [new UploadedFile($scan, 'consentement-scan.png', 'image/png', null, true)], $acceptedBy, null, app(MergeImagesIntoPdfAction::class));
    }

    private function documents(Patient $patient): void
    {
        $session = TreatmentSession::query()
            ->whereHas('treatment', fn ($q) => $q->where('patient_id', $patient->id))
            ->firstOrFail();

        $patient->addMedia($this->tempImage("Pièce d'identité"))
            ->usingName("Pièce d'identité")
            ->toMediaCollection('identity');

        $pdf = sys_get_temp_dir().'/'.Str::uuid().'.pdf';
        file_put_contents($pdf, Pdf::loadHTML('<h1>Compte rendu médical</h1><p>Document de démonstration.</p>')->output());
        $patient->addMedia($pdf)
            ->usingName('Compte rendu médical')
            ->withCustomProperties(['treatment_session_id' => $session->id])
            ->toMediaCollection('medical');

        $patient->addMedia($this->tempImage('Justificatif'))
            ->usingName('Justificatif de domicile')
            ->toMediaCollection('other');
    }

    /** Small PNG generated in memory — no binary fixture in the repo. */
    private function tempImage(string $text): string
    {
        $image = imagecreatetruecolor(400, 250);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 235, 240, 250));
        imagestring($image, 5, 20, 110, $text, (int) imagecolorallocate($image, 28, 50, 80));

        $path = sys_get_temp_dir().'/'.Str::uuid().'.png';
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }
}
