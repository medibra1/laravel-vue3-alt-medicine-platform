import { mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import { createVuetify } from 'vuetify';
import TreatmentSessionDialog from './TreatmentSessionDialog.vue';

const vuetify = createVuetify();

const treatmentDiseases = [
    { id: 1, code: '101', label: 'Acidity' },
    { id: 2, code: '201', label: 'Eczema' },
];

const lastKnownOutcomes = {
    1: { outcome: 'ongoing', outcome_percentage: null, notes: 'Improving slowly' },
};

// v-dialog teleports its content to document.body by default, so it never
// shows up under wrapper.element/wrapper.text() — and it stacks across
// mounts unless the previous wrapper is unmounted first.
let activeWrapper: VueWrapper | null = null;

afterEach(() => {
    activeWrapper?.unmount();
    activeWrapper = null;
});

describe('TreatmentSessionDialog', () => {
    it('prefills a new session from lastKnownOutcomes instead of leaving fields blank', () => {
        activeWrapper = mount(TreatmentSessionDialog, {
            props: {
                visible: true,
                patientId: 1,
                treatmentId: 1,
                session: null,
                treatmentDiseases,
                careCategories: [],
                measurementTypes: [],
                lastKnownOutcomes,
            },
            attachTo: document.body,
            global: { plugins: [vuetify] },
        });

        const vm = activeWrapper.vm as unknown as {
            diseaseOutcomes: Record<number, { outcome: string | null; outcome_percentage: number | null; notes: string | null }>;
        };

        expect(vm.diseaseOutcomes[1].outcome).toBe('ongoing');
        expect(vm.diseaseOutcomes[1].notes).toBe('Improving slowly');
        // Disease 2 has no lastKnownOutcomes entry — stays blank, not an error.
        expect(vm.diseaseOutcomes[2].outcome).toBeNull();
    });

    it('shows a "reprise de la dernière séance" hint only for prefilled diseases', async () => {
        activeWrapper = mount(TreatmentSessionDialog, {
            props: {
                visible: true,
                patientId: 1,
                treatmentId: 1,
                session: null,
                treatmentDiseases,
                careCategories: [],
                measurementTypes: [],
                lastKnownOutcomes,
            },
            attachTo: document.body,
            global: { plugins: [vuetify] },
        });

        // The disease-outcome fields live under the "Suivi des maladies" tab
        // (v-window only renders the active tab's content).
        const diseasesTab = activeWrapper.findAllComponents({ name: 'VTab' }).find((tab) => tab.text() === 'Suivi des maladies');
        await diseasesTab?.trigger('click');
        await activeWrapper.vm.$nextTick();

        const bodyText = document.body.textContent ?? '';
        expect(bodyText).toContain('Reprise de la dernière séance');
        // Only disease 1 has a lastKnownOutcomes entry — the hint must not
        // appear more than once (e.g. leaking onto disease 2's row too).
        expect(bodyText.match(/Reprise de la dernière séance/g)).toHaveLength(1);
    });

    it('does not prefill from lastKnownOutcomes when editing an existing session', () => {
        activeWrapper = mount(TreatmentSessionDialog, {
            props: {
                visible: true,
                patientId: 1,
                treatmentId: 1,
                session: {
                    id: 5,
                    session_date: '2026-08-20',
                    duration_minutes: 30,
                    notes: null,
                    care_items: [],
                    disease_progress: [{ disease_id: 1, outcome: 'not_cured', outcome_percentage: null, notes: null }],
                    measurements: [],
                },
                treatmentDiseases,
                careCategories: [],
                measurementTypes: [],
                lastKnownOutcomes,
            },
            attachTo: document.body,
            global: { plugins: [vuetify] },
        });

        const vm = activeWrapper.vm as unknown as {
            diseaseOutcomes: Record<number, { outcome: string | null; outcome_percentage: number | null; notes: string | null }>;
        };

        // The session's own (different) value wins over lastKnownOutcomes.
        expect(vm.diseaseOutcomes[1].outcome).toBe('not_cured');
        expect(document.body.textContent ?? '').not.toContain('Reprise de la dernière séance');
    });
});
