import '@mdi/font/css/materialdesignicons.css';
import 'vuetify/styles';
import { createVuetify } from 'vuetify';

// Palette adopted 2026-08-21 — see CLAUDE.md for the full rationale.
// `brand` (navy) is used on the app-bar/aside, distinct from `primary`
// (actions/links).
const ruqyaTheme = {
    dark: false,
    colors: {
        primary: '#4E9BF9',
        secondary: '#6B7280',
        error: '#FF637D',
        success: '#6BC070',
        warning: '#DFB52F',
        background: '#F6F8FA',
        surface: '#FFFFFF',
        brand: '#1C3250',
    },
};

const ruqyaThemeDark = {
    dark: true,
    colors: {
        primary: '#4E9BF9',
        secondary: '#9CA3AF',
        error: '#FF8FA3',
        success: '#7FD187',
        warning: '#E8C662',
        background: '#111827',
        surface: '#1F2937',
        brand: '#1C3250',
    },
};

export const vuetify = createVuetify({
    theme: {
        defaultTheme: 'ruqyaTheme',
        themes: {
            ruqyaTheme,
            ruqyaThemeDark,
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
