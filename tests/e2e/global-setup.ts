import { writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { runTinker } from './run-tinker';

const dirname = path.dirname(fileURLToPath(import.meta.url));
const FIXTURE_PATH = path.join(dirname, '.fixture.json');

// This project's Pest suite runs against an in-memory sqlite DB, unusable
// for a real `php artisan serve` process — so E2E tests run against the
// same local dev DB the rest of this app's manual browser verifications
// have always used (see CLAUDE.md "Vérification navigateur réelle"
// entries). Tinker is shelled out to rather than adding a dedicated
// Artisan command, keeping this a test-only concern with zero new
// application code.
const script = `
use App\\Domains\\Auth\\Models\\User;
use App\\Domains\\Core\\Models\\Center;
use App\\Domains\\Patients\\Models\\Patient;
use App\\Domains\\Patients\\Models\\Treatment;
use App\\Domains\\Patients\\Models\\TreatmentSession;
use App\\Domains\\Patients\\Models\\Disease;
use App\\Domains\\Practitioners\\Models\\Practitioner;

$user = User::where('email', config('app.super_admin.email'))->first();
$user->password = Hash::make(config('app.super_admin.password'));
$user->save();

$center = Center::first();
$disease = Disease::first();
$practitioner = Practitioner::first();

$patient = Patient::factory()->create(['intake_center_id' => $center->id]);
$patient->setStatus('confirmed');

$ongoing = Treatment::factory()->create([
    'patient_id' => $patient->id,
    'center_id' => $center->id,
    'practitioner_id' => $practitioner?->id,
    'started_at' => now()->subDays(5),
]);
$ongoing->setStatus('ongoing');
$ongoing->diseases()->attach($disease->id);

$closed = Treatment::factory()->create([
    'patient_id' => $patient->id,
    'center_id' => $center->id,
    'practitioner_id' => $practitioner?->id,
    'started_at' => now()->subDays(60),
    'ended_at' => now()->subDays(30),
]);
$closed->setStatus('closed');
$closed->update(['closure_reason' => 'closed_manually']);
$closed->diseases()->attach($disease->id);

$session = TreatmentSession::create([
    'treatment_id' => $closed->id,
    'session_date' => now()->subDays(31),
    'duration_minutes' => 20,
    'created_by' => $user->id,
]);
$session->diseaseProgress()->create(['disease_id' => $disease->id, 'outcome' => 'cured']);

echo json_encode(['patientId' => $patient->id, 'ongoingTreatmentId' => $ongoing->id, 'closedTreatmentId' => $closed->id]);
`;

export default function globalSetup() {
    const output = runTinker(script);

    const jsonLine = output
        .split('\n')
        .reverse()
        .find((line) => line.trim().startsWith('{'));

    if (!jsonLine) {
        throw new Error(`Could not find fixture JSON in tinker output:\n${output}`);
    }

    writeFileSync(FIXTURE_PATH, jsonLine.trim());
}
