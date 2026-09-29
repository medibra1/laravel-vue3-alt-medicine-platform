/**
 * Weekly schedule helpers shared by the availability screens. Storage
 * keeps day_of_week as 0 = Sunday; only the display order starts on
 * Monday.
 */
export interface WeeklySlot {
    day_of_week: number;
    start_time: string;
    end_time: string;
}

export interface TimeRange {
    start_time: string;
    end_time: string;
}

export type WeeklyGrid = Record<number, TimeRange[]>;

export const dayLabels = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
export const shortDayLabels = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];

/** Monday → Sunday display order. */
export const displayDayOrder = [1, 2, 3, 4, 5, 6, 0];

export function emptyGrid(): WeeklyGrid {
    return Object.fromEntries(displayDayOrder.map((day) => [day, [] as TimeRange[]]));
}

export function gridFromSlots(slots: WeeklySlot[]): WeeklyGrid {
    const grid = emptyGrid();
    for (const slot of slots) {
        grid[slot.day_of_week].push({ start_time: slot.start_time, end_time: slot.end_time });
    }
    for (const day of displayDayOrder) {
        grid[day].sort((a, b) => a.start_time.localeCompare(b.start_time));
    }
    return grid;
}

/**
 * Flattens the grid in display order. The returned `origin` maps each
 * payload index back to its day/range, so backend errors keyed
 * `slots.N.*` can be shown on the right row.
 */
export function flattenGrid(grid: WeeklyGrid): { slots: WeeklySlot[]; origin: { day: number; index: number }[] } {
    const slots: WeeklySlot[] = [];
    const origin: { day: number; index: number }[] = [];
    for (const day of displayDayOrder) {
        grid[day].forEach((range, index) => {
            slots.push({ day_of_week: day, start_time: range.start_time, end_time: range.end_time });
            origin.push({ day, index });
        });
    }
    return { slots, origin };
}

/**
 * Human summary, grouping consecutive days sharing identical ranges:
 * "Lun-Ven 08:00-12:00, 14:00-18:00 · Sam 09:00-12:00".
 */
export function summarizeSchedule(slots: WeeklySlot[]): string {
    const grid = gridFromSlots(slots);
    const parts: string[] = [];
    let groupStart: number | null = null;
    let previousDay: number | null = null;
    let previousKey = '';

    const flush = () => {
        if (groupStart === null || previousDay === null || previousKey === '') {
            return;
        }
        const days = groupStart === previousDay ? shortDayLabels[groupStart] : `${shortDayLabels[groupStart]}-${shortDayLabels[previousDay]}`;
        parts.push(`${days} ${previousKey}`);
    };

    for (const day of displayDayOrder) {
        const key = grid[day].map((range) => `${range.start_time}-${range.end_time}`).join(', ');
        if (key !== previousKey) {
            flush();
            groupStart = day;
            previousKey = key;
        }
        previousDay = day;
    }
    flush();

    return parts.join(' · ');
}
