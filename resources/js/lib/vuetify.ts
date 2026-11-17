import '@mdi/font/css/materialdesignicons.css';
import 'vuetify/styles';
import type { AppI18n } from '@/lib/i18n';
import { useI18n } from 'vue-i18n';
import { createVuetify } from 'vuetify';
import { createVueI18nAdapter } from 'vuetify/locale/adapters/vue-i18n';

// Palette adopted 2026-11-16 (premium redesign): deep emerald primary,
// warm gold accent, soft neutral surfaces. Theme names are kept from the
// previous palette — useAppTheme and the stored preference rely on them.
const ruqyaTheme = {
    dark: false,
    colors: {
        primary: '#0F766E',
        secondary: '#64748B',
        accent: '#B8862F',
        error: '#DC2626',
        success: '#16A34A',
        warning: '#D97706',
        info: '#2563EB',
        background: '#F4F6F5',
        surface: '#FFFFFF',
        'surface-variant': '#EEF2F1',
        'on-surface-variant': '#334155',
        brand: '#0B2F2B',
    },
    variables: {
        'border-color': '#0F172A',
        'border-opacity': 0.08,
        'medium-emphasis-opacity': 0.64,
    },
};

const ruqyaThemeDark = {
    dark: true,
    colors: {
        primary: '#2DD4BF',
        secondary: '#94A3B8',
        accent: '#E0B15E',
        error: '#F87171',
        success: '#4ADE80',
        warning: '#FBBF24',
        info: '#60A5FA',
        background: '#0A1211',
        surface: '#111B1A',
        'surface-variant': '#1A2726',
        'on-surface-variant': '#CBD5E1',
        brand: '#0B2F2B',
    },
    variables: {
        'border-color': '#FFFFFF',
        'border-opacity': 0.09,
        'medium-emphasis-opacity': 0.68,
    },
};

/**
 * One instance per app (and per SSR request): locale, RTL and Vuetify's
 * own strings all follow the vue-i18n instance passed in.
 */
export function createAppVuetify(i18n: AppI18n) {
    return createVuetify({
        theme: {
            defaultTheme: 'ruqyaTheme',
            themes: { ruqyaTheme, ruqyaThemeDark },
        },
        locale: {
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            adapter: createVueI18nAdapter({ i18n: i18n as any, useI18n }),
        },
        defaults: {
            VBtn: { rounded: 'lg', class: 'text-none font-weight-medium' },
            VTextField: { rounded: 'lg', color: 'primary' },
            VSelect: { rounded: 'lg', color: 'primary' },
            VAutocomplete: { rounded: 'lg', color: 'primary' },
            VTextarea: { rounded: 'lg', color: 'primary' },
            VNumberInput: { rounded: 'lg', color: 'primary' },
            VDateInput: { rounded: 'lg', color: 'primary' },
            VChip: { rounded: 'lg' },
            VAlert: { rounded: 'lg' },
            VMenu: { offset: 8 },
            VTooltip: { location: 'top' },
            VCheckbox: { color: 'primary' },
            VSwitch: { color: 'primary', inset: true },
            VTabs: { color: 'primary' },
            VDataTableServer: { hover: true },
        },
    });
}
