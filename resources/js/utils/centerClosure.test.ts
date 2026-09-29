import { describe, expect, it } from 'vitest';
import { type CenterClosure, closureCovering } from './centerClosure';

const closure: CenterClosure = { id: 1, center_id: 3, starts_on: '2026-10-12', ends_on: '2026-10-13', label: 'Travaux', notes: null };

describe('closureCovering', () => {
    it('matches inclusive bounds of the right center only', () => {
        expect(closureCovering([closure], 3, '2026-10-12')).toBe(closure);
        expect(closureCovering([closure], 3, '2026-10-13')).toBe(closure);
        expect(closureCovering([closure], 3, '2026-10-14')).toBeNull();
        expect(closureCovering([closure], 4, '2026-10-12')).toBeNull();
        expect(closureCovering([closure], null, '2026-10-12')).toBeNull();
    });
});
