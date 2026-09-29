export type TimeOffReason = 'vacation' | 'sick_leave' | 'training' | 'other';

export interface TimeOff {
    id: number;
    practitioner_id: number;
    /** YYYY-MM-DD, inclusive. */
    starts_on: string;
    /** YYYY-MM-DD, inclusive. */
    ends_on: string;
    reason: TimeOffReason | null;
    notes: string | null;
}

export const timeOffReasonOptions: { label: string; value: TimeOffReason }[] = [
    { label: 'Congés', value: 'vacation' },
    { label: 'Arrêt maladie', value: 'sick_leave' },
    { label: 'Formation', value: 'training' },
    { label: 'Autre', value: 'other' },
];

export function timeOffReasonLabel(reason: TimeOffReason | null): string {
    return timeOffReasonOptions.find((option) => option.value === reason)?.label ?? 'Congé';
}

/**
 * The time off covering this practitioner on this day, if any. Dates are
 * compared as YYYY-MM-DD strings, which sort chronologically.
 */
export function timeOffCovering(timeOffs: TimeOff[], practitionerId: number | null, isoDate: string): TimeOff | null {
    if (practitionerId === null) return null;

    return timeOffs.find((t) => t.practitioner_id === practitionerId && t.starts_on <= isoDate && t.ends_on >= isoDate) ?? null;
}

const dateFormatter = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });

export function formatTimeOffPeriod(timeOff: TimeOff): string {
    const format = (iso: string) => dateFormatter.format(new Date(`${iso}T00:00:00`));
    return timeOff.starts_on === timeOff.ends_on ? format(timeOff.starts_on) : `${format(timeOff.starts_on)} → ${format(timeOff.ends_on)}`;
}
