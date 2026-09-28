import { describe, expect, it } from 'vitest';
import { flattenGrid, gridFromSlots, summarizeSchedule } from './weeklySchedule';

const week = [1, 2, 3, 4, 5].flatMap((day) => [
    { day_of_week: day, start_time: '08:00', end_time: '12:00' },
    { day_of_week: day, start_time: '14:00', end_time: '18:00' },
]);

describe('weeklySchedule', () => {
    it('groups consecutive identical days in the summary', () => {
        expect(summarizeSchedule([...week, { day_of_week: 6, start_time: '09:00', end_time: '12:00' }])).toBe(
            'Lun-Ven 08:00-12:00, 14:00-18:00 · Sam 09:00-12:00',
        );
    });

    it('flattens in Monday-first order and keeps the origin of each slot', () => {
        const { slots, origin } = flattenGrid(gridFromSlots([{ day_of_week: 0, start_time: '10:00', end_time: '11:00' }, ...week]));
        expect(slots[0]).toEqual({ day_of_week: 1, start_time: '08:00', end_time: '12:00' });
        expect(slots.at(-1)?.day_of_week).toBe(0);
        expect(origin.at(-1)).toEqual({ day: 0, index: 0 });
    });
});
