import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
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

    it('groups care items by category label, one heading per category', () => {
        const groupedSessions = [
            {
                id: 3,
                session_date: '2026-08-20',
                duration_minutes: 20,
                notes: null,
                care_items: [
                    { id: 10, label: 'Tête', category_label: 'Ventouses' },
                    { id: 11, label: 'S21 v30 (Cadenas)', category_label: 'Verset à ajouter' },
                    { id: 12, label: 'Pied', category_label: 'Ventouses' },
                ],
                disease_progress: [],
            },
        ];

        const wrapper = mount(TreatmentTimeline, {
            props: { sessions: groupedSessions, treatmentStatus: 'ongoing' },
            global: { plugins: [vuetify] },
        });

        expect(wrapper.text()).toContain('Ventouses');
        expect(wrapper.text()).toContain('Verset à ajouter');
        expect(wrapper.text()).toContain('Tête');
        expect(wrapper.text()).toContain('Pied');
        expect(wrapper.text()).toContain('S21 v30 (Cadenas)');

        const chips = wrapper.findAllComponents({ name: 'VChip' });
        expect(chips).toHaveLength(3);
    });
});

describe('TreatmentTimeline — delete session', () => {
    let confirmSpy: ReturnType<typeof vi.spyOn>;

    beforeEach(() => {
        confirmSpy = vi.spyOn(window, 'confirm');
    });

    afterEach(() => {
        confirmSpy.mockRestore();
    });

    it('emits delete-session with the session when confirmed', async () => {
        confirmSpy.mockReturnValue(true);

        const wrapper = mount(TreatmentTimeline, {
            props: { sessions, treatmentStatus: 'ongoing' },
            global: { plugins: [vuetify] },
        });

        const deleteButtons = wrapper.findAll('button[aria-label="Supprimer la séance"]');
        expect(deleteButtons).toHaveLength(2);
        await deleteButtons[0].trigger('click');

        expect(confirmSpy).toHaveBeenCalledTimes(1);
        expect(wrapper.emitted('delete-session')).toHaveLength(1);
        expect(wrapper.emitted('delete-session')?.[0]).toEqual([sessions[0]]);
    });

    it('does not emit delete-session when the confirmation is cancelled', async () => {
        confirmSpy.mockReturnValue(false);

        const wrapper = mount(TreatmentTimeline, {
            props: { sessions, treatmentStatus: 'ongoing' },
            global: { plugins: [vuetify] },
        });

        const deleteButtons = wrapper.findAll('button[aria-label="Supprimer la séance"]');
        await deleteButtons[0].trigger('click');

        expect(confirmSpy).toHaveBeenCalledTimes(1);
        expect(wrapper.emitted('delete-session')).toBeUndefined();
    });

    it('does not emit edit-session when the delete button is clicked (click.stop)', async () => {
        confirmSpy.mockReturnValue(true);

        const wrapper = mount(TreatmentTimeline, {
            props: { sessions, treatmentStatus: 'ongoing' },
            global: { plugins: [vuetify] },
        });

        const deleteButtons = wrapper.findAll('button[aria-label="Supprimer la séance"]');
        await deleteButtons[0].trigger('click');

        expect(wrapper.emitted('edit-session')).toBeUndefined();
    });
});
