import { existsSync, readFileSync, rmSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { runTinker } from './run-tinker';

const dirname = path.dirname(fileURLToPath(import.meta.url));
const FIXTURE_PATH = path.join(dirname, '.fixture.json');

export default function globalTeardown() {
    if (!existsSync(FIXTURE_PATH)) {
        return;
    }

    const fixture = JSON.parse(readFileSync(FIXTURE_PATH, 'utf-8')) as { patientId: number };

    const script = `
use App\\Domains\\Auth\\Models\\User;
use App\\Domains\\Patients\\Models\\Patient;
use Illuminate\\Support\\Str;

Patient::find(${fixture.patientId})?->delete();

$user = User::where('email', config('app.super_admin.email'))->first();
$user->password = Hash::make(Str::random(32));
$user->save();
`;

    runTinker(script);
    rmSync(FIXTURE_PATH);
}
