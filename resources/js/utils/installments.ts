import { toLocalDateString } from '@/utils/date';

export type InstallmentInterval = 'monthly' | 'biweekly' | 'weekly';

export interface InstallmentDraft {
    due_on: string;
    amount: number;
}

/**
 * Splits an amount into `count` instalments, to the cent: every one gets
 * the same rounded-down share and the last one takes what is left, so
 * the schedule always adds up exactly.
 */
export function splitInstallments(total: number, count: number, first: Date, interval: InstallmentInterval): InstallmentDraft[] {
    if (count < 1 || total <= 0) {
        return [];
    }

    const cents = Math.round(total * 100);
    const share = Math.floor(cents / count);

    return Array.from({ length: count }, (_, index) => {
        const due = new Date(first.getFullYear(), first.getMonth(), first.getDate());

        if (interval === 'monthly') {
            due.setMonth(due.getMonth() + index);
        } else {
            due.setDate(due.getDate() + index * (interval === 'weekly' ? 7 : 14));
        }

        return {
            due_on: toLocalDateString(due),
            amount: (index === count - 1 ? cents - share * (count - 1) : share) / 100,
        };
    });
}
