import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { createVuetify } from 'vuetify';
import TreatmentCard from './TreatmentCard.vue';

const vuetify = createVuetify();

function buildTreatment(overrides: Partial<Record<string, unknown>> = {}) {
    return {
        id: 1,
        client_uuid: 'uuid-1',
        practitioner_id: null,
        center_id: null,
        started_at: '2026-08-01',
        ended_at: null,
        outcome: null,
        outcome_percentage: null,
        notes: null,
        status: 'ongoing',
        closure_reason: null,
        practitioner: null,
        diseases: [
            { id: 1, code: '101', label: 'Anxiety', category_label: 'Mental' },
            { id: 2, code: '201', label: 'Eczema', category_label: 'Skin' },
        ],
        sessions: [],
        locked_disease_ids: [],
        latest_known_outcomes: {
            1: { outcome: 'cured', outcome_percentage: null, notes: null },
        },
        ...overrides,
    };
}

describe('TreatmentCard', () => {
    it('shows the disease chip status and color from latest_known_outcomes', () => {
        const wrapper = mount(TreatmentCard, {
            props: { treatment: buildTreatment() },
            global: { plugins: [vuetify] },
        });

        expect(wrapper.text()).toContain('101 — Anxiety · Guéri');

        const chips = wrapper.findAllComponents({ name: 'VChip' });
        const diseaseChip = chips.find((chip) => chip.text().includes('Anxiety'));
        expect(diseaseChip?.props('color')).toBe('success');
    });

    it('shows a neutral "not tracked yet" state when a disease has no latest_known_outcomes entry', () => {
        const wrapper = mount(TreatmentCard, {
            props: { treatment: buildTreatment() },
            global: { plugins: [vuetify] },
        });

        expect(wrapper.text()).toContain('201 — Eczema · Pas encore de suivi');

        const chips = wrapper.findAllComponents({ name: 'VChip' });
        const diseaseChip = chips.find((chip) => chip.text().includes('Eczema'));
        expect(diseaseChip?.props('color')).toBe('secondary');
    });
});
