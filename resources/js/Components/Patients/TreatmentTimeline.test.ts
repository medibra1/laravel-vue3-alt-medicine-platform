import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { createVuetify } from 'vuetify';
import TreatmentTimeline from './TreatmentTimeline.vue';

const vuetify = createVuetify();

const sessions = [
    {
        id: 1,
        session_date: '2026-08-01',
        duration_minutes: 30,
        notes: null,
        care_items: [{ id: 1, label: 'Ointment', category_label: 'Ointment' }],
        disease_progress: [
            { disease_id: 1, disease_label: 'Acidity', outcome: 'cured', outcome_percentage: null, notes: null },
        ],
    },
    {
        id: 2,
        session_date: '2026-08-15',
        duration_minutes: 45,
        notes: null,
        care_items: [],
        disease_progress: [
            { disease_id: 1, disease_label: 'Acidity', outcome: 'ongoing', outcome_percentage: null, notes: null },
        ],
    },
];

describe('TreatmentTimeline', () => {
    it('renders one timeline entry per session', () => {
        const wrapper = mount(TreatmentTimeline, {
            props: { sessions, treatmentStatus: 'ongoing' },
            global: { plugins: [vuetify] },
        });

        expect(wrapper.findAllComponents({ name: 'VTimelineItem' })).toHaveLength(2);
    });

    it('emits edit-session with the session when its card is clicked', async () => {
        const wrapper = mount(TreatmentTimeline, {
            props: { sessions, treatmentStatus: 'ongoing' },
            global: { plugins: [vuetify] },
        });

        const cards = wrapper.findAllComponents({ name: 'VCard' });
        await cards[0].trigger('click');

        expect(wrapper.emitted('edit-session')).toHaveLength(1);
        expect(wrapper.emitted('edit-session')?.[0]).toEqual([sessions[0]]);
    });

    it('shows the outcome label alongside the disease name for each distinct status', () => {
        const wrapper = mount(TreatmentTimeline, {
            props: { sessions, treatmentStatus: 'ongoing' },
            global: { plugins: [vuetify] },
        });

        expect(wrapper.text()).toContain('Acidity — Guéri');
        expect(wrapper.text()).toContain('Acidity — En cours');
    });
});
