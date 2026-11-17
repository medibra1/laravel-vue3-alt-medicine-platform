<?php

use App\Domains\Core\Models\Center;
use App\Domains\Patients\Models\Consent;
use App\Domains\Patients\Models\Patient;
use App\Domains\Scheduling\Models\Appointment;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

/** @return array<string, int> */
function demoCounts(): array
{
    return collect(['centers', 'users', 'practitioners', 'patients', 'treatments', 'treatment_sessions', 'appointments', 'consents', 'media', 'statuses', 'model_has_roles', 'roles'])
        ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()])
        ->all();
}

test('seeds centers, patients, appointments and every derived patient status', function () {
    $this->seed(DemoDataSeeder::class);

    expect(Center::query()->where('name', 'like', '[Démo]%')->count())->toBe(2)
        ->and(Patient::count())->toBeGreaterThanOrEqual(20)
        ->and(Consent::count())->toBe(4)
        ->and(Appointment::query()
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->whereNull('reminder_sent_at')
            ->whereBetween('starts_at', [now(), now()->addDay()])
            ->exists())->toBeTrue();

    $statuses = Patient::all()->map(fn (Patient $p) => $p->derivedStatus()['key'])->unique()->sort()->values()->all();
    expect($statuses)->toBe(['active', 'completed', 'new', 'stopped', 'unreachable']);
});

test('running it twice leaves the same row counts', function () {
    $this->seed(DemoDataSeeder::class);
    $first = demoCounts();

    $this->seed(DemoDataSeeder::class);

    expect(demoCounts())->toBe($first);
});

test('DatabaseSeeder calls the demo seeder outside production', function () {
    $this->seed();

    expect(Center::query()->where('name', 'like', '[Démo]%')->count())->toBe(2)
        ->and(DB::table('countries')->count())->toBe(46);
});

test('DatabaseSeeder skips the demo seeder in production', function () {
    app()->detectEnvironment(fn () => 'production');

    // --force: db:seed asks for confirmation in production otherwise.
    $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

    expect(Center::count())->toBe(0)
        ->and(Patient::count())->toBe(0)
        ->and(DB::table('countries')->count())->toBe(46);
});
