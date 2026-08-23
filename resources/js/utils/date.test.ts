import { describe, expect, it } from 'vitest';
import { fromLocalDateString, toLocalDateString } from './date';

describe('toLocalDateString', () => {
    it('formats a local date as YYYY-MM-DD without shifting timezones', () => {
        expect(toLocalDateString(new Date(2026, 7, 23))).toBe('2026-08-23');
    });

    it('zero-pads single-digit months and days', () => {
        expect(toLocalDateString(new Date(2026, 0, 5))).toBe('2026-01-05');
    });
});

describe('fromLocalDateString', () => {
    it('parses YYYY-MM-DD into a local Date at midnight, not UTC', () => {
        const date = fromLocalDateString('2026-08-23');

        expect(date).not.toBeNull();
        expect(date?.getFullYear()).toBe(2026);
        expect(date?.getMonth()).toBe(7);
        expect(date?.getDate()).toBe(23);
        expect(date?.getHours()).toBe(0);
    });

    it('returns null for null or empty input', () => {
        expect(fromLocalDateString(null)).toBeNull();
        expect(fromLocalDateString('')).toBeNull();
    });
});

describe('round-trip', () => {
    it('never touches UTC getters/ISO conversion, so it cannot shift by a day regardless of the runner timezone', () => {
        // Pick a date whose UTC and local representations would disagree
        // under a naive `toISOString()` implementation for any timezone
        // ahead of UTC (the exact bug being regression-tested here).
        const selected = new Date(2026, 7, 23, 0, 0, 0);
        const serialized = toLocalDateString(selected);

        expect(serialized).toBe('2026-08-23');

        const parsed = fromLocalDateString(serialized);

        expect(parsed?.getFullYear()).toBe(selected.getFullYear());
        expect(parsed?.getMonth()).toBe(selected.getMonth());
        expect(parsed?.getDate()).toBe(selected.getDate());
    });

    it('round-trips the first and last day of a month correctly', () => {
        expect(fromLocalDateString(toLocalDateString(new Date(2026, 1, 1)))?.getDate()).toBe(1);
        expect(fromLocalDateString(toLocalDateString(new Date(2026, 0, 31)))?.getDate()).toBe(31);
    });
});
