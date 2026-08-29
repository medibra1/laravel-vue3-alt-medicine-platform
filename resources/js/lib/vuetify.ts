import '@mdi/font/css/materialdesignicons.css';
import 'vuetify/styles';
import { createVuetify } from 'vuetify';
import { en, fr } from 'vuetify/locale';

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
        // 'locale' (not just the RTL table below) drives date parsing/
        // display in v-date-input — without it, Vuetify defaults to
        // en-US (MM/DD/YYYY). Typing a date the natural DD/MM/YYYY way
        // (e.g. "26/08/2026") then silently parsed/displayed as
        // "07/26/2026" (26 read as a month, invalid, reordered) — found
        // via real browser testing: the wizard's "Date de début" never
        // actually saved a typed value, only a value picked by clicking
        // a day in the calendar, which any real user has no reason to
        // know is required. Locale is still hardcoded 'fr' rather than
        // reactive — same caveat already noted for the RTL table below,
        // vue-i18n isn't wired client-side yet.
        //
        // `messages` must be supplied explicitly — Vuetify does not
        // auto-load every bundled locale, `locale: 'fr'` alone left every
        // internal string (aria-labels, "Effacer", the calendar's
        // month/day names) falling back to English with a
        // `Translation key "$vuetify.xxx" not found in "fr"` console
        // warning on every affected render, found via real browser
        // testing (a `v-badge`/any clearable input warned on every
        // interaction). `en` kept as `fallback` so a genuinely missing
        // key still degrades to English text instead of a raw key path.
        locale: 'fr',
        fallback: 'en',
        messages: { fr, en },
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
