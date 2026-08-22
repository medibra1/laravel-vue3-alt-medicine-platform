export type TDiseaseOutcome = 'cured' | 'not_cured' | 'percentage' | 'ongoing';

interface OutcomeOption {
    label: string;
    value: TDiseaseOutcome;
}

// Single source of truth for the dropdown options in TreatmentWizardDialog
// and TreatmentSessionDialog ('percentage' here is the literal option
// label, not the resolved "62%" text — that resolution happens in
// outcomeLabel() below, which needs the actual percentage value) and for
// the base labels reused by outcomeLabel().
export const outcomeOptions: OutcomeOption[] = [
    { label: 'Guéri', value: 'cured' },
    { label: 'Non guéri', value: 'not_cured' },
    { label: 'En cours', value: 'ongoing' },
    { label: 'Pourcentage', value: 'percentage' },
];

const outcomeLabels: Record<TDiseaseOutcome, string> = Object.fromEntries(
    outcomeOptions.map((option) => [option.value, option.label]),
) as Record<TDiseaseOutcome, string>;

/**
 * Resolved display text for a disease outcome — "percentage" resolves to
 * the actual percentage value (e.g. "62%") rather than the literal option
 * label "Pourcentage", since a percentage on its own isn't informative.
 */
export function outcomeLabel(outcome: string | null, percentage?: number | null): string {
    if (outcome === null) {
        return 'Non renseigné';
    }

    if (outcome === 'percentage') {
        return percentage !== null && percentage !== undefined ? `${percentage}%` : outcomeLabels.percentage;
    }

    return outcomeLabels[outcome as TDiseaseOutcome] ?? outcome;
}

const outcomeColors: Record<TDiseaseOutcome, string> = {
    cured: 'success',
    not_cured: 'error',
    percentage: 'info',
    ongoing: 'warning',
};

/**
 * One color per outcome value (not just resolved/unresolved) so cured,
 * not_cured, percentage and ongoing all read as visually distinct states
 * everywhere a disease's status is shown (TreatmentTimeline, TreatmentCard).
 */
export function outcomeColor(outcome: string | null): string {
    if (outcome === null) {
        return 'secondary';
    }

    return outcomeColors[outcome as TDiseaseOutcome] ?? 'secondary';
}

const outcomeIcons: Record<TDiseaseOutcome, string> = {
    cured: 'mdi-check-circle',
    not_cured: 'mdi-close-circle',
    percentage: 'mdi-percent-circle',
    ongoing: 'mdi-progress-clock',
};

export function outcomeIcon(outcome: string | null): string {
    if (outcome === null) {
        return 'mdi-help-circle';
    }

    return outcomeIcons[outcome as TDiseaseOutcome] ?? 'mdi-help-circle';
}
