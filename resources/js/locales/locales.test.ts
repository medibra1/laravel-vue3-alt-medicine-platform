import { describe, expect, it } from 'vitest';
import { buildMessages } from './index';
import { tr } from '@/lib/i18n';

function keys(value: unknown, prefix = ''): string[] {
    if (Array.isArray(value)) {
        return [`${prefix}[${value.length}]`];
    }
    if (value !== null && typeof value === 'object') {
        return Object.entries(value).flatMap(([key, child]) => keys(child, prefix ? `${prefix}.${key}` : key));
    }

    return [prefix];
}

describe('locales', () => {
    const fr = keys(buildMessages('fr')).sort();

    it.each(['en', 'ar'] as const)('%s has exactly the same keys as fr', (locale) => {
        expect(keys(buildMessages(locale)).sort()).toEqual(fr);
    });

    it('resolves list entries used for day names', () => {
        expect(tr('domain.days[1]')).toBe('Lundi');
        expect(tr('domain.daysShort[0]')).toBe('Dim');
    });
});
