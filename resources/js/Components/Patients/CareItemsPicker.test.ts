import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { createVuetify } from 'vuetify';
import CareItemsPicker from './CareItemsPicker.vue';

const vuetify = createVuetify();

const careCategories = [
    {
        id: 1,
        code: 'cupping',
        label: 'Ventouses',
        items: [
            { id: 10, code: '066', label: 'Pied' },
            { id: 11, code: '001', label: 'Tête' },
        ],
    },
    {
        id: 2,
        code: 'verse',
        label: 'Verset à ajouter',
        items: [{ id: 20, code: '001', label: 'S21 v30 (Cadenas)' }],
    },
];

describe('CareItemsPicker', () => {
    it('shows one collapsed accordion panel per category with an item count when items are selected', () => {
        const wrapper = mount(CareItemsPicker, {
            props: { careCategories, modelValue: new Set([10]) },
            global: { plugins: [vuetify] },
        });

        expect(wrapper.text()).toContain('Ventouses (1)');
        expect(wrapper.text()).toContain('Verset à ajouter');
        expect(wrapper.text()).not.toContain('Verset à ajouter (');
    });

    it('filters items across all categories when searching, showing the category as a chip', async () => {
        const wrapper = mount(CareItemsPicker, {
            props: { careCategories, modelValue: new Set<number>() },
            global: { plugins: [vuetify] },
        });

        await wrapper.find('input').setValue('cadenas');

        expect(wrapper.text()).toContain('S21 v30 (Cadenas)');
        expect(wrapper.text()).not.toContain('Pied');
        expect(wrapper.text()).not.toContain('Tête');
    });

    it('emits update:modelValue with the toggled item added', async () => {
        const wrapper = mount(CareItemsPicker, {
            props: { careCategories, modelValue: new Set<number>() },
            global: { plugins: [vuetify] },
        });

        await wrapper.find('input').setValue('tête');
        const checkbox = wrapper.find('input[type="checkbox"]');
        await checkbox.setValue(true);

        const emitted = wrapper.emitted('update:modelValue');
        expect(emitted).toHaveLength(1);
        expect(emitted?.[0][0]).toEqual(new Set([11]));
    });

    it('emits update:modelValue with the toggled item removed when already selected', async () => {
        const wrapper = mount(CareItemsPicker, {
            props: { careCategories, modelValue: new Set([11]) },
            global: { plugins: [vuetify] },
        });

        await wrapper.find('input').setValue('tête');
        const checkbox = wrapper.find('input[type="checkbox"]');
        await checkbox.setValue(false);

        const emitted = wrapper.emitted('update:modelValue');
        expect(emitted).toHaveLength(1);
        expect(emitted?.[0][0]).toEqual(new Set());
    });

    it('toggles an item inside an expanded accordion panel', async () => {
        const wrapper = mount(CareItemsPicker, {
            props: { careCategories, modelValue: new Set<number>() },
            global: { plugins: [vuetify] },
        });

        await wrapper.find('.v-expansion-panel-title').trigger('click');
        const checkbox = wrapper.find('input[type="checkbox"]');
        await checkbox.setValue(true);

        const emitted = wrapper.emitted('update:modelValue');
        expect(emitted).toHaveLength(1);
        expect(emitted?.[0][0]).toEqual(new Set([10]));
    });
});
