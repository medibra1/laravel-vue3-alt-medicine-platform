import { describe, expect, it } from 'vitest';
import { type TimeOff, timeOffCovering, timeOffReasonLabel } from './timeOff';

const timeOff: TimeOff = { id: 1, practitioner_id: 7, starts_on: '2026-10-12', ends_on: '2026-10-16', reason: 'vacation', notes: null };

describe('timeOffCovering', () => {
    it('matches the inclusive bounds of the period', () => {
        expect(timeOffCovering([timeOff], 7, '2026-10-12')).toBe(timeOff);
        expect(timeOffCovering([timeOff], 7, '2026-10-16')).toBe(timeOff);
    });

    it('ignores days outside the period and other practitioners', () => {
        expect(timeOffCovering([timeOff], 7, '2026-10-17')).toBeNull();
        expect(timeOffCovering([timeOff], 8, '2026-10-13')).toBeNull();
        expect(timeOffCovering([timeOff], null, '2026-10-13')).toBeNull();
    });
});

describe('timeOffReasonLabel', () => {
    it('falls back to a generic label', () => {
        expect(timeOffReasonLabel('sick_leave')).toBe('Arrêt maladie');
        expect(timeOffReasonLabel(null)).toBe('Congé');
    });
});
