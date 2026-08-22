import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { createVuetify } from 'vuetify';
import AppTabs from './AppTabs.vue';

const vuetify = createVuetify();

const tabs = [
    { title: 'First', value: 'first' },
    { title: 'Second', value: 'second' },
];

describe('AppTabs', () => {
    it('renders the window item matching modelValue as active', () => {
        const wrapper = mount(AppTabs, {
            props: { tabs, modelValue: 'second' },
            global: { plugins: [vuetify] },
            slots: {
                first: '<div class="first-content">First content</div>',
                second: '<div class="second-content">Second content</div>',
            },
        });

        expect(wrapper.find('.second-content').exists()).toBe(true);
    });

    it('emits update:modelValue when a tab is selected', async () => {
        const wrapper = mount(AppTabs, {
            props: { tabs, modelValue: 'first' },
            global: { plugins: [vuetify] },
            slots: {
                first: '<div>First content</div>',
                second: '<div>Second content</div>',
            },
        });

        const tabButtons = wrapper.findAllComponents({ name: 'VTab' });
        await tabButtons[1].trigger('click');

        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['second']);
    });
});
