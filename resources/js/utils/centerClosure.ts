export interface CenterClosure {
    id: number;
    center_id: number;
    /** YYYY-MM-DD, inclusive. */
    starts_on: string;
    /** YYYY-MM-DD, inclusive. */
    ends_on: string;
    label: string;
    notes: string | null;
}

/** The closure of this center covering this day, if any (YYYY-MM-DD string compare). */
export function closureCovering(closures: CenterClosure[], centerId: number | null, isoDate: string): CenterClosure | null {
    if (centerId === null) return null;

    return closures.find((c) => c.center_id === centerId && c.starts_on <= isoDate && c.ends_on >= isoDate) ?? null;
}
