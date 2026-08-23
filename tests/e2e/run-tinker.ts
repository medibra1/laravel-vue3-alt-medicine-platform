import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dirname = path.dirname(fileURLToPath(import.meta.url));
export const projectRoot = path.join(dirname, '..', '..');

// tinker's `--execute` takes a single shell argument, and a literal "\n"
// inside a double-quoted shell string is never turned into a real newline
// by the shell — base64-encoding the script and decoding it inline
// sidesteps quoting/escaping entirely regardless of shell involved.
//
// `php` is also only resolvable through a login shell in some local
// setups — see CLAUDE.md "Piège d'environnement rencontré en
// vérification" — hence routing through `zsh -l`.
export function runTinker(script: string): string {
    const encoded = Buffer.from(script, 'utf-8').toString('base64');

    return execFileSync(
        'zsh',
        ['-l', '-c', `php artisan tinker --execute="eval(base64_decode('${encoded}'));"`],
        { cwd: projectRoot, encoding: 'utf-8' },
    );
}
