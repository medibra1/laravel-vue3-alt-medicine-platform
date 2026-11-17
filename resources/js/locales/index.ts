// Each section file holds the three languages side by side, so a string
// is never added in one language and forgotten in the others (a Vitest
// check enforces identical key sets — see locales.test.ts).
const modules = import.meta.glob<{ default: Record<'fr' | 'en' | 'ar', Record<string, unknown>> }>('./sections/*.ts', { eager: true });

export function buildMessages(locale: 'fr' | 'en' | 'ar'): Record<string, unknown> {
    const messages: Record<string, unknown> = {};

    for (const [path, module] of Object.entries(modules)) {
        const name = path.replace('./sections/', '').replace('.ts', '');
        messages[name] = module.default[locale];
    }

    return messages;
}
