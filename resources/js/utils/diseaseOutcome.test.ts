import { describe, expect, it } from 'vitest';
import { outcomeColor, outcomeIcon, outcomeLabel } from './diseaseOutcome';

describe('outcomeLabel', () => {
    it('returns "Guéri" for cured', () => {
        expect(outcomeLabel('cured')).toBe('Guéri');
    });

    it('returns "Non guéri" for not_cured', () => {
        expect(outcomeLabel('not_cured')).toBe('Non guéri');
    });

    it('returns "En cours" for ongoing', () => {
        expect(outcomeLabel('ongoing')).toBe('En cours');
    });

    it('returns the resolved percentage for percentage', () => {
        expect(outcomeLabel('percentage', 62)).toBe('62%');
    });

    it('falls back to the literal option label when percentage has no value', () => {
        expect(outcomeLabel('percentage', null)).toBe('Pourcentage');
        expect(outcomeLabel('percentage')).toBe('Pourcentage');
    });

    it('returns "Non renseigné" for null', () => {
        expect(outcomeLabel(null)).toBe('Non renseigné');
    });
});

describe('outcomeColor', () => {
    it('returns a distinct color for each outcome value', () => {
        expect(outcomeColor('cured')).toBe('success');
        expect(outcomeColor('not_cured')).toBe('error');
        expect(outcomeColor('percentage')).toBe('info');
        expect(outcomeColor('ongoing')).toBe('warning');
    });

    it('returns secondary for null', () => {
        expect(outcomeColor(null)).toBe('secondary');
    });
});

describe('outcomeIcon', () => {
    it('returns a distinct icon for each outcome value', () => {
        expect(outcomeIcon('cured')).toBe('mdi-check-circle');
        expect(outcomeIcon('not_cured')).toBe('mdi-close-circle');
        expect(outcomeIcon('percentage')).toBe('mdi-percent-circle');
        expect(outcomeIcon('ongoing')).toBe('mdi-progress-clock');
    });

    it('returns a help icon for null', () => {
        expect(outcomeIcon(null)).toBe('mdi-help-circle');
    });
});
