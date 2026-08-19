import '@mdi/font/css/materialdesignicons.css';
import 'vuetify/styles';
import { createVuetify } from 'vuetify';

const indigoTheme = {
    dark: false,
    colors: {
        primary: '#4F46E5',
        secondary: '#6B7280',
        error: '#DC2626',
        success: '#16A34A',
        warning: '#D97706',
        background: '#F3F4F6',
        surface: '#FFFFFF',
    },
};

const indigoThemeDark = {
    dark: true,
    colors: {
        primary: '#818CF8',
        secondary: '#9CA3AF',
        error: '#F87171',
        success: '#4ADE80',
        warning: '#FBBF24',
        background: '#111827',
        surface: '#1F2937',
    },
};

export const vuetify = createVuetify({
    theme: {
        defaultTheme: 'indigoTheme',
        themes: {
            indigoTheme,
            indigoThemeDark,
        },
    },
    locale: {
        // Langues prévues (voir CLAUDE.md "i18n") : fr/en en LTR, ar en
        // RTL. La locale courante n'est pas encore pilotée dynamiquement
        // (vue-i18n pas câblé côté client) — cette table sera lue
        // automatiquement dès que ce sera le cas, rien à ajouter ici.
        rtl: {
            fr: false,
            en: false,
            ar: true,
        },
    },
});
