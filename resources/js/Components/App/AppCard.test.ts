import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { createVuetify } from 'vuetify';
import AppCard from './AppCard.vue';

const vuetify = createVuetify();

describe('AppCard', () => {
    it('renders a long title without truncating it via the native v-card title prop', () => {
        const longTitle = 'Maladies mentales et cérébrales — catégorie avec un libellé assez long pour tester le retour à la ligne';

        const wrapper = mount(AppCard, {
            props: { title: longTitle },
            global: { plugins: [vuetify] },
        });

        // v-card's native `title` prop renders into a `title` HTML
        // attribute (and forces single-line ellipsis) instead of visible
        // text — asserting the full text is present as rendered content
        // (not just an attribute) confirms the #title slot path is used.
        expect(wrapper.text()).toContain(longTitle);
        expect(wrapper.find('.v-card').attributes('title')).toBeUndefined();
    });

    it('shows the icon next to the title when provided', () => {
        const wrapper = mount(AppCard, {
            props: { title: 'Maladies digestives', icon: 'mdi-stomach' },
            global: { plugins: [vuetify] },
        });

        expect(wrapper.find('.mdi-stomach').exists()).toBe(true);
    });

    it('does not render a title slot when no title is given', () => {
        const wrapper = mount(AppCard, {
            props: {},
            global: { plugins: [vuetify] },
        });

        expect(wrapper.find('.v-card-title').exists()).toBe(false);
    });
});
