import { describe, expect, it } from 'vitest';
import { computeGridHours } from './agendaGridHours';

const hours = [
    { day_of_week: 1, start_time: '09:00', end_time: '12:00' },
    { day_of_week: 1, start_time: '14:00', end_time: '17:30' },
    { day_of_week: 6, start_time: '10:00', end_time: '13:00' },
];

describe('computeGridHours', () => {
    it('falls back to 8h-19h when the center has no hours', () => {
        expect(computeGridHours([], [1], [])).toEqual({ startHour: 8, endHour: 19 });
    });

    it('uses the opening hours of the displayed days only', () => {
        expect(computeGridHours(hours, [6], [])).toEqual({ startHour: 10, endHour: 13 });
        expect(computeGridHours(hours, [1], [])).toEqual({ startHour: 9, endHour: 18 });
    });

    it('falls back when every displayed day is closed', () => {
        expect(computeGridHours(hours, [0], [])).toEqual({ startHour: 8, endHour: 19 });
    });

    it('widens to fit an appointment outside the opening hours', () => {
        const result = computeGridHours(hours, [6], [
            { starts_at: new Date(2026, 8, 26, 7, 30).toISOString(), ends_at: new Date(2026, 8, 26, 8, 0).toISOString() },
            { starts_at: new Date(2026, 8, 26, 20, 0).toISOString(), ends_at: new Date(2026, 8, 26, 20, 45).toISOString() },
        ]);
        expect(result).toEqual({ startHour: 7, endHour: 21 });
    });
});
