import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { createVuetify } from 'vuetify';
import PatientStatusChip from './PatientStatusChip.vue';

const vuetify = createVuetify();

describe('PatientStatusChip', () => {
    it('shows the label and color from the status prop', () => {
        const wrapper = mount(PatientStatusChip, {
            props: {
                status: { key: 'stopped', label: 'Arrêté', color: 'error' },
                clickable: false,
            },
            global: { plugins: [vuetify] },
        });

        expect(wrapper.text()).toContain('Arrêté');
        const chip = wrapper.findComponent({ name: 'VChip' });
        expect(chip.props('color')).toBe('error');
    });

    it('is not clickable when clickable is false', () => {
        const wrapper = mount(PatientStatusChip, {
            props: {
                status: { key: 'completed', label: 'Terminé', color: 'info' },
                clickable: false,
            },
            global: { plugins: [vuetify] },
        });

        const chip = wrapper.findComponent({ name: 'VChip' });
        expect(chip.props('link')).toBe(false);
    });

    it('is clickable and emits click when clickable is true', async () => {
        const wrapper = mount(PatientStatusChip, {
            props: {
                status: { key: 'active', label: 'Actif', color: 'success' },
                clickable: true,
            },
            global: { plugins: [vuetify] },
        });

        const chip = wrapper.findComponent({ name: 'VChip' });
        expect(chip.props('link')).toBe(true);

        await chip.trigger('click');
        expect(wrapper.emitted('click')).toHaveLength(1);
    });
});
