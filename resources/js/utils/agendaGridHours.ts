import type { WeeklySlot } from '@/utils/weeklySchedule';

export const DEFAULT_GRID_HOURS = { startHour: 8, endHour: 19 } as const;

function hourFloor(time: string): number {
    return parseInt(time.slice(0, 2), 10);
}

function hourCeil(time: string): number {
    const [h, m] = time.split(':').map((part) => parseInt(part, 10));
    return m > 0 ? h + 1 : h;
}

/**
 * Agenda grid range, in two layers:
 * 1. Base: the center's opening hours for the displayed weekdays (a center
 *    with nothing configured for those days falls back to 8h-19h).
 * 2. Safety net: widen to fit any loaded appointment outside that base —
 *    an existing appointment is never hidden.
 */
export function computeGridHours(
    operatingHours: WeeklySlot[],
    displayedDays: number[],
    appointments: { starts_at: string; ends_at: string }[],
): { startHour: number; endHour: number } {
    const relevant = operatingHours.filter((h) => displayedDays.includes(h.day_of_week));

    let start: number = relevant.length ? Math.min(...relevant.map((h) => hourFloor(h.start_time))) : DEFAULT_GRID_HOURS.startHour;
    let end: number = relevant.length ? Math.max(...relevant.map((h) => hourCeil(h.end_time))) : DEFAULT_GRID_HOURS.endHour;

    for (const appointment of appointments) {
        const startsAt = new Date(appointment.starts_at);
        // Measured from the start's midnight so an appointment ending at
        // 00:00 the next day counts as 24, not 0.
        const endHours =
            startsAt.getHours() + startsAt.getMinutes() / 60 + (new Date(appointment.ends_at).getTime() - startsAt.getTime()) / 3_600_000;
        start = Math.min(start, startsAt.getHours());
        end = Math.max(end, Math.ceil(endHours));
    }

    return { startHour: start, endHour: Math.min(24, end) };
}
