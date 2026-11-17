import { describe, expect, it } from 'vitest';
import { splitInstallments } from './installments';

describe('splitInstallments', () => {
    it('adds up to the cent, the remainder going to the last instalment', () => {
        const rows = splitInstallments(100, 3, new Date(2026, 10, 17), 'monthly');

        expect(rows.map((row) => row.amount)).toEqual([33.33, 33.33, 33.34]);
        expect(rows.map((row) => row.due_on)).toEqual(['2026-11-17', '2026-12-17', '2027-01-17']);
    });

    it('spaces weekly and fortnightly schedules by days', () => {
        expect(splitInstallments(90, 3, new Date(2026, 10, 17), 'weekly').map((row) => row.due_on)).toEqual(['2026-11-17', '2026-11-24', '2026-12-01']);
        expect(splitInstallments(90, 2, new Date(2026, 10, 17), 'biweekly').map((row) => row.due_on)).toEqual(['2026-11-17', '2026-12-01']);
    });

    it('returns nothing for an empty amount', () => {
        expect(splitInstallments(0, 3, new Date(), 'monthly')).toEqual([]);
    });
});
